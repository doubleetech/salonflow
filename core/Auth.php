<?php

/**
 * Auth
 * Handles login/logout and route guarding for the two roles: admin, cashier.
 * All password checks go through password_verify(); nothing is ever
 * compared in plain text.
 */
class Auth
{
    /** Attempt an admin login by email. Returns the user row on success, null on failure. */
    public static function attemptAdminLogin(string $email, string $password): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM users WHERE role = 'admin' AND email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        // Convert false to null
        if ($user === false) {
            $user = null;
        }

        return self::verifyAndLogin($user, $password);
    }

    /** Attempt a cashier login by username. Returns the user row on success, null on failure. */
    public static function attemptCashierLogin(string $username, string $password): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM users WHERE role = 'cashier' AND username = :username LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        // Convert false to null
        if ($user === false) {
            $user = null;
        }

        return self::verifyAndLogin($user, $password);
    }

    /** Attempt a worker login by username. Returns the user row on success, null on failure. */
    public static function attemptWorkerLogin(string $username, string $password): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM users WHERE role = 'worker' AND username = :username LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        // Convert false to null
        if ($user === false) {
            $user = null;
        }

        return self::verifyAndLogin($user, $password);
    }

    private static function verifyAndLogin(?array $user, string $password): ?array
    {
        if (!$user) {
            // Still run password_verify against a dummy hash so login timing
            // doesn't reveal whether the email/username exists.
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuuQWERTYUIOPASDFGHJKLZXCVBNM1234');
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        if (in_array($user['status'], ['suspended', 'inactive'], true)) {
            return null;
        }

        Session::set('user_id', $user['id']);
        Session::set('user_role', $user['role']);
        Session::set('user_name', $user['full_name']);
        // NOTE: users.branch_id is superseded for cashiers by
        // cashier_branch_assignments (they pick a branch fresh each
        // business day now). Still stored here for Admin/legacy reference,
        // but cashier-side code should never read this — use
        // BranchAssignmentModel::getForCashierToday() instead.
        Session::set('branch_id', $user['branch_id']);
        Session::set('user_status', $user['status']);

        return $user;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function isAdmin(): bool
    {
        return self::check() && self::role() === 'admin';
    }

    public static function isCashier(): bool
    {
        return self::check() && self::role() === 'cashier';
    }

    public static function isWorker(): bool
    {
        return self::check() && self::role() === 'worker';
    }

    public static function mustChangePassword(): bool
    {
        return Session::get('user_status') === 'pending_password_change';
    }

    /** Redirects away unless the current user is a logged-in admin. */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('Location: ' . APP_URL . '/index.php?route=who-are-you');
            exit;
        }
    }

    /** Redirects away unless the current user is a logged-in cashier. */
    public static function requireCashier(): void
    {
        if (!self::isCashier()) {
            header('Location: ' . APP_URL . '/index.php?route=who-are-you');
            exit;
        }
    }

    /** Redirects away unless the current user is a logged-in worker. */
    public static function requireWorker(): void
    {
        if (!self::isWorker()) {
            header('Location: ' . APP_URL . '/index.php?route=who-are-you');
            exit;
        }
    }

    /** Redirects away unless the user is logged in as either role. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/index.php?route=who-are-you');
            exit;
        }
    }
}