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
     * Export worker report as PDF
     */
    public function exportPdf(): void
    {
        Auth::requireWorker();
        $this->redirectIfMustChangePassword();
        $worker = $this->currentWorkerProfile();

        // Convert dates from DD-MM-YYYY to Y-m-d before passing to DateRange
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

        if ($rangeError) {
            Session::flash('report_error', $rangeError);
            header('Location: ' . APP_URL . '/index.php?route=worker/reports');
            exit;
        }

        $business = BusinessModel::get();
        $workerName = $worker['full_name'];
        $summary = ReportModel::workerOwnSummary($worker['id'], $range['start'], $range['end']);
        
        // Get the worker's daily breakdown for the period
        $dailyBreakdown = $this->getWorkerDailyBreakdown($worker['id'], $range['start'], $range['end']);

        // Generate the PDF
        $pdf = $this->buildPdf($business, $workerName, $range, $summary, $dailyBreakdown);

        AuditLog::record('export_pdf', "Exported worker report for {$workerName} ({$range['label']}, {$range['start']} to {$range['end']})");

        $filename = 'salonflow-worker-report-' . $range['start'] . '-to-' . $range['end'] . '.pdf';
        $pdf->streamDownload($filename);
    }

    /**
     * Get daily breakdown for a worker
     */
    private function getWorkerDailyBreakdown(int $workerId, string $startDate, string $endDate): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT 
                DATE(t.business_date) as date,
                COUNT(t.id) as sales_count,
                COALESCE(SUM(t.amount_made), 0) as revenue,
                COALESCE(SUM(t.worker_commission), 0) as commission,
                COALESCE(SUM(tip.amount), 0) as tips
            FROM transactions t
            LEFT JOIN transaction_tips tip ON tip.transaction_id = t.id
            WHERE t.worker_id = :worker_id 
            AND t.business_date BETWEEN :start AND :end
            GROUP BY DATE(t.business_date)
            ORDER BY t.business_date ASC"
        );
        $stmt->execute([
            'worker_id' => $workerId,
            'start' => $startDate,
            'end' => $endDate
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Build the PDF for worker report
     */
    private function buildPdf(array $business, string $workerName, array $range, array $summary, array $dailyBreakdown): SimplePdf
    {
        $pdf = new SimplePdf();
        $pdf->addPage();
        $y = 50;
        $leftMargin = 40;
        $rightMargin = 555;

        // === HEADER SECTION ===
        // Business Name
        $pdf->text($leftMargin, $y, $business['name'] ?? 'SalonFlow', 18, true);
        $y += 22;
        
        // Report Title
        $pdf->text($leftMargin, $y, "Worker Performance Report", 14, true);
        $y += 16;
        
        // Worker Name & Period
        $pdf->text($leftMargin, $y, "Worker: {$workerName}", 11);
        $pdf->text(300, $y, "Period: {$range['label']}", 11);
        $y += 14;
        $pdf->text($leftMargin, $y, "Date Range: {$range['start']} to {$range['end']}", 10);
        $y += 8;
        $pdf->text(300, $y, "Generated: " . date('d-m-Y H:i'), 10);
        $y += 18;

        // Divider line
        $pdf->line($leftMargin, $y, $rightMargin, $y);
        $y += 18;

        // === PERFORMANCE SUMMARY ===
        $pdf->text($leftMargin, $y, "Performance Summary", 13, true);
        $y += 14; // Increased spacing
        
        // Summary in 2 columns with better alignment
        $col1 = $leftMargin;
        $col2 = 300;
        $rowHeight = 22; // Increased row height for better readability
        
        $summaryData = [
            ['Total Sales', (string) $summary['record_count']],
            ['Revenue', $this->money($summary['revenue'])],
            ['Commission Earned', $this->money($summary['commission'])],
            ['Tips Received', $this->money($summary['tips'])],
        ];
        
        foreach ($summaryData as $index => $row) {
            $col = ($index % 2 === 0) ? $col1 : $col2;
            
            if ($index % 2 === 0 && $index > 0) {
                $y += $rowHeight;
            }
            
            $pdf->text($col, $y, $row[0] . ":", 10);
            $pdf->text($col + 120, $y, $row[1], 10, true);
        }
        
        $y += $rowHeight + 20; // Increased spacing after summary

        // === DAILY BREAKDOWN TABLE ===
        if (!empty($dailyBreakdown)) {
            $pdf->text($leftMargin, $y, "Daily Performance Breakdown", 13, true);
            $y += 14; // Increased spacing
            
            // Table headers
            $pdf->text($leftMargin, $y, "Date", 9, true);
            $pdf->text(120, $y, "Sales", 9, true);
            $pdf->text(180, $y, "Revenue", 9, true);
            $pdf->text(280, $y, "Commission", 9, true);
            $pdf->text(380, $y, "Tips", 9, true);
            $pdf->text(460, $y, "Total Earned", 9, true);
            
            $y += 6;
            $pdf->line($leftMargin, $y, $rightMargin, $y);
            $y += 14; // Increased spacing after line
            
            $totalEarnings = 0;
            
            foreach ($dailyBreakdown as $day) {
                $dailyTotal = $day['commission'] + $day['tips'];
                $totalEarnings += $dailyTotal;
                
                // Format date to DD-MM-YYYY
                $displayDate = DateRange::formatForDisplay($day['date']);
                
                $pdf->text($leftMargin, $y, $displayDate, 9);
                $pdf->text(120, $y, (string) $day['sales_count'], 9);
                $pdf->text(180, $y, $this->money($day['revenue']), 9);
                $pdf->text(280, $y, $this->money($day['commission']), 9);
                $pdf->text(380, $y, $this->money($day['tips']), 9);
                $pdf->text(460, $y, $this->money($dailyTotal), 9);
                
                $y += 14;
                
                // Add new page if needed
                if ($y > 750) {
                    $pdf->addPage();
                    $y = 50;
                    
                    // Repeat table headers on new page
                    $pdf->text($leftMargin, $y, "Daily Performance Breakdown (continued)", 13, true);
                    $y += 14;
                    $pdf->text($leftMargin, $y, "Date", 9, true);
                    $pdf->text(120, $y, "Sales", 9, true);
                    $pdf->text(180, $y, "Revenue", 9, true);
                    $pdf->text(280, $y, "Commission", 9, true);
                    $pdf->text(380, $y, "Tips", 9, true);
                    $pdf->text(460, $y, "Total Earned", 9, true);
                    $y += 6;
                    $pdf->line($leftMargin, $y, $rightMargin, $y);
                    $y += 14;
                }
            }
            
            $y += 6;
            $pdf->line($leftMargin, $y, $rightMargin, $y);
            $y += 16; // Increased spacing after line
            
            // Total row
            $pdf->text($leftMargin, $y, "TOTAL EARNINGS", 10, true);
            $pdf->text(460, $y, $this->money($totalEarnings), 10, true);
            $y += 24; // Increased spacing after total
        }

        // === SUMMARY STATISTICS ===
        $pdf->text($leftMargin, $y, "Report Summary", 13, true);
        $y += 16; // Increased spacing between header and content
        
        // Use dash instead of bullet for better compatibility
        $stats = array(
            "  -  Total Days Worked: " . count($dailyBreakdown),
            "  -  Average Daily Revenue: " . (count($dailyBreakdown) > 0 ? $this->money($summary['revenue'] / count($dailyBreakdown)) : $this->money(0)),
            "  -  Total Commission + Tips: " . $this->money($summary['commission'] + $summary['tips']),
            "  -  Commission Rate: " . ($summary['revenue'] > 0 ? number_format(($summary['commission'] / $summary['revenue']) * 100, 2) : 0) . "%",
        );
        
        foreach ($stats as $stat) {
            $pdf->text($leftMargin, $y, $stat, 10);
            $y += 16; // Increased spacing between items
        }
        
        $y += 14; // Extra spacing before footer

        // === FOOTER ===
        $y = 780;
        $pdf->text($leftMargin, $y, "Generated by SalonFlow - " . ($business['name'] ?? 'SalonFlow'), 8);
        $pdf->text(480, $y, "Page 1 of 1", 8);
        $pdf->line($leftMargin, $y - 5, $rightMargin, $y - 5);

        return $pdf;
    }

    /**
     * Redirect if the user must change their password
     */
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

    /**
     * Formats a number as "NGN 1,234.56"
     */
    private function money($value): string
    {
        return 'NGN ' . number_format((float) $value, 2);
    }
}