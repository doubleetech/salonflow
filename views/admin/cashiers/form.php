<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Admin</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../../layouts/admin-nav.php'; ?>

    <main class="content content--narrow">
        <h1><?php echo $cashier ? 'Edit Cashier' : 'Add Cashier'; ?></h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST"
              action="<?php echo APP_URL; ?>/index.php?route=admin/cashiers/<?php echo $cashier ? 'edit' : 'create'; ?>"
              class="panel-form">
            <?php echo Csrf::field(); ?>
            <?php if ($cashier): ?>
                <input type="hidden" name="id" value="<?php echo $cashier['id']; ?>">
            <?php endif; ?>

            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($cashier['full_name'] ?? ''); ?>" required autofocus>

            <label for="username">Username</label>
            <?php if ($cashier): ?>
                <input type="text" value="<?php echo htmlspecialchars($cashier['username']); ?>" disabled>
                <p class="field-hint">Username can't be changed after creation.</p>
            <?php else: ?>
                <input type="text" id="username" name="username" required>
            <?php endif; ?>

            <p class="field-hint">No branch assignment needed — cashiers rotate between branches and pick which one they're working each time they log in.</p>

            <?php if (!$cashier): ?>
                <p class="field-hint">A temporary numeric password will be generated automatically — you'll see it once, right after saving.</p>
            <?php endif; ?>

            <button type="submit" class="btn btn--primary"><?php echo $cashier ? 'Save Changes' : 'Add Cashier'; ?></button>
        </form>

        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=admin/cashiers">&larr; Back to Cashiers</a>
    </main>
</div>
