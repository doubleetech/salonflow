<?php

/**
 * UserModel
 * All direct DB access for the `users` table — admin, cashier, and (as of
 * this update) worker login accounts all live here. Worker-specific profile
 * data (commission, specialty, etc.) stays in worker_profiles, linked via
 * worker_profiles.user_id.
 */
class UserModel
{
    public static function findById(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Used only by the Admin password-recovery flow. */
    public static function findAdminByEmail(string $email): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE role = 'admin' AND email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updatePasswordAndActivate(int $userId, string $newPasswordHash): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE users
             SET password_hash = :hash, status = 'active'
             WHERE id = :id"
        );
        return $stmt->execute([
            'hash' => $newPasswordHash,
            'id'   => $userId,
        ]);
    }

    // ------------------------------------------------------------------
    // Cashier-specific queries (cashiers are `users` rows with role='cashier')
    // ------------------------------------------------------------------

    /**
     * All cashiers, with whichever branch they've picked for TODAY (if any)
     * shown alongside — since cashiers rotate, there's no fixed branch to
     * show here anymore, only "where are they working today".
     */
    public static function allCashiersWithTodayBranch(): array
    {
        $db = Database::connect();
        $stmt = $db->query(
            "SELECT u.*, b.name AS today_branch_name
             FROM users u
             LEFT JOIN cashier_branch_assignments cba
                ON cba.cashier_id = u.id AND cba.business_date = CURDATE()
             LEFT JOIN branches b ON b.id = cba.branch_id
             WHERE u.role = 'cashier'
             ORDER BY u.full_name ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Creates a cashier account with a random numeric temporary password.
     * No branch is assigned at creation — cashiers rotate between branches
     * and pick one fresh each business day instead (see BranchAssignmentModel).
     * Returns the plain-text temp password so the Admin can hand it to
     * the cashier — it is NEVER stored anywhere in plain text, only its hash.
     */
    public static function createCashier(string $fullName, string $username): array
    {
        $tempPassword = self::generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $db = Database::connect();
        $stmt = $db->prepare(
            "INSERT INTO users (role, full_name, username, password_hash, branch_id, status)
             VALUES ('cashier', :full_name, :username, :hash, NULL, 'pending_password_change')"
        );
        $stmt->execute([
            'full_name' => $fullName,
            'username' => $username,
            'hash' => $hash,
        ]);

        return [
            'id' => (int) $db->lastInsertId(),
            'temp_password' => $tempPassword,
        ];
    }

    public static function updateCashier(int $id, string $fullName): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE users SET full_name = :full_name WHERE id = :id AND role = 'cashier'"
        );
        return $stmt->execute(['full_name' => $fullName, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // Worker-specific queries (workers are `users` rows with role='worker')
    // ------------------------------------------------------------------

    /**
     * Creates a worker's LOGIN account (role='worker') with a random numeric
     * temporary password. This is separate from their worker_profiles row —
     * the caller links the two via worker_profiles.user_id. Returns the
     * plain-text temp password, same as createCashier().
     */
    public static function createWorkerAccount(string $fullName, string $username): array
    {
        $tempPassword = self::generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $db = Database::connect();
        $stmt = $db->prepare(
            "INSERT INTO users (role, full_name, username, password_hash, branch_id, status)
             VALUES ('worker', :full_name, :username, :hash, NULL, 'pending_password_change')"
        );
        $stmt->execute([
            'full_name' => $fullName,
            'username' => $username,
            'hash' => $hash,
        ]);

        return [
            'id' => (int) $db->lastInsertId(),
            'temp_password' => $tempPassword,
        ];
    }

    // ------------------------------------------------------------------
    // Shared (both cashier + worker accounts use these)
    // ------------------------------------------------------------------

    public static function setStatus(int $id, string $status): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Admin-triggered reset: new numeric temp password, forces change on next login. */
    public static function resetToTempPassword(int $id): string
    {
        $tempPassword = self::generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE users SET password_hash = :hash, status = 'pending_password_change' WHERE id = :id"
        );
        $stmt->execute(['hash' => $hash, 'id' => $id]);

        return $tempPassword;
    }

    public static function usernameExists(string $username): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        return (bool) $stmt->fetch();
    }

    /**
     * Numeric-only temporary password (8 digits), per explicit request —
     * easier for non-technical staff to type on a phone keypad than a
     * mixed alphanumeric one. 8 digits = 90 million possible values, which
     * is plenty for a short-lived, single-use temp password that's only
     * ever handed over once and immediately replaced on first login.
     */
    private static function generateTempPassword(): string
    {
        return (string) random_int(10000000, 99999999);
    }
}
