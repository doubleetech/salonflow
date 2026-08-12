<?php

/**
 * ClosureModel
 * Owns the open/close/reopen lifecycle for a business day at one branch.
 *
 * A branch/day has no row here at all while it's just "in progress" (today,
 * never closed yet). A row with status='closed' means the cashier closed
 * it and every transaction for that branch/day is locked. A row with
 * status='reopened' means an Admin explicitly reopened it for corrections —
 * the reopen_reason/reopened_by/reopened_at columns are never cleared even
 * after it's closed again, so that history stays visible permanently.
 */
class ClosureModel
{
    public static function findByBranchAndDate(int $branchId, string $date): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT dc.*, closer.full_name AS closed_by_name, reopener.full_name AS reopened_by_name
             FROM daily_closures dc
             JOIN users closer ON closer.id = dc.closed_by
             LEFT JOIN users reopener ON reopener.id = dc.reopened_by
             WHERE dc.branch_id = :branch_id AND dc.business_date = :date LIMIT 1"
        );
        $stmt->execute(['branch_id' => $branchId, 'date' => $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function isClosed(int $branchId, string $date): bool
    {
        $row = self::findByBranchAndDate($branchId, $date);
        return $row !== null && $row['status'] === 'closed';
    }

    public static function isReopened(int $branchId, string $date): bool
    {
        $row = self::findByBranchAndDate($branchId, $date);
        return $row !== null && $row['status'] === 'reopened';
    }

    /** The most recent day (if any) currently sitting reopened for a branch. */
    public static function findReopenedForBranch(int $branchId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM daily_closures
             WHERE branch_id = :branch_id AND status = 'reopened'
             ORDER BY business_date DESC LIMIT 1"
        );
        $stmt->execute(['branch_id' => $branchId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Closes a branch/day: snapshots today's totals into daily_closures
     * (reusing ReportModel's existing math, so this can never disagree with
     * what the dashboard already showed) and locks every transaction for
     * that branch/day. Returns [success, errorMessageOrNull].
     */
    public static function close(int $branchId, string $date, int $closedByUserId): array
    {
        $existing = self::findByBranchAndDate($branchId, $date);

        if ($existing !== null && $existing['status'] === 'closed') {
            return [false, 'This day is already closed.'];
        }

        $summary = ReportModel::summary($branchId, $date, $date);
        $db = Database::connect();

        if ($existing !== null) {
            // Re-closing after a reopen — update the same row, keep the
            // reopen_reason/reopened_by/reopened_at history intact.
            $stmt = $db->prepare(
                "UPDATE daily_closures
                 SET total_revenue = :revenue, cash_total = :cash, transfer_total = :transfer,
                     pos_total = :pos, worker_commissions = :commissions, salon_earnings = :earnings,
                     tips_total = :tips, status = 'closed', closed_by = :closed_by, closed_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                'revenue' => $summary['total_revenue'],
                'cash' => $summary['cash_total'],
                'transfer' => $summary['transfer_total'],
                'pos' => $summary['pos_total'],
                'commissions' => $summary['worker_commissions'],
                'earnings' => $summary['salon_earnings'],
                'tips' => $summary['tips_total'],
                'closed_by' => $closedByUserId,
                'id' => $existing['id'],
            ]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO daily_closures
                    (branch_id, business_date, total_revenue, cash_total, transfer_total,
                     pos_total, worker_commissions, salon_earnings, tips_total, status, closed_by)
                 VALUES
                    (:branch_id, :date, :revenue, :cash, :transfer,
                     :pos, :commissions, :earnings, :tips, 'closed', :closed_by)"
            );
            $stmt->execute([
                'branch_id' => $branchId,
                'date' => $date,
                'revenue' => $summary['total_revenue'],
                'cash' => $summary['cash_total'],
                'transfer' => $summary['transfer_total'],
                'pos' => $summary['pos_total'],
                'commissions' => $summary['worker_commissions'],
                'earnings' => $summary['salon_earnings'],
                'tips' => $summary['tips_total'],
                'closed_by' => $closedByUserId,
            ]);
        }

        $lockStmt = $db->prepare(
            "UPDATE transactions SET is_locked = 1 WHERE branch_id = :branch_id AND business_date = :date"
        );
        $lockStmt->execute(['branch_id' => $branchId, 'date' => $date]);

        return [true, null];
    }

    /**
     * Reopens a previously-closed branch/day: unlocks its transactions and
     * records who reopened it and why. Returns [success, errorMessageOrNull].
     */
    public static function reopen(int $branchId, string $date, string $reason, int $reopenedByUserId): array
    {
        $existing = self::findByBranchAndDate($branchId, $date);

        if ($existing === null || $existing['status'] !== 'closed') {
            return [false, 'This day is not currently closed, so it cannot be reopened.'];
        }

        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE daily_closures
             SET status = 'reopened', reopened_by = :reopened_by, reopen_reason = :reason, reopened_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'reopened_by' => $reopenedByUserId,
            'reason' => $reason,
            'id' => $existing['id'],
        ]);

        $unlockStmt = $db->prepare(
            "UPDATE transactions SET is_locked = 0 WHERE branch_id = :branch_id AND business_date = :date"
        );
        $unlockStmt->execute(['branch_id' => $branchId, 'date' => $date]);

        return [true, null];
    }

    /** Recent closures across all branches, for the Admin's "choose a day to reopen" screen. */
    public static function recent(int $limit = 30): array
    {
        $db = Database::connect();
        $limit = max(1, min($limit, 200)); // guard against a bad/huge value ever reaching raw SQL

        $stmt = $db->prepare(
            "SELECT dc.*, b.name AS branch_name,
                    closer.full_name AS closed_by_name,
                    reopener.full_name AS reopened_by_name
             FROM daily_closures dc
             JOIN branches b ON b.id = dc.branch_id
             JOIN users closer ON closer.id = dc.closed_by
             LEFT JOIN users reopener ON reopener.id = dc.reopened_by
             ORDER BY dc.business_date DESC, dc.closed_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Closures within a date range, optionally scoped to one branch — used by the Admin Reports screen. */
    public static function forDateRange(?int $branchId, string $startDate, string $endDate): array
    {
        $db = Database::connect();

        $sql = "SELECT dc.*, b.name AS branch_name, closer.full_name AS closed_by_name
                FROM daily_closures dc
                JOIN branches b ON b.id = dc.branch_id
                JOIN users closer ON closer.id = dc.closed_by
                WHERE dc.business_date BETWEEN :start AND :end";

        $params = ['start' => $startDate, 'end' => $endDate];

        if ($branchId !== null) {
            $sql .= " AND dc.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        $sql .= " ORDER BY dc.business_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
