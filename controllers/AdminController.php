<?php

class AdminController
{
    public function dashboard(): void
    {
        Auth::requireAdmin();

        if (Auth::mustChangePassword()) {
            header('Location: ' . APP_URL . '/index.php?route=change-password');
            exit;
        }

        $pageTitle = 'Admin Dashboard';

        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');

        // Headline figures: today's is the full detail breakdown (matches
        // the spec's list of dashboard stats); week/month are just the
        // revenue trendline, "so far" as of today — not the full calendar
        // week/month, since it isn't over yet. The Reports screen (Phase 4's
        // other half) is where a full completed week/month can be pulled.
        $todaySummary = ReportModel::summary(null, $today, $today);
        $weekSummary = ReportModel::summary(null, $weekStart, $today);
        $monthSummary = ReportModel::summary(null, $monthStart, $today);

        $branchBreakdown = ReportModel::branchBreakdown($today, $today);
        $workerPerformance = ReportModel::workerPerformance($today, $today);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }
}
