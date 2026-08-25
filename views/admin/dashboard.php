<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Admin</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../layouts/admin-nav.php'; ?>

    <main class="content">
        <div class="dashboard-header">
            <h1>Welcome back, <?php echo htmlspecialchars(Session::get('user_name')); ?></h1>
            <div class="heartbeat-indicator">
                <span class="live-dot"></span>
                <span class="live-text">Live</span>
                <span class="last-updated" id="lastUpdated">Just now</span>
            </div>
        </div>

        <div class="notice">
            <strong>Phase 4 of 5 complete.</strong> Real numbers, reports, and PDF export
            are all live. Business-day closures and the audit log viewer arrive in Phase 5.
        </div>

        <h2 class="section-heading">Revenue Trend</h2>
        <div class="card-grid" id="revenueCards">
            <div class="stat-card"><span class="stat-card__label">Today's Revenue</span><span class="stat-card__value" id="todayRevenue">₦<?php echo number_format((float) $todaySummary['total_revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Week-to-Date Revenue</span><span class="stat-card__value" id="weekRevenue">₦<?php echo number_format((float) $weekSummary['total_revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Month-to-Date Revenue</span><span class="stat-card__value" id="monthRevenue">₦<?php echo number_format((float) $monthSummary['total_revenue'], 2); ?></span></div>
        </div>

        <h2 class="section-heading">Revenue + Tips</h2>
        <div class="card-grid" id="revenueTipsCards">
            <div class="stat-card stat-card--tips"><span class="stat-card__label">Today's Revenue + Tips</span><span class="stat-card__value" id="todayRevenueTips">₦<?php echo number_format((float) $todaySummary['total_revenue'] + (float) $todaySummary['tips_total'], 2); ?></span></div>
            <div class="stat-card stat-card--tips"><span class="stat-card__label">Week-to-Date Revenue + Tips</span><span class="stat-card__value" id="weekRevenueTips">₦<?php echo number_format((float) $weekSummary['total_revenue'] + (float) $weekSummary['tips_total'], 2); ?></span></div>
            <div class="stat-card stat-card--tips"><span class="stat-card__label">Month-to-Date Revenue + Tips</span><span class="stat-card__value" id="monthRevenueTips">₦<?php echo number_format((float) $monthSummary['total_revenue'] + (float) $monthSummary['tips_total'], 2); ?></span></div>
        </div>

        <h2 class="section-heading">Today, at a Glance</h2>
        <div class="card-grid" id="todayGlance">
            <div class="stat-card"><span class="stat-card__label">Cash Total</span><span class="stat-card__value" id="cashTotal">₦<?php echo number_format((float) $todaySummary['cash_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Transfer Total</span><span class="stat-card__value" id="transferTotal">₦<?php echo number_format((float) $todaySummary['transfer_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">POS Total</span><span class="stat-card__value" id="posTotal">₦<?php echo number_format((float) $todaySummary['pos_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Tips</span><span class="stat-card__value" id="tipsTotal">₦<?php echo number_format((float) $todaySummary['tips_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Worker Commissions</span><span class="stat-card__value" id="commissionsTotal">₦<?php echo number_format((float) $todaySummary['worker_commissions'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Salon Earnings</span><span class="stat-card__value" id="salonEarnings">₦<?php echo number_format((float) $todaySummary['salon_earnings'], 2); ?></span></div>
        </div>

        <h2 class="section-heading">Branch Revenue — Today</h2>
        <table class="data-table">
            <thead><tr><th>Branch</th><th>Sales</th><th>Revenue</th></tr></thead>
            <tbody id="branchTableBody">
                <?php if (empty($branchBreakdown)): ?>
                    <tr><td colspan="3" class="empty-row">No branches yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($branchBreakdown as $b): ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                    <td><?php echo (int) $b['record_count']; ?></td>
                    <td class="amount">₦<?php echo number_format((float) $b['revenue'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="section-heading">Worker Performance — Today</h2>
        <table class="data-table">
            <thead><tr><th>Worker</th><th>Branch</th><th>Sales</th><th>Revenue</th><th>Commission</th><th>Tips</th></tr></thead>
            <tbody id="workerTableBody">
                <?php if (empty($workerPerformance)): ?>
                    <tr><td colspan="6" class="empty-row">No workers yet.</td></tr>
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
    </main>
</div>