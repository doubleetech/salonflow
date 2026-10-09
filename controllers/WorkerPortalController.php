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
        $this->requireQueueCleared((int) $worker['id']);

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
        $this->requireQueueCleared((int) $worker['id']);

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
        $this->requireQueueCleared((int) $worker['id']);

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

        $filename = 'salonflow-staff-report-' . $range['start'] . '-to-' . $range['end'] . '.pdf';
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
            AND t.confirmation_status = 'accepted'
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
            ['Cashback Received', $this->money($summary['tips'])],
            ['Commission + Cashback', $this->money($summary['staff_payout'])],
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
            $addText(380, $y, "Cashback", 9, true);
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
                    $addText(380, $y, "Cashback", 9, true);
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
            "  -  Total Commission + Cashback: " . $this->money($summary['commission'] + $summary['tips']),
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

        // A new record needs confirming: tell the page to jump to the queue.
        TransactionModel::expireOverdueForWorker((int) $worker['id']);
        if (TransactionModel::queueCountForWorker((int) $worker['id']) > 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'timestamp' => time(),
                'redirect' => APP_URL . '/index.php?route=worker/confirm',
            ]);
            exit;
        }
        
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

    // ------------------------------------------------------------------
    // Record confirmation queue (Accept / Appeal)
    // ------------------------------------------------------------------

    /** The queue page: one record at a time. Redirects to the dashboard once it is clear. */
    public function confirm(): void
    {
        Auth::requireWorker();
        $this->redirectIfMustChangePassword();
        $worker = $this->currentWorkerProfile();

        TransactionModel::expireOverdueForWorker((int) $worker['id']);
        $first = TransactionModel::nextInQueueForWorker((int) $worker['id']);

        if ($first === null) {
            header('Location: ' . APP_URL . '/index.php?route=worker/dashboard');
            exit;
        }

        $pageTitle = 'Confirm Records';
        $remaining = TransactionModel::queueCountForWorker((int) $worker['id']);
        $card = $this->cardPayload($first, $remaining);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/worker/confirm.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /** ACCEPT: no confirmation step. Answers with the next record so the page can swap it in. */
    public function acceptSubmit(): void
    {
        Auth::requireWorker();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');
        $worker = $this->currentWorkerProfile();
        $workerId = (int) $worker['id'];
        $id = (int) ($_POST['record_id'] ?? 0);

        TransactionModel::expireOverdueForWorker($workerId);

        $record = TransactionModel::find($id);
        if ($record && (int) $record['worker_id'] === $workerId
            && TransactionModel::acceptForWorker($id, $workerId)) {
            AuditLog::record('worker_accept_record', "Accepted Record #{$id} (₦" . number_format((float) $record['amount_made'], 2) . ")");
        }

        $this->queueReply();
    }

    /** APPEAL: one tap on a reason. Only 'pending' records can be appealed. */
    public function appealSubmit(): void
    {
        Auth::requireWorker();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');
        $worker = $this->currentWorkerProfile();
        $workerId = (int) $worker['id'];
        $id = (int) ($_POST['record_id'] ?? 0);
        $reason = (string) ($_POST['reason'] ?? '');

        TransactionModel::expireOverdueForWorker($workerId);

        $error = null;
        $record = TransactionModel::find($id);
        if ($record && (int) $record['worker_id'] === $workerId
            && TransactionModel::appealForWorker($id, $workerId, $reason)) {
            AuditLog::record(
                'worker_appeal_record',
                "Appealed Record #{$id} (₦" . number_format((float) $record['amount_made'], 2) . "). Reason: " . TransactionModel::appealReasonLabel($reason)
            );
        } else {
            $error = 'This record can no longer be appealed.';
        }

        $this->queueReply($error);
    }

    /** Sends the next record in the queue (or "done") as JSON. Non-ajax requests just go back to the queue page. */
    private function queueReply(?string $error = null): void
    {
        $worker = $this->currentWorkerProfile();
        $workerId = (int) $worker['id'];

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if (!$isAjax) {
            header('Location: ' . APP_URL . '/index.php?route=worker/confirm');
            exit;
        }

        $next = TransactionModel::nextInQueueForWorker($workerId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $error === null,
            'error' => $error,
            'done' => $next === null,
            'redirect' => APP_URL . '/index.php?route=worker/dashboard',
            'card' => $next === null ? null : $this->cardPayload($next, TransactionModel::queueCountForWorker($workerId)),
        ]);
        exit;
    }

    /** The bits of a queue record the card needs, already formatted for display. */
    private function cardPayload(array $row, int $remaining): array
    {
        $reviser = '';
        if (!empty($row['revised_by_name']) || !empty($row['revised_by_role'])) {
            $reviser = ($row['revised_by_role'] ?? '') === 'admin' ? 'Admin' : (string) $row['revised_by_name'];
        }
        $hasOld = $row['revision_old_amount'] !== null && (float) $row['revision_old_amount'] !== (float) $row['amount_made'];

        return [
            'id' => (int) $row['id'],
            'amount' => '₦' . number_format((float) $row['amount_made'], 2),
            'tip' => (float) $row['tip_amount'] > 0 ? '₦' . number_format((float) $row['tip_amount'], 2) : '',
            'note' => (string) ($row['note'] ?? ''),
            'cashier' => (string) ($row['cashier_name'] ?? 'Cashier'),
            'time' => date('M j, g:i A', strtotime($row['created_at'])),
            'expired' => $row['confirmation_status'] === 'expired',
            'can_appeal' => TransactionModel::canAppeal($row),
            'appealed_before' => (int) $row['appeal_count'] > 0,
            'revision_note' => (string) ($row['revision_note'] ?? ''),
            'revised_by' => $reviser,
            'old_amount' => $hasOld ? '₦' . number_format((float) $row['revision_old_amount'], 2) : '',
            'remaining' => $remaining,
        ];
    }

    /** My Records: accepted records plus ones still under review (appealed). */
    public function records(): void
    {
        Auth::requireWorker();
        $this->redirectIfMustChangePassword();
        $worker = $this->currentWorkerProfile();
        $this->requireQueueCleared((int) $worker['id']);

        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) { $start = date('Y-m-01'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) { $end = date('Y-m-d'); }

        $records = TransactionModel::historyForWorker((int) $worker['id'], $start, $end);

        // Totals count accepted records only; "under review" ones are shown but not added up.
        $acceptedCount = 0; $acceptedAmount = 0.0; $acceptedShare = 0.0; $acceptedTips = 0.0; $reviewCount = 0;
        foreach ($records as $r) {
            if ($r['confirmation_status'] === 'accepted') {
                $acceptedCount++;
                $acceptedAmount += (float) $r['amount_made'];
                $acceptedShare += (float) $r['worker_commission'];
                $acceptedTips += (float) $r['tip_amount'];
            } else {
                $reviewCount++;
            }
        }

        $pageTitle = 'My Records';
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/worker/records.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Gate for the normal dashboard/reports: anything still pending or
     * expired sends the worker to the queue. There is deliberately no way around this.
     */
    private function requireQueueCleared(int $workerId): void
    {
        TransactionModel::expireOverdueForWorker($workerId);
        if (TransactionModel::queueCountForWorker($workerId) > 0) {
            header('Location: ' . APP_URL . '/index.php?route=worker/confirm');
            exit;
        }
    }

    /**
     * Formats a number as "NGN 1,234.56"
     */
    private function money($value): string
    {
        return 'NGN ' . number_format((float) $value, 2);
    }
}