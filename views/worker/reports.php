<?php
$selectedPeriod = $_GET['period'] ?? 'daily';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedStart = $_GET['start'] ?? date('Y-m-d');
$selectedEnd = $_GET['end'] ?? date('Y-m-d');
?>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Worker</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../layouts/worker-nav.php'; ?>

    <main class="content">
        <h1>My Reports</h1>
        <p class="field-hint">Your own performance only.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="GET" action="<?php echo APP_URL; ?>/index.php" class="panel-form" id="report-filter-form">
            <input type="hidden" name="route" value="worker/reports">
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
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                    <p class="field-hint">For Weekly/Monthly, this picks which week/month.</p>
                </div>

                <div id="custom-fields" class="custom-fields" <?php echo $selectedPeriod !== 'custom' ? 'hidden' : ''; ?>>
                    <label for="start">Start Date</label>
                    <input type="date" id="start" name="start" value="<?php echo htmlspecialchars($selectedStart); ?>">
                    <label for="end">End Date</label>
                    <input type="date" id="end" name="end" value="<?php echo htmlspecialchars($selectedEnd); ?>">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">View Report</button>
            </div>
        </form>

        <?php if ($summary !== null): ?>
        <h2 class="section-heading">Summary</h2>
        <div class="card-grid">
            <div class="stat-card"><span class="stat-card__label">Sales</span><span class="stat-card__value"><?php echo (int) $summary['record_count']; ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Revenue</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Commission</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['commission'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">My Tips</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['tips'], 2); ?></span></div>
        </div>
        <?php endif; ?>
    </main>
</div>
