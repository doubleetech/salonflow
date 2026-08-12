<?php

/**
 * WorkerModel
 * Talks to the `worker_profiles` table — profile + commission data.
 * As of this update, a worker CAN optionally have a linked login account
 * (worker_profiles.user_id -> users.id, role='worker'); that login lives
 * in the `users` table exactly like Admin/Cashier accounts do, created via
 * UserModel::createWorkerAccount(). This model only concerns itself with
 * the profile side and the link itself, not password/session handling.
 *
 * Important: updating commission_percentage here only changes what NEW
 * transactions will use going forward. Old transactions already stored
 * their own frozen commission_percentage_applied, so they're untouched.
 */
class WorkerModel
{
    public static function allWithBranch(): array
    {
        $db = Database::connect();
        $stmt = $db->query(
            "SELECT w.*, b.name AS branch_name, u.username AS login_username
             FROM worker_profiles w
             JOIN branches b ON b.id = w.branch_id
             LEFT JOIN users u ON u.id = w.user_id
             ORDER BY w.full_name ASC"
        );
        return $stmt->fetchAll();
    }

    public static function allActiveByBranch(int $branchId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM worker_profiles WHERE branch_id = :branch_id AND status = 'active' ORDER BY full_name ASC"
        );
        $stmt->execute(['branch_id' => $branchId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM worker_profiles WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Looks up a worker's profile from their OWN login account id — used by the worker portal after login. */
    public static function findByUserId(int $userId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT w.*, b.name AS branch_name FROM worker_profiles w JOIN branches b ON b.id = w.branch_id WHERE w.user_id = :user_id LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** $userId is optional — a worker can exist with no login access at all (the original spec default). */
    public static function create(
        int $branchId, string $fullName, float $commissionPercentage,
        string $specialty, ?string $employmentDate, string $notes, ?int $userId = null
    ): int {
        $db = Database::connect();
        $stmt = $db->prepare(
            "INSERT INTO worker_profiles (user_id, branch_id, full_name, commission_percentage, specialty, employment_date, notes, status)
             VALUES (:user_id, :branch_id, :full_name, :commission, :specialty, :employment_date, :notes, 'active')"
        );
        $stmt->execute([
            'user_id' => $userId,
            'branch_id' => $branchId,
            'full_name' => $fullName,
            'commission' => $commissionPercentage,
            'specialty' => $specialty,
            'employment_date' => $employmentDate ?: null,
            'notes' => $notes,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(
        int $id, int $branchId, string $fullName, float $commissionPercentage,
        string $specialty, ?string $employmentDate, string $notes
    ): bool {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE worker_profiles
             SET branch_id = :branch_id, full_name = :full_name, commission_percentage = :commission,
                 specialty = :specialty, employment_date = :employment_date, notes = :notes
             WHERE id = :id"
        );
        return $stmt->execute([
            'branch_id' => $branchId,
            'full_name' => $fullName,
            'commission' => $commissionPercentage,
            'specialty' => $specialty,
            'employment_date' => $employmentDate ?: null,
            'notes' => $notes,
            'id' => $id,
        ]);
    }

    public static function setStatus(int $id, string $status): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE worker_profiles SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Backfills login access for a worker created before this feature existed. */
    public static function linkUserAccount(int $workerId, int $userId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE worker_profiles SET user_id = :user_id WHERE id = :id");
        return $stmt->execute(['user_id' => $userId, 'id' => $workerId]);
    }
}
