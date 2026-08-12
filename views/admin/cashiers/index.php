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
        <div class="content-header">
            <h1>Cashiers</h1>
            <a class="btn btn--primary btn--small" href="<?php echo APP_URL; ?>/index.php?route=admin/cashiers/create">+ Add Cashier</a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($tempPassword)): ?>
            <div class="alert alert--temp-password">
                Temporary password: <code><?php echo htmlspecialchars($tempPassword); ?></code>
                <br>Write this down now — it will not be shown again.
            </div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Working Today</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cashiers)): ?>
                    <tr><td colspan="5" class="empty-row">No cashiers yet. Add your first one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($cashiers as $cashier): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cashier['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($cashier['username']); ?></td>
                    <td>
                        <?php if ($cashier['today_branch_name']): ?>
                            <?php echo htmlspecialchars($cashier['today_branch_name']); ?>
                        <?php else: ?>
                            <span class="field-hint">Not chosen yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge--<?php echo $cashier['status'] === 'active' ? 'success' : ($cashier['status'] === 'pending_password_change' ? 'warning' : 'muted'); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $cashier['status'])); ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <a href="<?php echo APP_URL; ?>/index.php?route=admin/cashiers/edit&id=<?php echo $cashier['id']; ?>">Edit</a>

                        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/cashiers/reset-password" class="inline-form">
                            <?php echo Csrf::field(); ?>
                            <input type="hidden" name="id" value="<?php echo $cashier['id']; ?>">
                            <button type="submit" class="link-button" onclick="return confirm('Reset password for <?php echo htmlspecialchars($cashier['full_name']); ?>?');">Reset Password</button>
                        </form>

                        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/cashiers/toggle-status" class="inline-form">
                            <?php echo Csrf::field(); ?>
                            <input type="hidden" name="id" value="<?php echo $cashier['id']; ?>">
                            <button type="submit" class="link-button">
                                <?php echo $cashier['status'] === 'suspended' ? 'Reactivate' : 'Suspend'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>
