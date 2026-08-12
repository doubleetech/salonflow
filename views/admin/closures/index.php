<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Admin</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../../layouts/admin-nav.php'; ?>

    <main class="content">
        <h1>Business Day Closures</h1>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h2 class="section-heading">Reopen a Closed Day</h2>
        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/closures/reopen" class="panel-form">
            <?php echo Csrf::field(); ?>

            <div class="filter-row">
                <div>
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id" required>
                        <option value="">-- Select Branch --</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="business_date">Day to Reopen</label>
                    <input type="date" id="business_date" name="business_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <label for="reason">Reason for Reopening</label>
            <textarea id="reason" name="reason" rows="2" required placeholder="e.g. Cashier recorded a sale under the wrong worker"></textarea>

            <button type="submit" class="btn btn--primary">Reopen This Day</button>
            <p class="field-hint">The cashier at that branch will then be able to edit that day's records and close it again when done.</p>
        </form>

        <h2 class="section-heading">Recent Closures</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Revenue</th>
                    <th>Closed By</th>
                    <th>Reopen Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentClosures)): ?>
                    <tr><td colspan="6" class="empty-row">No days have been closed yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentClosures as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['business_date']); ?></td>
                    <td>
                        <span class="badge badge--<?php echo $c['status'] === 'closed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($c['status']); ?>
                        </span>
                    </td>
                    <td class="amount">₦<?php echo number_format((float) $c['total_revenue'], 2); ?></td>
                    <td><?php echo htmlspecialchars($c['closed_by_name']); ?></td>
                    <td>
                        <?php if ($c['status'] === 'reopened' && !empty($c['reopen_reason'])): ?>
                            <?php echo htmlspecialchars($c['reopen_reason']); ?>
                            <span class="field-hint">— by <?php echo htmlspecialchars($c['reopened_by_name']); ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>
