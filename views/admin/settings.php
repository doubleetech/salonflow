<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Admin</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../layouts/admin-nav.php'; ?>

    <main class="content content--narrow">
        <h1>Business Settings</h1>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin/settings" class="panel-form">
            <?php echo Csrf::field(); ?>

            <label for="name">Business Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($business['name'] ?? ''); ?>" required>

            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>">

            <label for="address">Address</label>
            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($business['address'] ?? ''); ?>">

            <label for="currency">Currency</label>
            <input type="text" id="currency" name="currency" value="<?php echo htmlspecialchars($business['currency'] ?? 'NGN'); ?>" maxlength="10" required>

            <button type="submit" class="btn btn--primary">Save Settings</button>
        </form>
    </main>
</div>
