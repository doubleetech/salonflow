<?php

class ReportController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Reports';
        [$range, $rangeError] = DateRange::resolve($_GET);

        $branchIdParam = $_GET['branch_id'] ?? '';
        $branchId = ($branchIdParam !== '') ? (int) $branchIdParam : null;

        $branches = BranchModel::all();
        $summary = null;
        $branchBreakdown = null;
        $workerPerformance = null;
        $closures = null;
        $error = $rangeError ?: Session::flash('report_error');

        if (!$rangeError) {
            $summary = ReportModel::summary($branchId, $range['start'], $range['end']);
            // Branch breakdown only makes sense when looking at the whole
            // business — if a single branch is already selected, showing
            // a "breakdown by branch" table of just that one branch is noise.
            if ($branchId === null) {
                $branchBreakdown = ReportModel::branchBreakdown($range['start'], $range['end']);
            }
            $workerPerformance = ReportModel::workerPerformance($range['start'], $range['end'], $branchId);
            // Which cashier closed each day in this period, and when —
            // "signed" with their name, same info as the Closures screen,
            // surfaced here too since Reports is where an Admin would look
            // for a period overview.
            $closures = ClosureModel::forDateRange($branchId, $range['start'], $range['end']);
        }

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/reports/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function exportPdf(): void
    {
        Auth::requireAdmin();

        [$range, $rangeError] = DateRange::resolve($_GET);

        if ($rangeError) {
            // Don't hand back a broken/empty PDF for a bad date range —
            // send the admin back to the report screen with the same
            // error the on-screen version would have shown.
            Session::flash('report_error', $rangeError);
            header('Location: ' . APP_URL . '/index.php?route=admin/reports');
            exit;
        }

        $branchIdParam = $_GET['branch_id'] ?? '';
        $branchId = ($branchIdParam !== '') ? (int) $branchIdParam : null;

        $business = BusinessModel::get();
        $branchName = 'Entire Business';
        if ($branchId !== null) {
            $branch = BranchModel::find($branchId);
            $branchName = $branch ? $branch['name'] : 'Entire Business';
        }

        $summary = ReportModel::summary($branchId, $range['start'], $range['end']);
        $branchBreakdown = $branchId === null ? ReportModel::branchBreakdown($range['start'], $range['end']) : null;
        $workerPerformance = ReportModel::workerPerformance($range['start'], $range['end'], $branchId);

        $pdf = self::buildPdf($business, $branchName, $range, $summary, $branchBreakdown, $workerPerformance);

        AuditLog::record('export_pdf', "Exported {$range['label']} report ({$branchName}, {$range['start']} to {$range['end']})");

        $filename = 'salonflow-report-' . $range['start'] . '-to-' . $range['end'] . '.pdf';
        $pdf->streamDownload($filename);
    }

    /**
     * Lays out the title, summary, and tables onto PDF pages.
     * Public + static so CashierController::exportPdf() can reuse this
     * exact same layout logic for a branch-scoped report instead of
     * duplicating it — a cashier's PDF is just this same method called
     * with $branchBreakdown always null (one branch, no breakdown needed).
     */
    public static function buildPdf(array $business, string $branchName, array $range, array $summary, ?array $branchBreakdown, array $workerPerformance): SimplePdf
    {
        $pdf = new SimplePdf();
        $pdf->addPage();
        $y = 50;

        $pdf->text(40, $y, $business['name'] ?? 'SalonFlow', 16, true);
        $y += 22;
        $pdf->text(40, $y, "{$range['label']} Report - {$branchName}", 12, true);
        $y += 16;
        $pdf->text(40, $y, "Period: {$range['start']} to {$range['end']}", 10);
        $y += 24;

        $pdf->text(40, $y, 'Summary', 12, true);
        $y += 6;
        $pdf->line(40, $y, 555, $y);
        $y += 16;

        $revenuePlusTips = (float) $summary['total_revenue'] + (float) $summary['tips_total'];

        $summaryRows = [
            ['Total Revenue', $summary['total_revenue']],
            ['Cash Total', $summary['cash_total']],
            ['Transfer Total', $summary['transfer_total']],
            ['POS Total', $summary['pos_total']],
            ['Tips', $summary['tips_total']],
            ['Total Revenue + Tips', $revenuePlusTips],
            ['Worker Commissions', $summary['worker_commissions']],
            ['Salon Earnings', $summary['salon_earnings']],
            ['Number of Sales', (string) $summary['record_count']],
        ];

        foreach ($summaryRows as $row) {
            [$label, $value] = $row;
            $formatted = $label === 'Number of Sales' ? $value : self::money($value);
            $pdf->text(40, $y, $label, 10);
            $pdf->text(300, $y, $formatted, 10);
            $y += 16;
        }

        $y += 12;

        if ($branchBreakdown !== null) {
            $y = self::tableSection($pdf, $y, 'Branch Revenue', ['Branch', 'Sales', 'Revenue', 'Commissions', 'Salon Earnings'], [40, 220, 300, 390, 480],
                array_map(fn($r) => [$r['name'], (string) $r['record_count'], self::money($r['revenue']), self::money($r['worker_commissions']), self::money($r['salon_earnings'])], $branchBreakdown)
            );
            $y += 12;
        }

        $y = self::tableSection($pdf, $y, 'Worker Performance', ['Worker', 'Branch', 'Sales', 'Revenue', 'Commission', 'Tips'], [40, 150, 230, 290, 380, 470],
            array_map(fn($r) => [$r['full_name'], $r['branch_name'], (string) $r['record_count'], self::money($r['revenue']), self::money($r['commission']), self::money($r['tips'])], $workerPerformance)
        );

        return $pdf;
    }

    /**
     * Draws a titled table (header row + data rows), starting a new PDF
     * page automatically if it would otherwise run off the bottom margin.
     * Returns the Y position after the table so the caller can keep stacking
     * more sections beneath it.
     */
    private static function tableSection(SimplePdf $pdf, float $y, string $title, array $columns, array $xPositions, array $rows): float
    {
        $bottomMargin = 780;

        if ($y + 50 > $bottomMargin) {
            $pdf->addPage();
            $y = 50;
        }

        $pdf->text(40, $y, $title, 12, true);
        $y += 6;
        $pdf->line(40, $y, 555, $y);
        $y += 16;

        foreach ($columns as $i => $col) {
            $pdf->text($xPositions[$i], $y, $col, 9, true);
        }
        $y += 6;
        $pdf->line(40, $y, 555, $y);
        $y += 14;

        if (empty($rows)) {
            $pdf->text(40, $y, 'No data for this period.', 9);
            $y += 16;
            return $y;
        }

        foreach ($rows as $row) {
            if ($y > $bottomMargin) {
                $pdf->addPage();
                $y = 50;
                // Repeat the header on the new page so a long table stays readable.
                foreach ($columns as $i => $col) {
                    $pdf->text($xPositions[$i], $y, $col, 9, true);
                }
                $y += 6;
                $pdf->line(40, $y, 555, $y);
                $y += 14;
            }

            foreach ($row as $i => $cell) {
                $pdf->text($xPositions[$i], $y, (string) $cell, 9);
            }
            $y += 15;
        }

        return $y;
    }

    /** Formats a number as "NGN 1,234.56" — see SimplePdf's docblock for why not "₦". */
    private static function money($value): string
    {
        return 'NGN ' . number_format((float) $value, 2);
    }
}
