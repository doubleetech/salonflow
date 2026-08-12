<?php

class ClosureController
{
    public function reopenForm(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Business Day Closures';
        $branches = BranchModel::all();
        $recentClosures = ClosureModel::recent(30);
        $error = Session::flash('closure_error');
        $success = Session::flash('closure_success');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/closures/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function reopenSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $date = trim($_POST['business_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($branchId <= 0) {
            Session::flash('closure_error', 'Please choose a branch.');
            header('Location: ' . APP_URL . '/index.php?route=admin/closures');
            exit;
        }
        if (!$this->isValidDate($date)) {
            Session::flash('closure_error', 'Please choose a valid date.');
            header('Location: ' . APP_URL . '/index.php?route=admin/closures');
            exit;
        }
        if ($reason === '') {
            Session::flash('closure_error', 'Please enter a reason for reopening this day.');
            header('Location: ' . APP_URL . '/index.php?route=admin/closures');
            exit;
        }

        [$success, $errorMsg] = ClosureModel::reopen($branchId, $date, $reason, Auth::id());

        if (!$success) {
            Session::flash('closure_error', $errorMsg);
            header('Location: ' . APP_URL . '/index.php?route=admin/closures');
            exit;
        }

        $branch = BranchModel::find($branchId);
        $branchName = $branch ? $branch['name'] : "branch #{$branchId}";
        AuditLog::record('reopen_business_day', "Reopened {$date} for {$branchName}. Reason: {$reason}");

        Session::flash('closure_success', "Reopened {$date} for {$branchName}. The cashier can now edit that day's records and close it again when done.");
        header('Location: ' . APP_URL . '/index.php?route=admin/closures');
        exit;
    }

    private function isValidDate(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
