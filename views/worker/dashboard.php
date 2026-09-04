<div class="app-shell">
     <header class="topbar">
    <div class="topbar__left">
        <div class="nav-toggle" id="navToggle" aria-label="Toggle navigation" role="button" tabindex="0">
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
        </div>
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Staff</div>
    </div>
   <div class="topbar__user">
    <span><i class="fas fa-user-cog"></i> <?php echo htmlspecialchars(Session::get('user_name')); ?></span>
    <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">
        <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
</div>
</header>


    <?php require __DIR__ . '/../layouts/worker-nav.php'; ?>

    <main class="content">
        <h1>Welcome, <?php echo htmlspecialchars(Session::get('user_name')); ?></h1>
        <p class="field-hint">This shows your own performance only — no one else's numbers or the salon's totals.</p>

        <h2 class="section-heading">Today</h2>
        <div class="card-grid">
            <div class="stat-card"><span class="stat-card__label">Sales</span><span class="stat-card__value" id="todaySales"><?php echo (int) $todaySummary['record_count']; ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Revenue</span><span class="stat-card__value" id="todayRevenue">₦<?php echo number_format((float) $todaySummary['revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Commission</span><span class="stat-card__value" id="todayCommission">₦<?php echo number_format((float) $todaySummary['commission'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Tips</span><span class="stat-card__value" id="todayTips">₦<?php echo number_format((float) $todaySummary['tips'], 2); ?></span></div>
        </div>

        <h2 class="section-heading">Week-to-Date</h2>
        <div class="card-grid">
            <div class="stat-card"><span class="stat-card__label">Sales</span><span class="stat-card__value" id="weekSales"><?php echo (int) $weekSummary['record_count']; ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Revenue</span><span class="stat-card__value" id="weekRevenue">₦<?php echo number_format((float) $weekSummary['revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Commission</span><span class="stat-card__value" id="weekCommission">₦<?php echo number_format((float) $weekSummary['commission'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Tips</span><span class="stat-card__value" id="weekTips">₦<?php echo number_format((float) $weekSummary['tips'], 2); ?></span></div>
        </div>

        <h2 class="section-heading">Month-to-Date</h2>
        <div class="card-grid">
            <div class="stat-card"><span class="stat-card__label">Sales</span><span class="stat-card__value" id="monthSales"><?php echo (int) $monthSummary['record_count']; ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Revenue</span><span class="stat-card__value" id="monthRevenue">₦<?php echo number_format((float) $monthSummary['revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Commission</span><span class="stat-card__value" id="monthCommission">₦<?php echo number_format((float) $monthSummary['commission'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Tips</span><span class="stat-card__value" id="monthTips">₦<?php echo number_format((float) $monthSummary['tips'], 2); ?></span></div>
        </div>
    </main>
</div>
