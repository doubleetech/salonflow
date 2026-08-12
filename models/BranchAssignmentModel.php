<?php

/**
 * BranchAssignmentModel
 * Owns `cashier_branch_assignments` — the record of which branch a
 * cashier picked to work, for one specific calendar date. Once a row
 * exists for (cashier, date), that's locked: the UNIQUE constraint on
 * the table itself prevents a second pick for the same day, and nothing
 * in this model ever updates an existing row, only inserts new ones.
 */
class BranchAssignmentModel
{
    /** The branch a cashier picked for a given date, or null if they haven't picked yet. */
    public static function getForCashierOnDate(int $cashierId, string $date): ?int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT branch_id FROM cashier_branch_assignments WHERE cashier_id = :cashier_id AND business_date = :date LIMIT 1"
        );
        $stmt->execute(['cashier_id' => $cashierId, 'date' => $date]);
        $branchId = $stmt->fetchColumn();
        return $branchId !== false ? (int) $branchId : null;
    }

    public static function getForCashierToday(int $cashierId): ?int
    {
        return self::getForCashierOnDate($cashierId, date('Y-m-d'));
    }

    public static function hasChosenToday(int $cashierId): bool
    {
        return self::getForCashierToday($cashierId) !== null;
    }

    /**
     * Records a cashier's branch pick for today. Returns false (and picks
     * nothing) if they've already picked for today — callers should check
     * hasChosenToday() first, but this is the safety net against a race
     * (e.g. two tabs submitting at once) since the table's UNIQUE
     * constraint would reject a duplicate insert anyway.
     */
    public static function assignForToday(int $cashierId, int $branchId): bool
    {
        if (self::hasChosenToday($cashierId)) {
            return false;
        }

        $db = Database::connect();
        try {
            $stmt = $db->prepare(
                "INSERT INTO cashier_branch_assignments (cashier_id, branch_id, business_date)
                 VALUES (:cashier_id, :branch_id, CURDATE())"
            );
            return $stmt->execute(['cashier_id' => $cashierId, 'branch_id' => $branchId]);
        } catch (PDOException $e) {
            // A duplicate-key error here means the UNIQUE constraint caught
            // a race we didn't catch above — treat it the same as "already assigned".
            return false;
        }
    }
}
