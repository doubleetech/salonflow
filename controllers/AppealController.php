<?php

/**
 * AppealController
 * Admin-only. Lists records a worker appealed and lets the Admin mark
 * each one resolved. Appeals never block the worker; this is only the
 * Admin's follow-up screen.
 */
class AppealController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Appeals';
        $appeals = TransactionModel::openAppeals();

        // Resolved-appeals report (date range, default: last 30 days)
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $_GET['end'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) { $start = date('Y-m-d', strtotime('-30 days')); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) { $end = date('Y-m-d'); }
        $resolved = TransactionModel::resolvedAppeals($start, $end);
        $success = Session::flash('appeal_success');
        $error = Session::flash('appeal_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/appeals/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function resolve(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['record_id'] ?? 0);
        $note = TransactionModel::clipNote((string) ($_POST['note'] ?? ''));
        $record = TransactionModel::find($id);

        if ($note === '') {
            Session::flash('appeal_error', 'Please write a short note. The staff member will see it.');
        } elseif ($record && TransactionModel::resolveAppeal($id, (int) Auth::id(), $note)) {
            $reason = TransactionModel::appealReasonLabel($record['appeal_reason'] ?? null);
            AuditLog::record(
                'appeal_resolved',
                "Resolved appeal on Record #{$id} (₦" . number_format((float) $record['amount_made'], 2) . "). Staff reason was: {$reason}. Sent back to staff to confirm. Note: " . $note
            );
            Session::flash('appeal_success', "Appeal on Record #{$id} resolved and sent back to the staff member to confirm.");
        } else {
            Session::flash('appeal_error', 'That appeal was not found or is already resolved.');
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/appeals');
        exit;
    }
}
