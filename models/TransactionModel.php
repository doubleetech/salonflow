<?php

/**
 * TransactionModel
 * Owns all reads/writes to `transactions` and `transaction_tips`.
 *
 * The one rule that matters most here: commission_percentage_applied,
 * worker_commission, and salon_share are computed ONCE at save time using
 * the worker's CURRENT commission_percentage, then written onto the row.
 * From that point on they are just data — nothing ever recalculates them
 * off worker_profiles again, even if the worker's rate changes later.
 */
class TransactionModel
{
    /**
     * Creates a new sale record. Returns the new transaction id.
     * $amounts is ['cash' => x, 'transfer' => y, 'pos' => z] — for a single
     * payment method (not "combination"), the caller puts the full amount
     * made into the matching key and zero into the other two. This keeps
     * amount_cash / amount_transfer / amount_pos always summable later,
     * regardless of whether the sale was a combination or not.
     *
     * $businessDate is explicit (no default) — the caller (CashierController)
     * decides "today" or "yesterday" (backdating) and is responsible for
     * checking that specific day isn't already closed before calling this.
     */
    public static function create(
        int $branchId,
        int $workerId,
        int $cashierId,
        float $amountMade,
        string $paymentMethod,
        array $amounts,
        float $tipAmount,
        string $note,
        string $businessDate
    ): int {
        $db = Database::connect();

        $worker = WorkerModel::find($workerId);
        $commissionRate = $worker ? (float) $worker['commission_percentage'] : 0.0;

        $workerCommission = round($amountMade * $commissionRate / 100, 2);
        $salonShare = round($amountMade - $workerCommission, 2);

        // Worker confirmation: a new record waits for the worker to Accept or
        // Appeal it. Safety net only: a worker with no login account could
        // never confirm anything, so their records skip the queue.
        $needsConfirmation = $worker && !empty($worker['user_id']);
        $confirmationStatus = $needsConfirmation ? 'pending' : 'accepted';
        $confirmationDeadline = $needsConfirmation ? date('Y-m-d', strtotime('+1 day')) : null;

        $stmt = $db->prepare(
            "INSERT INTO transactions
                (branch_id, worker_id, cashier_id, amount_made, payment_method,
                 amount_cash, amount_transfer, amount_pos,
                 commission_percentage_applied, worker_commission, salon_share,
                 note, business_date, is_locked,
                 confirmation_status, confirmation_deadline, confirmation_started_at)
             VALUES
                (:branch_id, :worker_id, :cashier_id, :amount_made, :payment_method,
                 :amount_cash, :amount_transfer, :amount_pos,
                 :commission_rate, :worker_commission, :salon_share,
                 :note, :business_date, 0,
                 :confirmation_status, :confirmation_deadline, IF(:confirmation_status_b = 'pending', NOW(), NULL))"
        );

        $stmt->execute([
            'branch_id' => $branchId,
            'worker_id' => $workerId,
            'cashier_id' => $cashierId,
            'amount_made' => $amountMade,
            'payment_method' => $paymentMethod,
            'amount_cash' => $amounts['cash'],
            'amount_transfer' => $amounts['transfer'],
            'amount_pos' => $amounts['pos'],
            'commission_rate' => $commissionRate,
            'worker_commission' => $workerCommission,
            'salon_share' => $salonShare,
            'note' => $note,
            'business_date' => $businessDate,
            'confirmation_status' => $confirmationStatus,
            'confirmation_status_b' => $confirmationStatus,
            'confirmation_deadline' => $confirmationDeadline,
        ]);

        $transactionId = (int) $db->lastInsertId();

        if ($tipAmount > 0) {
            self::addTip($transactionId, $tipAmount);
        }

        return $transactionId;
    }

    /**
     * Updates an existing sale record. Re-runs the commission calculation
     * against the worker's CURRENT rate. The WHERE clause's "AND is_locked = 0"
     * is a last-line-of-defense safety net — the controller should already
     * have checked isEditable() before ever reaching this point, but this
     * guarantees a locked row can never be silently written to even if
     * that check were ever bypassed or a bug slipped past it.
     */
    public static function update(
        int $id,
        int $workerId,
        float $amountMade,
        string $paymentMethod,
        array $amounts,
        float $tipAmount,
        string $note,
        string $revisionNote = '',
        int $actorId = 0
    ): bool {
        $db = Database::connect();

        $worker = WorkerModel::find($workerId);
        $commissionRate = $worker ? (float) $worker['commission_percentage'] : 0.0;

        $workerCommission = round($amountMade * $commissionRate / 100, 2);
        $salonShare = round($amountMade - $workerCommission, 2);

        // Remember what the record looked like, so we can tell whether this
        // edit really changed anything (and show the old amount to the worker).
        $before = self::find($id);
        $changed = $before && self::hasChanges($before, $workerId, $amountMade, $paymentMethod, $amounts, $tipAmount, $note);

        $stmt = $db->prepare(
            "UPDATE transactions
             SET worker_id = :worker_id, amount_made = :amount_made, payment_method = :payment_method,
                 amount_cash = :amount_cash, amount_transfer = :amount_transfer, amount_pos = :amount_pos,
                 commission_percentage_applied = :commission_rate, worker_commission = :worker_commission,
                 salon_share = :salon_share, note = :note, updated_at = NOW()
             WHERE id = :id AND is_locked = 0"
        );

        $result = $stmt->execute([
            'worker_id' => $workerId,
            'amount_made' => $amountMade,
            'payment_method' => $paymentMethod,
            'amount_cash' => $amounts['cash'],
            'amount_transfer' => $amounts['transfer'],
            'amount_pos' => $amounts['pos'],
            'commission_rate' => $commissionRate,
            'worker_commission' => $workerCommission,
            'salon_share' => $salonShare,
            'note' => $note,
            'id' => $id,
        ]);

        // Replace the tip: simplest correct approach is delete-then-reinsert.
        $del = $db->prepare("DELETE FROM transaction_tips WHERE transaction_id = :id");
        $del->execute(['id' => $id]);

        if ($tipAmount > 0) {
            self::addTip($id, $tipAmount);
        }

        // A real change sends the record back to the (possibly new) worker to
        // confirm again, with the cashier's note and a fresh deadline.
        if ($result && $changed && (int) $before['is_locked'] === 0) {
            self::sendBackForConfirmation($id, $before, $worker, $amountMade, trim($revisionNote), $actorId, 'edited');
        }

        return $result;
    }

    /**
     * True when an edit would actually change the record. Used by the
     * controller (to decide whether a note is required) and by update().
     */
    public static function hasChanges(array $before, int $workerId, float $amountMade, string $paymentMethod, array $amounts, float $tipAmount, string $note): bool
    {
        return (int) $before['worker_id'] !== $workerId
            || round((float) $before['amount_made'], 2) !== round($amountMade, 2)
            || (string) $before['payment_method'] !== $paymentMethod
            || round((float) $before['amount_cash'], 2) !== round((float) $amounts['cash'], 2)
            || round((float) $before['amount_transfer'], 2) !== round((float) $amounts['transfer'], 2)
            || round((float) $before['amount_pos'], 2) !== round((float) $amounts['pos'], 2)
            || round((float) $before['tip_amount'], 2) !== round($tipAmount, 2)
            || trim((string) ($before['note'] ?? '')) !== trim($note);
    }

    /**
     * Puts a record back in its worker's queue as 'pending' with a fresh
     * "end of next business day" window and a note explaining what happened.
     * If the record was appealed, the appeal is closed and filed in
     * appeal_resolutions (the permanent report). $type is 'edited' (cashier
     * changed it) or 'resolved' (Admin marked the appeal resolved).
     * The appeal count is NOT reset, which is what enforces the re-appeal limit.
     */
    private static function sendBackForConfirmation(int $id, array $before, ?array $worker, float $newAmount, string $note, int $actorId, string $type): void
    {
        $db = Database::connect();
        $needsConfirmation = $worker && !empty($worker['user_id']);

        if ($before['confirmation_status'] === 'appealed') {
            $ins = $db->prepare(
                "INSERT INTO appeal_resolutions
                    (transaction_id, resolution_type, appeal_reason, appeal_number, note, old_amount, new_amount, resolved_by)
                 VALUES (:id, :type, :reason, :num, :note, :old, :new, :by)"
            );
            $ins->execute([
                'id' => $id,
                'type' => $type,
                'reason' => $before['appeal_reason'],
                'num' => max(1, (int) $before['appeal_count']),
                'note' => $note,
                'old' => $before['amount_made'],
                'new' => $newAmount,
                'by' => $actorId ?: null,
            ]);
        }

        $upd = $db->prepare(
            "UPDATE transactions
             SET confirmation_status = :status, appeal_reason = NULL, responded_at = NULL,
                 confirmation_deadline = :deadline, confirmation_started_at = NOW(),
                 revision_note = :note, revision_by = :by, revision_old_amount = :old,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $upd->execute([
            'status' => $needsConfirmation ? 'pending' : 'accepted',
            'deadline' => $needsConfirmation ? date('Y-m-d', strtotime('+1 day')) : null,
            'note' => $note !== '' ? $note : null,
            'by' => $actorId ?: null,
            'old' => $type === 'edited' ? $before['amount_made'] : null,
            'id' => $id,
        ]);
    }

    private static function addTip(int $transactionId, float $amount): void
    {
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO transaction_tips (transaction_id, amount) VALUES (:id, :amount)");
        $stmt->execute(['id' => $transactionId, 'amount' => $amount]);
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT t.*, COALESCE(tip.amount, 0) AS tip_amount
             FROM transactions t
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             WHERE t.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * A branch's records for one specific date, newest first, with worker
     * name + tip joined in. Used both for "today's records" (the normal
     * case) and for viewing a reopened past day's records (the exception).
     */
    public static function recordsForBranchAndDate(int $branchId, string $date): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT t.*, w.full_name AS worker_name, COALESCE(tip.amount, 0) AS tip_amount
             FROM transactions t
             JOIN worker_profiles w ON w.id = t.worker_id
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             WHERE t.branch_id = :branch_id AND t.business_date = :date
             ORDER BY t.created_at DESC"
        );
        $stmt->execute(['branch_id' => $branchId, 'date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * A record is editable purely based on its real lock state — NOT
     * whether it happens to be from today. That single is_locked flag
     * now correctly covers both cases the spec describes:
     *   - Today, not yet closed: is_locked = 0 → editable (normal case).
     *   - An older day an Admin explicitly reopened: is_locked was reset
     *     to 0 by ClosureModel::reopen() → editable (the exception case).
     * Once a day is closed, every transaction in it is is_locked = 1 and
     * this returns false, regardless of date.
     */
    public static function isEditable(array $transaction): bool
    {
        return (int) $transaction['is_locked'] === 0;
    }

    /**
     * Quick totals for "today" at one branch — powers the cashier dashboard.
     * Cash/Transfer/POS totals include tips, folded in proportionally to
     * each sale's channel split — same reasoning as ReportModel::summary().
     *
     * Also returns worker_commissions_total and tips_total separately, so
     * the dashboard can show a combined "Staff Commissions + Cashback" card
     * without a second query. Note: these two are computed independently
     * of the channel totals above — the channel totals fold tips INTO the
     * channel split (for reconciliation), while tips_total here is the
     * raw sum (for display alongside commissions).
     */
    public static function summaryForBranchToday(int $branchId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT
                COUNT(t.id) AS record_count,
                COALESCE(SUM(t.amount_made), 0) AS total_revenue,
                COALESCE(SUM(t.amount_cash + COALESCE(COALESCE(tip.amount, 0) * t.amount_cash / NULLIF(t.amount_made, 0), 0)), 0) AS cash_total,
                COALESCE(SUM(t.amount_transfer + COALESCE(COALESCE(tip.amount, 0) * t.amount_transfer / NULLIF(t.amount_made, 0), 0)), 0) AS transfer_total,
                COALESCE(SUM(t.amount_pos + COALESCE(COALESCE(tip.amount, 0) * t.amount_pos / NULLIF(t.amount_made, 0), 0)), 0) AS pos_total,
                COALESCE(SUM(t.worker_commission), 0) AS worker_commissions_total,
                COALESCE(SUM(tip.amount), 0) AS tips_total
             FROM transactions t
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             WHERE t.branch_id = :branch_id AND t.business_date = CURDATE()"
        );
        $stmt->execute(['branch_id' => $branchId]);
        return $stmt->fetch();
    }

    /**
     * Count new transactions since a given timestamp
     */
    // ------------------------------------------------------------------
    // Worker confirmation (pending / accepted / appealed / expired)
    // ------------------------------------------------------------------

    /**
     * Flips this worker's overdue 'pending' records to 'expired' and writes
     * an audit entry for each. There is no cron job on XAMPP, so this runs
     * whenever the worker opens a page (cheap: one indexed query).
     *
     * A record is overdue when its deadline day has passed, OR the branch
     * has closed that deadline day since the record was created. A day that
     * was closed and later reopened still counts as closed.
     */
    public static function expireOverdueForWorker(int $workerId): int
    {
        $db = Database::connect();
        $today = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT t.id, t.amount_made
             FROM transactions t
             WHERE t.worker_id = :worker_id
               AND t.confirmation_status = 'pending'
               AND (
                    t.confirmation_deadline < :today
                    OR EXISTS (
                        SELECT 1 FROM daily_closures dc
                        WHERE dc.branch_id = t.branch_id
                          AND dc.business_date = t.confirmation_deadline
                          AND dc.closed_at >= COALESCE(t.confirmation_started_at, t.created_at)
                    )
               )"
        );
        $stmt->execute(['worker_id' => $workerId, 'today' => $today]);
        $overdue = $stmt->fetchAll();

        $upd = $db->prepare(
            "UPDATE transactions SET confirmation_status = 'expired'
             WHERE id = :id AND confirmation_status = 'pending'"
        );
        $expired = 0;
        foreach ($overdue as $row) {
            $upd->execute(['id' => $row['id']]);
            if ($upd->rowCount() === 1) {
                $expired++;
                AuditLog::record(
                    'appeal_expired',
                    "Appeal window expired for Record #{$row['id']} (₦" . number_format((float) $row['amount_made'], 2) . "). Staff can now only accept it."
                );
            }
        }
        return $expired;
    }

    /** How many records this worker still has to deal with (pending + expired). */
    public static function queueCountForWorker(int $workerId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM transactions
             WHERE worker_id = :worker_id AND confirmation_status IN ('pending','expired')"
        );
        $stmt->execute(['worker_id' => $workerId]);
        return (int) $stmt->fetchColumn();
    }

    /** The oldest record in this worker's queue (or null when the queue is clear). */
    public static function nextInQueueForWorker(int $workerId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT t.id, t.amount_made, t.note, t.created_at, t.confirmation_status,
                    t.appeal_count, t.revision_note, t.revision_old_amount,
                    COALESCE(tip.amount, 0) AS tip_amount,
                    u.full_name AS cashier_name,
                    rb.full_name AS revised_by_name, rb.role AS revised_by_role
             FROM transactions t
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             LEFT JOIN users u ON u.id = t.cashier_id
             LEFT JOIN users rb ON rb.id = t.revision_by
             WHERE t.worker_id = :worker_id AND t.confirmation_status IN ('pending','expired')
             ORDER BY COALESCE(t.confirmation_started_at, t.created_at) ASC, t.id ASC
             LIMIT 1"
        );
        $stmt->execute(['worker_id' => $workerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Worker taps ACCEPT. Works for 'pending' and 'expired'. Returns true if a row changed. */
    public static function acceptForWorker(int $id, int $workerId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE transactions
             SET confirmation_status = 'accepted', responded_at = NOW()
             WHERE id = :id AND worker_id = :worker_id
               AND confirmation_status IN ('pending','expired')"
        );
        $stmt->execute(['id' => $id, 'worker_id' => $workerId]);
        return $stmt->rowCount() === 1;
    }

    /** Worker taps a reason. Only 'pending' records can be appealed (never 'expired'). */
    public static function appealForWorker(int $id, int $workerId, string $reason): bool
    {
        if (!in_array($reason, ['wrong_amount', 'other'], true)) {
            return false;
        }
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE transactions
             SET confirmation_status = 'appealed', appeal_reason = :reason, responded_at = NOW(),
                 appeal_count = appeal_count + 1
             WHERE id = :id AND worker_id = :worker_id
               AND confirmation_status = 'pending' AND appeal_count < :max_appeals"
        );
        $stmt->execute(['reason' => $reason, 'id' => $id, 'worker_id' => $workerId, 'max_appeals' => self::MAX_APPEALS]);
        return $stmt->rowCount() === 1;
    }

    /** Trims a note and cuts it to 255 characters without splitting a multi-byte character (works without the mbstring extension). */
    public static function clipNote(string $note): string
    {
        $note = trim($note);
        return preg_match('/^.{0,255}/us', $note, $m) ? $m[0] : '';
    }

    /** Appeal + one re-appeal. After that the worker can only Accept. */
    public const MAX_APPEALS = 2;

    public static function canAppeal(array $row): bool
    {
        return $row['confirmation_status'] === 'pending' && (int) $row['appeal_count'] < self::MAX_APPEALS;
    }

    public static function appealReasonLabel(?string $reason): string
    {
        return $reason === 'wrong_amount' ? 'Wrong amount' : 'Other';
    }

    // ---- Admin side: appeals ----

    /** Appeals not resolved yet, newest first. Pass a branch id to see only that branch (cashier side). */
    public static function openAppeals(?int $branchId = null): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT t.id, t.amount_made, t.note, t.business_date, t.responded_at, t.appeal_reason, t.appeal_count, t.is_locked,
                    COALESCE(tip.amount, 0) AS tip_amount,
                    w.full_name AS worker_name, b.name AS branch_name, u.full_name AS cashier_name
             FROM transactions t
             JOIN worker_profiles w ON w.id = t.worker_id
             JOIN branches b ON b.id = t.branch_id
             LEFT JOIN users u ON u.id = t.cashier_id
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             WHERE t.confirmation_status = 'appealed'
               AND (:branch_id IS NULL OR t.branch_id = :branch_id2)
             ORDER BY t.responded_at DESC, t.id DESC"
        );
        $stmt->execute(['branch_id' => $branchId, 'branch_id2' => $branchId]);
        return $stmt->fetchAll();
    }

    public static function openAppealCount(): int
    {
        $db = Database::connect();
        return (int) $db->query("SELECT COUNT(*) FROM transactions WHERE confirmation_status = 'appealed'")->fetchColumn();
    }

    /**
     * Appeals a CASHIER can act on: first appeals at their branch. A second
     * appeal (appeal_count >= 2) is the Admin's decision.
     */
    public static function openCashierAppealCount(int $branchId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM transactions
             WHERE confirmation_status = 'appealed' AND appeal_count < :max AND branch_id = :branch_id"
        );
        $stmt->execute(['max' => self::MAX_APPEALS, 'branch_id' => $branchId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Admin marks an appeal resolved (a note is required). The record goes
     * back to the worker as 'pending' to be confirmed again, with the note
     * on their card and a fresh deadline. The closed appeal is filed in
     * appeal_resolutions.
     */
    public static function resolveAppeal(int $id, int $adminId, string $note): bool
    {
        $before = self::find($id);
        if (!$before || $before['confirmation_status'] !== 'appealed') {
            return false;
        }
        $worker = WorkerModel::find((int) $before['worker_id']);
        self::sendBackForConfirmation($id, $before, $worker, (float) $before['amount_made'], trim($note), $adminId, 'resolved');

        $db = Database::connect();
        $stmt = $db->prepare("UPDATE transactions SET appeal_resolved_by = :admin, appeal_resolved_at = NOW() WHERE id = :id");
        $stmt->execute(['admin' => $adminId, 'id' => $id]);
        return true;
    }

    /** Resolved-appeals report: every appeal that was closed in the date range, newest first. */
    public static function resolvedAppeals(string $start, string $end): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT r.*, t.business_date, w.full_name AS worker_name, b.name AS branch_name,
                    u.full_name AS resolver_name, u.role AS resolver_role
             FROM appeal_resolutions r
             JOIN transactions t ON t.id = r.transaction_id
             JOIN worker_profiles w ON w.id = t.worker_id
             JOIN branches b ON b.id = t.branch_id
             LEFT JOIN users u ON u.id = r.resolved_by
             WHERE DATE(r.created_at) BETWEEN :start AND :end
             ORDER BY r.created_at DESC, r.id DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    /** Worker history: accepted records plus ones still under review (appealed). */
    public static function historyForWorker(int $workerId, string $start, string $end): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT t.id, t.business_date, t.created_at, t.amount_made, t.worker_commission, t.note,
                    t.confirmation_status, t.appeal_reason, t.revision_note,
                    COALESCE(tip.amount, 0) AS tip_amount
             FROM transactions t
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             WHERE t.worker_id = :worker_id
               AND t.confirmation_status IN ('accepted','appealed')
               AND t.business_date BETWEEN :start AND :end
             ORDER BY t.business_date DESC, t.id DESC
             LIMIT 500"
        );
        $stmt->execute(['worker_id' => $workerId, 'start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    // Live-update change detection (used by the heartbeat endpoints).
    // This looks at updated_at, not created_at, so edits, worker
    // Accept/Appeal, Admin resolutions and day-closing all refresh the
    // dashboards, not just brand-new records. updated_at is set when a row
    // is created and again on every change. ">=" (not ">") so a change that
    // lands in the very same second as the last poll can never be missed;
    // worst case is one harmless extra refresh.
    public static function countNewSince($timestamp): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM transactions 
             WHERE updated_at >= FROM_UNIXTIME(:timestamp)"
        );
        $stmt->execute(['timestamp' => $timestamp]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    /**
     * Count new transactions since a given timestamp for a specific branch
     */
    public static function countNewSinceForBranch($timestamp, $branchId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM transactions 
             WHERE updated_at >= FROM_UNIXTIME(:timestamp) 
             AND branch_id = :branch_id"
        );
        $stmt->execute([
            'timestamp' => $timestamp,
            'branch_id' => $branchId
        ]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    /**
     * Count new transactions since a given timestamp for a specific worker
     */
    public static function countNewSinceForWorker($timestamp, $workerId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM transactions 
             WHERE updated_at >= FROM_UNIXTIME(:timestamp) 
             AND worker_id = :worker_id"
        );
        $stmt->execute([
            'timestamp' => $timestamp,
            'worker_id' => $workerId
        ]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }
}