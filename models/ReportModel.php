<?php

/**
 * ReportModel
 * All the "add these numbers up" queries live here — the dashboard and
 * the Reports screen both call into this, just with different date ranges.
 *
 * Every method takes an explicit start/end date (inclusive) so the same
 * code path handles "today", "this week", "this month", and "custom range"
 * — there's no separate logic per period, just different dates fed in.
 */
class ReportModel
{
    /**
     * Business-wide (or single-branch) totals for a date range.
     * $branchId = null means "entire business" (all branches combined).
     *
     * IMPORTANT: cash_total / transfer_total / pos_total include each sale's
     * tip, folded in proportionally to how that sale's amount was split
     * across channels. So a straight Transfer sale puts its whole tip into
     * transfer_total; a Combination sale splits the tip the same way the
     * sale itself was split. This makes cash_total + transfer_total +
     * pos_total always equal total_revenue + tips_total exactly — these
     * are meant to represent actual money movement (for reconciling against
     * a bank statement), which is why they differ from total_revenue,
     * which deliberately excludes tips (per the spec's separate "Tips" line).
     */
    public static function summary(?int $branchId, string $startDate, string $endDate): array
    {
        $db = Database::connect();

        $sql = "SELECT
                    COUNT(t.id) AS record_count,
                    COALESCE(SUM(t.amount_made), 0) AS total_revenue,
                    COALESCE(SUM(t.amount_cash + COALESCE(COALESCE(tip.amount, 0) * t.amount_cash / NULLIF(t.amount_made, 0), 0)), 0) AS cash_total,
                    COALESCE(SUM(t.amount_transfer + COALESCE(COALESCE(tip.amount, 0) * t.amount_transfer / NULLIF(t.amount_made, 0), 0)), 0) AS transfer_total,
                    COALESCE(SUM(t.amount_pos + COALESCE(COALESCE(tip.amount, 0) * t.amount_pos / NULLIF(t.amount_made, 0), 0)), 0) AS pos_total,
                    COALESCE(SUM(t.worker_commission), 0) AS worker_commissions,
                    COALESCE(SUM(t.salon_share), 0) - COALESCE(SUM(tip.amount), 0) AS salon_earnings,
                    COALESCE(SUM(tip.amount), 0) AS tips_total
                FROM transactions t
                LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
                WHERE t.business_date BETWEEN :start AND :end";

        $params = ['start' => $startDate, 'end' => $endDate];

        if ($branchId !== null) {
            $sql .= " AND t.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** Revenue broken down per branch for a date range — every branch listed, even with zero sales. */
    public static function branchBreakdown(string $startDate, string $endDate): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT
                b.id, b.name,
                COUNT(t.id) AS record_count,
                COALESCE(SUM(t.amount_made), 0) AS revenue,
                COALESCE(SUM(t.worker_commission), 0) AS worker_commissions,
                COALESCE(SUM(t.salon_share), 0) - COALESCE(SUM(tip.amount), 0) AS salon_earnings
             FROM branches b
             LEFT JOIN transactions t
                ON t.branch_id = b.id AND t.business_date BETWEEN :start AND :end
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             GROUP BY b.id, b.name
             ORDER BY b.name ASC"
        );
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        return $stmt->fetchAll();
    }

    /** Per-worker performance for a date range, optionally scoped to one branch. */
    public static function workerPerformance(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $db = Database::connect();

        // Note on the join order: transactions is LEFT JOINed to worker_profiles
        // (so workers with zero sales still appear), and transaction_tips is
        // LEFT JOINed to transactions. Since each transaction has at most one
        // tip row, this can't inflate the row count or double-count anything —
        // safe to SUM() directly without DISTINCT.
        $sql = "SELECT
                    w.id, w.full_name, b.name AS branch_name,
                    COUNT(t.id) AS record_count,
                    COALESCE(SUM(t.amount_made), 0) AS revenue,
                    COALESCE(SUM(t.worker_commission), 0) AS commission,
                    COALESCE(SUM(tip.amount), 0) AS tips
                FROM worker_profiles w
                JOIN branches b ON b.id = w.branch_id
                LEFT JOIN transactions t
                    ON t.worker_id = w.id AND t.business_date BETWEEN :start AND :end
                LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id";

        $params = ['start' => $startDate, 'end' => $endDate];

        if ($branchId !== null) {
            $sql .= " WHERE w.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        $sql .= " GROUP BY w.id, w.full_name, b.name ORDER BY revenue DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * One specific worker's own numbers for a date range — nothing about
     * any other worker, no branch totals, no salon-wide figures. Powers
     * the worker portal's dashboard and reports (workers can only ever
     * see their own performance, per the spec for that feature).
     */
    public static function workerOwnSummary(int $workerId, string $startDate, string $endDate): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT
                COUNT(t.id) AS record_count,
                COALESCE(SUM(t.amount_made), 0) AS revenue,
                COALESCE(SUM(t.worker_commission), 0) AS commission,
                COALESCE(SUM(tip.amount), 0) AS tips
             FROM transactions t
             LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
             WHERE t.worker_id = :worker_id AND t.business_date BETWEEN :start AND :end"
        );
        $stmt->execute(['worker_id' => $workerId, 'start' => $startDate, 'end' => $endDate]);
        return $stmt->fetch();
    }
}
