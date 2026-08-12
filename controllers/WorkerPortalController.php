<?php

/**
 * WorkerPortalController
 * The worker's OWN dashboard/reports — not to be confused with
 * AdminWorkerController, which is the admin-side worker management screen.
 *
 * Every query here is scoped to ONE worker_id (the logged-in worker's own),
 * via ReportModel::workerOwnSummary() — there is no code path in this
 * controller that can return another worker's numbers or salon-wide totals.
 */
class WorkerPortalController
{
    public function dashboard(): void
    {
        Auth::requireWorker();
        $this->redirectIfMustChangePassword();
        $worker = $this->currentWorkerProfile();

        $pageTitle = 'Worker Dashboard';

        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');

        $todaySummary = ReportModel::workerOwnSummary($worker['id'], $today, $today);
        $weekSummary = ReportModel::workerOwnSummary($worker['id'], $weekStart, $today);
        $monthSummary = ReportModel::workerOwnSummary($worker['id'], $monthStart, $today);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/worker/dashboard.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function reports(): void
    {
        Auth::requireWorker();
        $this->redirectIfMustChangePassword();
        $worker = $this->currentWorkerProfile();

        $pageTitle = 'My Reports';
        [$range, $rangeError] = DateRange::resolve($_GET);

        $summary = null;
        $error = $rangeError;

        if (!$rangeError) {
            $summary = ReportModel::workerOwnSummary($worker['id'], $range['start'], $range['end']);
        }

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/worker/reports.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    private function redirectIfMustChangePassword(): void
    {
        if (Auth::mustChangePassword()) {
            header('Location: ' . APP_URL . '/index.php?route=change-password');
            exit;
        }
    }

    /**
     * Looks up the worker_profiles row linked to the logged-in account.
     * This should always succeed for anyone who reached here (you can't
     * log in as role='worker' without a linked profile — see how worker
     * accounts are created in AdminWorkerController), but if it somehow
     * didn't, fail safe by logging out rather than showing a broken page.
     */
    private function currentWorkerProfile(): array
    {
        $worker = WorkerModel::findByUserId(Auth::id());

        if (!$worker) {
            Auth::logout();
            header('Location: ' . APP_URL . '/index.php?route=who-are-you');
            exit;
        }

        return $worker;
    }
}
