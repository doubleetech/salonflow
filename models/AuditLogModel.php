<?php

/**
 * AuditLogModel
 * Read-only access to `audit_logs` for the Admin's viewer screen.
 * Nothing ever writes through this model — that's AuditLog::record()'s
 * job (core/AuditLog.php). This one only answers "show me the log."
 */
class AuditLogModel
{
    /**
     * Returns ['rows' => [...], 'total' => int] for one page of results,
     * optionally filtered by action and/or a date range (inclusive).
     */
    public static function paginated(int $page, int $perPage, ?string $action, ?string $startDate, ?string $endDate): array
    {
        $db = Database::connect();

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200)); // guard against a huge page size
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($action !== null && $action !== '') {
            $where[] = 'action = :action';
            $params['action'] = $action;
        }
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $params['start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $params['end_date'] = $endDate . ' 23:59:59';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET are bound as integers below — PDO's native prepared
        // statements (which this app uses; see Database.php) support binding
        // these directly as long as they're explicitly typed PARAM_INT.
        $sql = "SELECT * FROM audit_logs {$whereSql} ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /** Distinct actions seen so far, for the filter dropdown. */
    public static function distinctActions(): array
    {
        $db = Database::connect();
        $stmt = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
