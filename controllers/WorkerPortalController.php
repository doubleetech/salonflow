<?php

/**
 * WorkerPortalController
 * The staff's OWN dashboard/reports — not to be confused with
 * AdminWorkerController, which is the admin-side staff management screen.
 *
 * Every query here is scoped to ONE worker_id (the logged-in staff's own),
 * via ReportModel::workerOwnSummary() — there is no code path in this
 * controller that can return another staff's numbers or salon-wide totals.
 */
class WorkerPortalController
{
    public function dashboard(): void
    {
        Auth::requireWorker();
        $this->redirectIfMustChangePassword();
        $worker = $this->currentWorkerProfile();

        $pageTitle = 'Staff Dashboard';

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
     * Export staff report as PDF
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
        
        // Get the staff's daily breakdown for the period
        $dailyBreakdown = $this->getWorkerDailyBreakdown($worker['id'], $range['start'], $range['end']);

        // Generate the PDF
        $pdf = $this->buildPdf($business, $workerName, $range, $summary, $dailyBreakdown);

        AuditLog::record('export_pdf', "Exported staff report for {$workerName} ({$range['label']}, {$range['start']} to {$range['end']})");

        $filename = 'salonflow-worker-report-' . $range['start'] . '-to-' . $range['end'] . '.pdf';
        $pdf->streamDownload($filename);
    }

    /**
     * Get daily breakdown for a staff
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
     * Build the PDF for staff report.
     *
     * This works in two passes rather than drawing directly onto a
     * SimplePdf as it goes:
     *   1. RECORD every text/line/page-break as a plain array of commands,
     *      tagged with which page (0-indexed) each belongs to. Nothing is
     *      written to a real PDF yet, so this pass costs nothing but a
     *      little memory, and by the end we know the TOTAL page count.
     *   2. REPLAY those commands onto a real SimplePdf, adding a page
     *      break whenever the command's page index changes, and printing
     *      an accurate "Page X of N" footer on every page as we go —
     *      something that's only possible because N is already known.
     * The previous version drew directly onto the PDF as it went, so it
     * had no way to know the eventual total and just hardcoded "Page 1 of 1"
     * — wrong on any report long enough to need more than one page, and
     * only ever printed on the last page at that.
     */
    private function buildPdf(array $business, string $workerName, array $range, array $summary, array $dailyBreakdown): SimplePdf
    {
        $leftMargin = 40;
        $rightMargin = 555;
        $bottomLimit = 750; // same threshold the daily-breakdown loop already used

        $commands = [];
        $pageIndex = 0;

        $addText = function (float $x, float $y, string $text, float $size = 10, bool $bold = false) use (&$commands, &$pageIndex) {
            $commands[] = ['text', $pageIndex, $x, $y, $text, $size, $bold];
        };
        $addLine = function (float $x1, float $y, float $x2) use (&$commands, &$pageIndex) {
            $commands[] = ['line', $pageIndex, $x1, $y, $x2, $y];
        };
        $newPage = function () use (&$pageIndex) {
            $pageIndex++;
        };

        $y = 50;

        // === HEADER SECTION ===
        $addText($leftMargin, $y, $business['name'] ?? 'SalonFlow', 18, true);
        $y += 22;

        $addText($leftMargin, $y, "Staff Performance Report", 14, true);
        $y += 16;

        $addText($leftMargin, $y, "Staff: {$workerName}", 11);
        $addText(300, $y, "Period: {$range['label']}", 11);
        $y += 14;
        $addText($leftMargin, $y, "Date Range: {$range['start']} to {$range['end']}", 10);
        $y += 8;
        $addText(300, $y, "Generated: " . date('d-m-Y H:i'), 10);
        $y += 18;

        $addLine($leftMargin, $y, $rightMargin);
        $y += 18;

        // === PERFORMANCE SUMMARY ===
        $addText($leftMargin, $y, "Performance Summary", 13, true);
        $y += 14;

        $col1 = $leftMargin;
        $col2 = 300;
        $rowHeight = 22;

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

            $addText($col, $y, $row[0] . ":", 10);
            $addText($col + 120, $y, $row[1], 10, true);
        }

        $y += $rowHeight + 20;

        // === DAILY BREAKDOWN TABLE ===
        if (!empty($dailyBreakdown)) {
            $addText($leftMargin, $y, "Daily Performance Breakdown", 13, true);
            $y += 14;

            $addText($leftMargin, $y, "Date", 9, true);
            $addText(120, $y, "Sales", 9, true);
            $addText(180, $y, "Revenue", 9, true);
            $addText(280, $y, "Commission", 9, true);
            $addText(380, $y, "Tips", 9, true);
            $addText(460, $y, "Total Earned", 9, true);

            $y += 6;
            $addLine($leftMargin, $y, $rightMargin);
            $y += 14;

            $totalEarnings = 0;

            foreach ($dailyBreakdown as $day) {
                $dailyTotal = $day['commission'] + $day['tips'];
                $totalEarnings += $dailyTotal;

                $displayDate = DateRange::formatForDisplay($day['date']);

                $addText($leftMargin, $y, $displayDate, 9);
                $addText(120, $y, (string) $day['sales_count'], 9);
                $addText(180, $y, $this->money($day['revenue']), 9);
                $addText(280, $y, $this->money($day['commission']), 9);
                $addText(380, $y, $this->money($day['tips']), 9);
                $addText(460, $y, $this->money($dailyTotal), 9);

                $y += 14;

                if ($y > $bottomLimit) {
                    $newPage();
                    $y = 50;

                    $addText($leftMargin, $y, "Daily Performance Breakdown (continued)", 13, true);
                    $y += 14;
                    $addText($leftMargin, $y, "Date", 9, true);
                    $addText(120, $y, "Sales", 9, true);
                    $addText(180, $y, "Revenue", 9, true);
                    $addText(280, $y, "Commission", 9, true);
                    $addText(380, $y, "Tips", 9, true);
                    $addText(460, $y, "Total Earned", 9, true);
                    $y += 6;
                    $addLine($leftMargin, $y, $rightMargin);
                    $y += 14;
                }
            }

            $y += 6;
            $addLine($leftMargin, $y, $rightMargin);
            $y += 16;

            $addText($leftMargin, $y, "TOTAL EARNINGS", 10, true);
            $addText(460, $y, $this->money($totalEarnings), 10, true);
            $y += 24;
        }

        // === SUMMARY STATISTICS ===
        // Safety check the original never had: the Report Summary block
        // (heading + up to 4 lines) needs about 80pt. If the daily
        // breakdown left us too close to the bottom margin, start a fresh
        // page instead of risking this section overlapping the footer or
        // running past the visible page area.
        $stats = [
            "  -  Total Days Worked: " . count($dailyBreakdown),
            "  -  Average Daily Revenue: " . (count($dailyBreakdown) > 0 ? $this->money($summary['revenue'] / count($dailyBreakdown)) : $this->money(0)),
            "  -  Total Commission + Tips: " . $this->money($summary['commission'] + $summary['tips']),
            "  -  Commission Rate: " . ($summary['revenue'] > 0 ? number_format(($summary['commission'] / $summary['revenue']) * 100, 2) : 0) . "%",
        ];
        $summaryBlockHeight = 16 + (count($stats) * 16);

        if ($y + $summaryBlockHeight > $bottomLimit) {
            $newPage();
            $y = 50;
        }

        $addText($leftMargin, $y, "Report Summary", 13, true);
        $y += 16;

        foreach ($stats as $stat) {
            $addText($leftMargin, $y, $stat, 10);
            $y += 16;
        }

        // Total page count is now known — every page index seen while
        // recording, 0-based, plus the one we're currently on.
        $totalPages = $pageIndex + 1;

        // === REPLAY: build the real PDF now, with an accurate footer ===
        $pdf = new SimplePdf();
        $pdf->addPage();
        $renderedPage = 0;
        $footerY = 780;

        $drawFooter = function (int $pageNumber) use ($pdf, $leftMargin, $rightMargin, $business, $totalPages, $footerY) {
            $pdf->line($leftMargin, $footerY - 5, $rightMargin, $footerY - 5);
            $pdf->text($leftMargin, $footerY, "Generated by SalonFlow - " . ($business['name'] ?? 'SalonFlow'), 8);
            $pdf->text(480, $footerY, "Page {$pageNumber} of {$totalPages}", 8);
        };

        foreach ($commands as $cmd) {
            [$type, $cmdPageIndex] = $cmd;

            if ($cmdPageIndex !== $renderedPage) {
                $drawFooter($renderedPage + 1);
                $pdf->addPage();
                $renderedPage = $cmdPageIndex;
            }

            if ($type === 'text') {
                [, , $x, $textY, $text, $size, $bold] = $cmd;
                $pdf->text($x, $textY, $text, $size, $bold);
            } else {
                [, , $x1, $lineY, $x2] = $cmd;
                $pdf->line($x1, $lineY, $x2, $lineY);
            }
        }

        $drawFooter($renderedPage + 1);

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
     * log in as role='worker' without a linked profile — see how staff
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
     * API endpoint for heartbeat updates on staff dashboard
     * Returns fresh data without reloading the page
     */
    public function heartbeat(): void
    {
        Auth::requireWorker();
        $worker = $this->currentWorkerProfile();
        
        // Get the last update timestamp from the request
        $lastUpdate = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
        
        $today = date('Y-m-d');
        
        // Check if there are new transactions since last update for this staff
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