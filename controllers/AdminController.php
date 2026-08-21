<?php

class AdminController
{
    public function dashboard(): void
    {
        Auth::requireAdmin();
        
        $pageTitle = 'Admin Dashboard';
        
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        
        $todaySummary = ReportModel::summary(null, $today, $today);
        $weekSummary = ReportModel::summary(null, $weekStart, $today);
        $monthSummary = ReportModel::summary(null, $monthStart, $today);
        $branchBreakdown = ReportModel::branchBreakdown($today, $today);
        $workerPerformance = ReportModel::workerPerformance($today, $today);
        
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }
    
    /**
     * API endpoint for heartbeat updates
     * Returns fresh data for all admin pages
     */
    public function heartbeat(): void
    {
        Auth::requireAdmin();
        
        // Get the last update timestamp from the request
        $lastUpdate = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
        
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        
        // Check if there are new transactions since last update
        $newTransactions = TransactionModel::countNewSince($lastUpdate);
        
        // If no new transactions, return 304 Not Modified
        if ($newTransactions === 0 && $lastUpdate > 0) {
            http_response_code(304);
            exit;
        }
        
        // Get fresh data
        $todaySummary = ReportModel::summary(null, $today, $today);
        $weekSummary = ReportModel::summary(null, $weekStart, $today);
        $monthSummary = ReportModel::summary(null, $monthStart, $today);
        $branchBreakdown = ReportModel::branchBreakdown($today, $today);
        $workerPerformance = ReportModel::workerPerformance($today, $today);
        
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
                'branchBreakdown' => $branchBreakdown,
                'workerPerformance' => $workerPerformance,
            ]
        ]);
        exit;
    }
}