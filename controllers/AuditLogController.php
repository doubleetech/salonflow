<?php

class AuditLogController
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Audit Log';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $action = $_GET['action'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';

        $result = AuditLogModel::paginated($page, self::PER_PAGE, $action ?: null, $startDate ?: null, $endDate ?: null);
        $logs = $result['rows'];
        $total = $result['total'];
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $actions = AuditLogModel::distinctActions();

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/audit-log/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }
}
