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
        
        // Convert dates from DD-MM-YYYY to Y-m-d before passing to DateRange
        // This handles the conversion for the backend processing
        if (isset($_GET['date']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $_GET['date'])) {
            $_GET['date'] = DateRange::normalizeDate($_GET['date']);
        }
        if (isset($_GET['start']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $_GET['start'])) {
            $_GET['start'] = DateRange::normalizeDate($_GET['start']);
        }
        if (isset($_GET['end']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $_GET['end'])) {
            $_GET['end'] = DateRange::normalizeDate($_GET['end']);
        }
        
        [$range, $rangeError] = DateRange::resolve($_GET);

        $summary = null;
        $error = $rangeError;

        if (!$rangeError) {
            $summary = ReportModel::workerOwnSummary($worker['id'], $range['start'], $range['end']);
        }

        // Format dates for display in DD-MM-YYYY format
        $displayDate = DateRange::formatForDisplay($_GET['date'] ?? date('Y-m-d'));
        $displayStart = DateRange::formatForDisplay($_GET['start'] ?? date('Y-m-d'));
        $displayEnd = DateRange::formatForDisplay($_GET['end'] ?? date('Y-m-d'));

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/worker/reports.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * API endpoint for heartbeat updates on worker dashboard
     * Returns fresh data without reloading the page
     */
    public function heartbeat(): void
    {
        Auth::requireWorker();
        $worker = $this->currentWorkerProfile();
        
        // Get the last update timestamp from the request
        $lastUpdate = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
        
        $today = date('Y-m-d');
        
        // Check if there are new transactions since last update for this worker
        $newTransactions = TransactionModel::countNewSinceForWorker($lastUpdate, $worker['id']);
        
        // If no new transactions, return 304 Not Modified
        if ($newTransactions === 0 && $lastUpdate > 0) {
            http_response_code(304);
            exit;
        }
        
        // Get fresh data
        $todaySummary = ReportModel::workerOwnSummary($worker['id'], $today, $today);
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        $weekSummary = ReportModel::workerOwnSummary($worker['id'], $weekStart, $today);
        $monthSummary = ReportModel::workerOwnSummary($worker['id'], $monthStart, $today);
        
        // Get current timestamp
        $currentTime = time();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'timestamp' => $currentTime,
            'data' => [
                'todaySummary' => $todaySummary,
                'weekSummary' => $weekSummary,
                'monthSummary' => $monthSummary,
            ]
        ]);
        exit;
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