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
        <h1>Appeals</h1>
        <!-- <p class="field-hint">Records a staff member disagreed with. If the record needs fixing, reopen the day under Closures and ask the cashier to edit it: that closes the appeal automatically. If it is already correct, write a short note and mark it resolved. Either way the record goes back to the staff member to confirm again, and they see your note. A staff member can appeal a record once more after that, then can only accept.</p> -->

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
                        <th>Branch</th>
                        <th>Staff</th>
                        <th>Cashier</th>
                        <th>Amount</th>
                        <th>Cashback</th>
                        <th>Reason</th>
                        <th>Appeal</th>
                        <th>Appealed</th>
                        <th>Resolve</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appeals)): ?>
                        <tr><td colspan="11" class="empty-row">No open appeals.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($appeals as $a): ?>
                        <tr>
                            <td>#<?php echo (int) $a['id']; ?></td>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($a['business_date']))); ?></td>
                            <td><?php echo htmlspecialchars($a['branch_name']); ?></td>
                            <td><?php echo htmlspecialchars($a['worker_name']); ?></td>
                            <td><?php echo htmlspecialchars($a['cashier_name'] ?? ''); ?></td>
                            <td class="amount">₦<?php echo number_format((float) $a['amount_made'], 2); ?></td>
                            <td class="amount"><?php echo (float) $a['tip_amount'] > 0 ? '₦' . number_format((float) $a['tip_amount'], 2) : '—'; ?></td>
                            <td><span class="badge badge--warning"><?php echo htmlspecialchars(TransactionModel::appealReasonLabel($a['appeal_reason'])); ?></span></td>
                            <td><?php echo (int) $a['appeal_count'] >= 2 ? 'Re-appeal' : 'First'; ?></td>
                            <td><?php echo htmlspecialchars(date('d-m-Y g:i A', strtotime($a['responded_at']))); ?></td>
                            <td>
                                <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/appeals/resolve">
                                    <?php echo Csrf::field(); ?>
                                    <input type="hidden" name="record_id" value="<?php echo (int) $a['id']; ?>">
                                    <input type="text" name="note" required maxlength="255" placeholder="Note for staff, e.g. Amount confirmed correct" style="min-width:220px">
                                    <button type="submit" class="btn btn--primary btn--small">Mark resolved</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top:32px">Resolved appeals</h2>
        <form method="GET" action="<?php echo APP_URL; ?>/index.php" style="margin-bottom:12px">
            <input type="hidden" name="route" value="admin/appeals">
            From <input type="date" name="start" value="<?php echo htmlspecialchars($start); ?>">
            To <input type="date" name="end" value="<?php echo htmlspecialchars($end); ?>">
            <button type="submit" class="btn btn--primary btn--small">Show</button>
            <span class="field-hint" style="margin-left:8px"><?php echo count($resolved); ?> resolved in this period</span>
        </form>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Resolved</th>
                        <th>Record</th>
                        <th>Branch</th>
                        <th>Staff</th>
                        <th>Reason</th>
                        <th>Appeal</th>
                        <th>Closed by</th>
                        <th>Note</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resolved)): ?>
                        <tr><td colspan="9" class="empty-row">No resolved appeals in this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($resolved as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d-m-Y g:i A', strtotime($r['created_at']))); ?></td>
                            <td>#<?php echo (int) $r['transaction_id']; ?></td>
                            <td><?php echo htmlspecialchars($r['branch_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['worker_name']); ?></td>
                            <td><?php echo htmlspecialchars(TransactionModel::appealReasonLabel($r['appeal_reason'])); ?></td>
                            <td><?php echo (int) $r['appeal_number'] >= 2 ? 'Re-appeal' : 'First'; ?></td>
                            <td>
                                <?php if ($r['resolution_type'] === 'edited'): ?>
                                    Cashier edit<?php echo $r['resolver_name'] ? ' (' . htmlspecialchars($r['resolver_name']) . ')' : ''; ?>
                                <?php else: ?>
                                    <?php echo ($r['resolver_role'] ?? '') === 'cashier' ? 'Cashier' : 'Admin'; ?><?php echo $r['resolver_name'] ? ' (' . htmlspecialchars($r['resolver_name']) . ')' : ''; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['note']); ?></td>
                            <td class="amount">
                                <?php if ((float) $r['old_amount'] !== (float) $r['new_amount']): ?>
                                    ₦<?php echo number_format((float) $r['old_amount'], 2); ?> → ₦<?php echo number_format((float) $r['new_amount'], 2); ?>
                                <?php else: ?>
                                    ₦<?php echo number_format((float) $r['new_amount'], 2); ?> (no change)
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
