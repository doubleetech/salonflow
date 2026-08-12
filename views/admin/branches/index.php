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
            <h1>Branches</h1>
            <a class="btn btn--primary btn--small" href="<?php echo APP_URL; ?>/index.php?route=admin/branches/create">+ Add Branch</a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($branches)): ?>
                    <tr><td colspan="5" class="empty-row">No branches yet. Add your first one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($branches as $branch): ?>
                <tr>
                    <td><?php echo htmlspecialchars($branch['name']); ?></td>
                    <td><?php echo htmlspecialchars($branch['address'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($branch['phone'] ?: '—'); ?></td>
                    <td>
                        <span class="badge badge--<?php echo $branch['status'] === 'active' ? 'success' : 'muted'; ?>">
                            <?php echo ucfirst($branch['status']); ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <a href="<?php echo APP_URL; ?>/index.php?route=admin/branches/edit&id=<?php echo $branch['id']; ?>">Edit</a>
                        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/branches/toggle-status" class="inline-form">
                            <?php echo Csrf::field(); ?>
                            <input type="hidden" name="id" value="<?php echo $branch['id']; ?>">
                            <button type="submit" class="link-button">
                                <?php echo $branch['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>
