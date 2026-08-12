<?php
$isClosed = $closure !== null && $closure['status'] === 'closed';
$isReopenedView = $closure !== null && $closure['status'] === 'reopened';
// $isYesterday comes from the controller's local scope (require shares it
// automatically, same as $isToday/$viewDate) — no explicit passing needed.
$canRecordHere = ($isToday || $isYesterday) && !$isClosed;
?>
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
            <h1><?php echo $isToday ? "Today's Records" : 'Records for ' . htmlspecialchars($viewDate); ?></h1>
            <?php if ($canRecordHere): ?>
                <a class="btn btn--primary btn--small" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create<?php echo $isYesterday ? '&for=yesterday' : ''; ?>">+ Record Sale</a>
            <?php endif; ?>
        </div>

        <?php if ($isReopenedView): ?>
            <div class="notice">
                <strong>Viewing a reopened day.</strong> Your Admin reopened <?php echo htmlspecialchars($viewDate); ?>
                for corrections<?php echo !empty($closure['reopen_reason']) ? ': "' . htmlspecialchars($closure['reopen_reason']) . '"' : '.'; ?>
                Edit whatever needs fixing, then close this day again below.
                <a href="<?php echo APP_URL; ?>/index.php?route=cashier/sales">&larr; Back to today</a>
            </div>
        <?php elseif ($isYesterday): ?>
            <div class="notice">
                <strong>Viewing yesterday.</strong> You can still add sales you forgot to record, as long as yesterday isn't closed yet.
                <a href="<?php echo APP_URL; ?>/index.php?route=cashier/sales">&larr; Back to today</a>
            </div>
        <?php elseif ($pendingReopen !== null): ?>
            <div class="notice">
                <strong>A past day is waiting for you.</strong>
                Your Admin reopened <?php echo htmlspecialchars($pendingReopen['business_date']); ?> for corrections.
                <a href="<?php echo APP_URL; ?>/index.php?route=cashier/sales&date=<?php echo urlencode($pendingReopen['business_date']); ?>">View and close it</a>.
            </div>
        <?php endif; ?>

        <?php if ($isToday): ?>
            <p class="field-hint">
                Forgot to record something from yesterday?
                <a href="<?php echo APP_URL; ?>/index.php?route=cashier/sales&date=<?php echo urlencode(date('Y-m-d', strtotime('-1 day'))); ?>">View yesterday's records</a>.
            </p>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($isClosed): ?>
            <div class="closure-panel">
                <span class="badge badge--muted">Day Closed</span>
                <div class="closure-panel__grid">
                    <div><span class="field-hint">Total Revenue</span><br>₦<?php echo number_format((float) $closure['total_revenue'], 2); ?></div>
                    <div><span class="field-hint">Cash</span><br>₦<?php echo number_format((float) $closure['cash_total'], 2); ?></div>
                    <div><span class="field-hint">Transfer</span><br>₦<?php echo number_format((float) $closure['transfer_total'], 2); ?></div>
                    <div><span class="field-hint">POS</span><br>₦<?php echo number_format((float) $closure['pos_total'], 2); ?></div>
                    <div><span class="field-hint">Tips</span><br>₦<?php echo number_format((float) $closure['tips_total'], 2); ?></div>
                    <div><span class="field-hint">Salon Earnings</span><br>₦<?php echo number_format((float) $closure['salon_earnings'], 2); ?></div>
                </div>
                <p class="field-hint">
                    Closed by <strong><?php echo htmlspecialchars($closure['closed_by_name']); ?></strong>
                    on <?php echo date('M j, Y g:i A', strtotime($closure['closed_at'])); ?>.
                    Records are read-only until an Admin reopens this day.
                </p>
            </div>
        <?php elseif ($isToday || $isYesterday || $isReopenedView): ?>
            <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=cashier/sales/close" class="close-day-form"
                  onsubmit="return confirm('Close this business day? Records will become read-only until an Admin reopens it.');">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($viewDate); ?>">
                <button type="submit" class="btn btn--brass">Close Business Day</button>
            </form>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Worker</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Tip</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="6" class="empty-row">No sales recorded for this day.</td></tr>
                <?php endif; ?>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo date('g:i A', strtotime($r['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($r['worker_name']); ?></td>
                    <td class="amount">₦<?php echo number_format((float) $r['amount_made'], 2); ?></td>
                    <td>
                        <span class="badge badge--muted"><?php echo ucfirst($r['payment_method']); ?></span>
                    </td>
                    <td class="amount"><?php echo $r['tip_amount'] > 0 ? '₦' . number_format((float) $r['tip_amount'], 2) : '—'; ?></td>
                    <td class="actions-cell">
                        <?php if (TransactionModel::isEditable($r)): ?>
                            <a href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/edit&id=<?php echo $r['id']; ?>">Edit</a>
                        <?php else: ?>
                            <span class="field-hint">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>
