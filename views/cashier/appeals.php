<div class="app-shell">
    <header class="topbar">
        <div class="topbar__left">
            <div class="nav-toggle" id="navToggle" aria-label="Toggle navigation" role="button" tabindex="0">
                <span class="nav-toggle__bar"></span>
                <span class="nav-toggle__bar"></span>
                <span class="nav-toggle__bar"></span>
            </div>
            <div class="topbar__brand"><?php echo APP_NAME; ?> · Cashier</div>
        </div>
        <div class="topbar__user">
            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </a>
        </div>
    </header>

    <?php require __DIR__ . '/../layouts/cashier-nav.php'; ?>

    <main class="content">
        <h1>Appeals</h1>
        <p class="field-hint">Records a staff member disagreed with. If the record is wrong, edit it and say what changed. If it is already correct, write a short note and confirm it. Either way it goes back to the staff member to confirm again, and they see your note. After a second appeal the Admin decides.</p>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Record</th>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Amount</th>
                        <th>Cashback</th>
                        <th>Reason</th>
                        <th>Appeal</th>
                        <th>What to do</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appeals)): ?>
                        <tr><td colspan="8" class="empty-row">No open appeals.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($appeals as $a): ?>
                        <?php $withAdmin = (int) $a['appeal_count'] >= TransactionModel::MAX_APPEALS; ?>
                        <tr>
                            <td>#<?php echo (int) $a['id']; ?></td>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($a['business_date']))); ?></td>
                            <td><?php echo htmlspecialchars($a['worker_name']); ?></td>
                            <td class="amount">₦<?php echo number_format((float) $a['amount_made'], 2); ?></td>
                            <td class="amount"><?php echo (float) $a['tip_amount'] > 0 ? '₦' . number_format((float) $a['tip_amount'], 2) : '—'; ?></td>
                            <td><span class="badge badge--warning"><?php echo htmlspecialchars(TransactionModel::appealReasonLabel($a['appeal_reason'])); ?></span></td>
                            <td><?php echo $withAdmin ? 'Second appeal' : 'First'; ?></td>
                            <td>
                                <?php if ($withAdmin): ?>
                                    <span class="field-hint">With the Admin</span>
                                <?php else: ?>
                                    <?php if ((int) $a['is_locked'] === 0): ?>
                                        <p><a class="btn btn--brass btn--small" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/edit&amp;id=<?php echo (int) $a['id']; ?>">Edit record</a></p>
                                    <?php else: ?>
                                        <p class="field-hint">That day is closed. To correct it, ask the Admin to reopen the day.</p>
                                    <?php endif; ?>
                                    <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=cashier/appeals/resolve">
                                        <?php echo Csrf::field(); ?>
                                        <input type="hidden" name="record_id" value="<?php echo (int) $a['id']; ?>">
                                        <input type="text" name="note" required maxlength="255" placeholder="Note for staff, e.g. Amount checked, it is correct" style="min-width:220px">
                                        <button type="submit" class="btn btn--primary btn--small">Confirm correct</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
