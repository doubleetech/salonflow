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
        <div class="content-header">
            <h1>Workers</h1>
            <a class="btn btn--primary btn--small" href="<?php echo APP_URL; ?>/index.php?route=admin/workers/create">+ Add Worker</a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
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
                    <th>Branch</th>
                    <th>Specialty</th>
                    <th>Commission %</th>
                    <th>Status</th>
                    <th>Login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workers)): ?>
                    <tr><td colspan="7" class="empty-row">No workers yet. Add your first one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($workers as $worker): ?>
                <tr>
                    <td><?php echo htmlspecialchars($worker['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($worker['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($worker['specialty'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($worker['commission_percentage']); ?>%</td>
                    <td>
                        <span class="badge badge--<?php echo $worker['status'] === 'active' ? 'success' : 'muted'; ?>">
                            <?php echo ucfirst($worker['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($worker['login_username']): ?>
                            <?php echo htmlspecialchars($worker['login_username']); ?>
                            <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/workers/reset-password" class="inline-form">
                                <?php echo Csrf::field(); ?>
                                <input type="hidden" name="id" value="<?php echo $worker['id']; ?>">
                                <button type="submit" class="link-button" onclick="return confirm('Reset password for <?php echo htmlspecialchars($worker['full_name']); ?>?');">Reset</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/workers/enable-login" class="enable-login-form">
                                <?php echo Csrf::field(); ?>
                                <input type="hidden" name="id" value="<?php echo $worker['id']; ?>">
                                <input type="text" name="username" placeholder="username" required>
                                <button type="submit" class="link-button">Enable Login</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <a href="<?php echo APP_URL; ?>/index.php?route=admin/workers/edit&id=<?php echo $worker['id']; ?>">Edit</a>
                        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/workers/toggle-status" class="inline-form">
                            <?php echo Csrf::field(); ?>
                            <input type="hidden" name="id" value="<?php echo $worker['id']; ?>">
                            <button type="submit" class="link-button">
                                <?php echo $worker['status'] === 'active' ? 'Suspend' : 'Reactivate'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>
