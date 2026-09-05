<?php

/**
 * PasswordResetModel
 * Owns the OTP lifecycle for Admin password recovery.
 *
 * The OTP itself is hashed before storage (via password_hash(), same
 * function used for real passwords) rather than stored as plain text —
 * it's short-lived and single-use, but there's no reason to store it in
 * a readable form when hashing it costs nothing extra.
 */
class PasswordResetModel
{
    /** Generates a random 6-digit code. Not zero-padded on purpose — always exactly 6 digits, no ambiguity. */
    public static function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    /** Stores a new OTP for a user, expiring 10 minutes from now. Returns the new row's id. */
    public static function create(int $userId, string $plainOtp): int
    {
        $db = Database::connect();
        $hash = password_hash($plainOtp, PASSWORD_DEFAULT);
        
        // Debug: Log hash length to confirm it's not being truncated
        error_log("OTP Hash length: " . strlen($hash));

        $stmt = $db->prepare(
            "INSERT INTO password_resets (user_id, otp_code, expires_at, used)
             VALUES (:user_id, :otp_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)"
        );
        $stmt->execute(['user_id' => $userId, 'otp_hash' => $hash]);

        return (int) $db->lastInsertId();
    }

    /** The most recent still-valid (unused, unexpired) OTP row for a user, if any. */
    public static function findActiveForUser(int $userId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT * FROM password_resets
             WHERE user_id = :user_id AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM password_resets WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Checks a submitted code against a specific reset row's hash.
     * Re-checks used/expiry too, defensively — the caller should already
     * have filtered for that via findActiveForUser(), but a row's state
     * could theoretically change between two requests.
     */
    public static function verify(array $resetRow, string $submittedCode): bool
    {
        if ((int) $resetRow['used'] === 1) {
            return false;
        }
        if (strtotime($resetRow['expires_at']) < time()) {
            return false;
        }
        return password_verify($submittedCode, $resetRow['otp_code']);
    }

    public static function markUsed(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}