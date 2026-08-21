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

        $stmt = $db->prepare(
            "INSERT INTO transactions
                (branch_id, worker_id, cashier_id, amount_made, payment_method,
                 amount_cash, amount_transfer, amount_pos,
                 commission_percentage_applied, worker_commission, salon_share,
                 note, business_date, is_locked)
             VALUES
                (:branch_id, :worker_id, :cashier_id, :amount_made, :payment_method,
                 :amount_cash, :amount_transfer, :amount_pos,
                 :commission_rate, :worker_commission, :salon_share,
                 :note, :business_date, 0)"
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
        string $note
    ): bool {
        $db = Database::connect();

        $worker = WorkerModel::find($workerId);
        $commissionRate = $worker ? (float) $worker['commission_percentage'] : 0.0;

        $workerCommission = round($amountMade * $commissionRate / 100, 2);
        $salonShare = round($amountMade - $workerCommission, 2);

        $stmt = $db->prepare(
            "UPDATE transactions
             SET worker_id = :worker_id, amount_made = :amount_made, payment_method = :payment_method,
                 amount_cash = :amount_cash, amount_transfer = :amount_transfer, amount_pos = :amount_pos,
                 commission_percentage_applied = :commission_rate, worker_commission = :worker_commission,
                 salon_share = :salon_share, note = :note
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

        return $result;
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
                COALESCE(SUM(t.amount_pos + COALESCE(COALESCE(tip.amount, 0) * t.amount_pos / NULLIF(t.amount_made, 0), 0)), 0) AS pos_total
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
    public static function countNewSince($timestamp): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM transactions 
             WHERE created_at > FROM_UNIXTIME(:timestamp)"
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
             WHERE created_at > FROM_UNIXTIME(:timestamp) 
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
             WHERE created_at > FROM_UNIXTIME(:timestamp) 
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