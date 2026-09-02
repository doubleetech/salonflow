<?php
// Filter field values always come straight from $_GET, not from the
// resolved $range — that way, even if something in the range failed to
// validate, the form still shows back exactly what the admin typed,
// instead of silently resetting (same lesson learned from the sale form).
$selectedPeriod = $_GET['period'] ?? 'daily';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedStart = $_GET['start'] ?? date('Y-m-d');
$selectedEnd = $_GET['end'] ?? date('Y-m-d');
$selectedBranch = $_GET['branch_id'] ?? '';

// Format dates for display in DD-MM-YYYY
$displayDate = DateRange::formatForDisplay($selectedDate);
$displayStart = DateRange::formatForDisplay($selectedStart);
$displayEnd = DateRange::formatForDisplay($selectedEnd);
?>
<div class="app-shell">
   <header class="topbar">
    <div class="topbar__left">
        <div class="nav-toggle" id="navToggle" aria-label="Toggle navigation" role="button" tabindex="0">
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
        </div>
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Admin</div>
    </div>
    <div class="topbar__user">
        <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(Session::get('user_name')); ?></span>
        <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </a>
    </div>
</header>
    <?php require __DIR__ . '/../../layouts/admin-nav.php'; ?>

    <main class="content">
        <h1>Reports</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="GET" action="<?php echo APP_URL; ?>/index.php" class="panel-form" id="report-filter-form">
            <div class="filter-row">
                <div>
                    <label for="period">Report Type</label>
                    <select id="period" name="period">
                        <option value="daily" <?php echo $selectedPeriod === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $selectedPeriod === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $selectedPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="custom" <?php echo $selectedPeriod === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>

                <div id="date-field" <?php echo $selectedPeriod === 'custom' ? 'hidden' : ''; ?>>
                    <label for="date">Date</label>
                    <div class="date-input-wrapper">
                        <input type="text" id="date" name="date" value="<?php echo htmlspecialchars($displayDate); ?>" placeholder="DD-MM-YYYY" class="date-input" autocomplete="off">
                        <i class="fas fa-calendar-alt date-icon"></i>
                    </div>
                    <p class="field-hint">For Weekly/Monthly, this picks which week/month.</p>
                </div>

                <div id="custom-fields" class="custom-fields" <?php echo $selectedPeriod !== 'custom' ? 'hidden' : ''; ?>>
                    <label for="start">Start Date</label>
                    <div class="date-input-wrapper">
                        <input type="text" id="start" name="start" value="<?php echo htmlspecialchars($displayStart); ?>" placeholder="DD-MM-YYYY" class="date-input" autocomplete="off">
                        <i class="fas fa-calendar-alt date-icon"></i>
                    </div>
                    <label for="end">End Date</label>
                    <div class="date-input-wrapper">
                        <input type="text" id="end" name="end" value="<?php echo htmlspecialchars($displayEnd); ?>" placeholder="DD-MM-YYYY" class="date-input" autocomplete="off">
                        <i class="fas fa-calendar-alt date-icon"></i>
                    </div>
                </div>

                <div>
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">Entire Business</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ((string) $selectedBranch === (string) $b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" name="route" value="admin/reports" class="btn btn--primary">View Report</button>
                <button type="submit" name="route" value="admin/reports/export" class="btn btn--primary btn--brass">Export PDF</button>
            </div>
        </form>

        <?php if ($summary !== null): ?>

        <h2 class="section-heading">Summary</h2>
        <div class="card-grid">
            <div class="stat-card"><span class="stat-card__label">Total Revenue</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['total_revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Cash Total</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['cash_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Transfer Total</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['transfer_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">POS Total</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['pos_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Tips</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['tips_total'], 2); ?></span></div>
            <div class="stat-card stat-card--tips"><span class="stat-card__label">Total Revenue + Tips</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['total_revenue'] + (float) $summary['tips_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Worker Commissions</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['worker_commissions'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Salon Earnings</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['salon_earnings'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Number of Sales</span><span class="stat-card__value"><?php echo (int) $summary['record_count']; ?></span></div>
        </div>

        <?php if ($branchBreakdown !== null): ?>
        <h2 class="section-heading">Branch Revenue</h2>
        <table class="data-table">
            <thead><tr><th>Branch</th><th>Sales</th><th>Revenue</th><th>Commissions</th><th>Salon Earnings</th></tr></thead>
            <tbody>
                <?php foreach ($branchBreakdown as $b): ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                    <td><?php echo (int) $b['record_count']; ?></td>
                    <td class="amount">₦<?php echo number_format((float) $b['revenue'], 2); ?></td>
                    <td class="amount">₦<?php echo number_format((float) $b['worker_commissions'], 2); ?></td>
                    <td class="amount">₦<?php echo number_format((float) $b['salon_earnings'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h2 class="section-heading">Worker Performance</h2>
        <table class="data-table">
            <thead><tr><th>Worker</th><th>Branch</th><th>Sales</th><th>Revenue</th><th>Commission</th><th>Tips</th></tr></thead>
            <tbody>
                <?php if (empty($workerPerformance)): ?>
                    <tr><td colspan="6" class="empty-row">No data for this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($workerPerformance as $w): ?>
                <tr>
                    <td><?php echo htmlspecialchars($w['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($w['branch_name']); ?></td>
                    <td><?php echo (int) $w['record_count']; ?></td>
                    <td class="amount">₦<?php echo number_format((float) $w['revenue'], 2); ?></td>
                    <td class="amount">₦<?php echo number_format((float) $w['commission'], 2); ?></td>
                    <td class="amount">₦<?php echo number_format((float) $w['tips'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="section-heading">Closures in This Period</h2>
        <table class="data-table">
            <thead><tr><th>Branch</th><th>Date</th><th>Status</th><th>Closed By</th><th>Revenue</th></tr></thead>
            <tbody>
                <?php if (empty($closures)): ?>
                    <tr><td colspan="5" class="empty-row">No days were closed in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($closures as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['business_date']); ?></td>
                    <td>
                        <span class="badge badge--<?php echo $c['status'] === 'closed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($c['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($c['closed_by_name']); ?></td>
                    <td class="amount">₦<?php echo number_format((float) $c['total_revenue'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </main>
</div>