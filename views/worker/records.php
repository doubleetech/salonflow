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
        <h1>My Records</h1>
        <!-- <p class="field-hint">Every record you have accepted.</p> -->

        <form method="GET" action="<?php echo APP_URL; ?>/index.php" style="margin-bottom:16px">
            <input type="hidden" name="route" value="worker/records">
            From <input type="date" name="start" value="<?php echo htmlspecialchars($start); ?>">
            To <input type="date" name="end" value="<?php echo htmlspecialchars($end); ?>">
            <button type="submit" class="btn btn--primary btn--small">Show</button>
        </form>

        <p>
            <strong><?php echo (int) $acceptedCount; ?></strong> accepted ·
            Amount <strong>₦<?php echo number_format($acceptedAmount, 2); ?></strong> ·
            Your share <strong>₦<?php echo number_format($acceptedShare, 2); ?></strong> ·
            Cashback <strong>₦<?php echo number_format($acceptedTips, 2); ?></strong>
            <?php if ($reviewCount > 0): ?> · <span class="badge badge--warning"><?php echo (int) $reviewCount; ?> under review</span><?php endif; ?>
        </p>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Record</th>
                        <th>Amount</th>
                        <th>Cashback</th>
                        <th>Your share</th>
                        <th>Note</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="7" class="empty-row">No records in this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($r['business_date']))); ?></td>
                            <td>#<?php echo (int) $r['id']; ?></td>
                            <td class="amount">₦<?php echo number_format((float) $r['amount_made'], 2); ?></td>
                            <td class="amount"><?php echo (float) $r['tip_amount'] > 0 ? '₦' . number_format((float) $r['tip_amount'], 2) : '—'; ?></td>
                            <td class="amount">₦<?php echo number_format((float) $r['worker_commission'], 2); ?></td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['note'] ?? '')); ?>
                                <?php if (!empty($r['revision_note'])): ?>
                                    <div class="field-hint">Update: <?php echo htmlspecialchars($r['revision_note']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['confirmation_status'] === 'appealed'): ?>
                                    <span class="badge badge--warning">Under review</span>
                                    <div class="field-hint"><?php echo htmlspecialchars(TransactionModel::appealReasonLabel($r['appeal_reason'])); ?></div>
                                <?php else: ?>
                                    <span class="badge badge--success">Accepted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
