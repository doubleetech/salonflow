<?php

/**
 * AuditLog
 * One static call from anywhere in the app records an audit trail row.
 * Kept deliberately dumb — no business logic here, just "write this down".
 */
class AuditLog
{
    public static function record(string $action, string $description = ''): void
    {
        $db = Database::connect();

        $userId    = Auth::check() ? Auth::id() : null;
        $userLabel = Auth::check() ? (Session::get('user_name') . ' (' . Auth::role() . ')') : 'system';
        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $db->prepare(
            "INSERT INTO audit_logs (user_id, user_label, action, description, ip_address)
             VALUES (:user_id, :user_label, :action, :description, :ip)"
        );

        $stmt->execute([
            'user_id'     => $userId,
            'user_label'  => $userLabel,
            'action'      => $action,
            'description' => $description,
            'ip'          => $ip,
        ]);
    }
}
