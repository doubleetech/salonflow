<?php
$selectedPeriod = $_GET['period'] ?? 'daily';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedStart = $_GET['start'] ?? date('Y-m-d');
$selectedEnd = $_GET['end'] ?? date('Y-m-d');

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
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">View Report</button>
                <button type="submit" name="route" value="worker/reports/export" class="btn btn--primary btn--brass">Export PDF</button>
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