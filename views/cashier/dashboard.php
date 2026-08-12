<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Cashier</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../layouts/cashier-nav.php'; ?>

    <main class="content">
        <div class="content-header">
            <h1>Welcome, <?php echo htmlspecialchars(Session::get('user_name')); ?></h1>
            <?php if (!$isTodayClosed): ?>
                <a class="btn btn--primary btn--small" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create">+ Record Sale</a>
            <?php else: ?>
                <span class="badge badge--muted">Today's Day Is Closed</span>
            <?php endif; ?>
        </div>

        <p class="field-hint">Working today at: <strong><?php echo htmlspecialchars($branch['name'] ?? 'Unknown Branch'); ?></strong></p>

        <?php if ($pendingReopen !== null): ?>
            <div class="notice">
                <strong>A past day is waiting for you.</strong>
                Your Admin reopened <?php echo htmlspecialchars($pendingReopen['business_date']); ?> for corrections.
                <a href="<?php echo APP_URL; ?>/index.php?route=cashier/sales&date=<?php echo urlencode($pendingReopen['business_date']); ?>">View and close it</a>.
            </div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="stat-card"><span class="stat-card__label">Today's Records</span><span class="stat-card__value"><?php echo (int) $summary['record_count']; ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Today's Revenue</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['total_revenue'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Cash Total</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['cash_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">Transfer Total</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['transfer_total'], 2); ?></span></div>
            <div class="stat-card"><span class="stat-card__label">POS Total</span><span class="stat-card__value">₦<?php echo number_format((float) $summary['pos_total'], 2); ?></span></div>
        </div>
    </main>
</div>
