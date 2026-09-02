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

    <main class="content content--narrow">
        <h1><?php echo $branch ? 'Edit Branch' : 'Add Branch'; ?></h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST"
              action="<?php echo APP_URL; ?>/index.php?route=admin/branches/<?php echo $branch ? 'edit' : 'create'; ?>"
              class="panel-form">
            <?php echo Csrf::field(); ?>
            <?php if ($branch): ?>
                <input type="hidden" name="id" value="<?php echo $branch['id']; ?>">
            <?php endif; ?>

            <label for="name">Branch Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($branch['name'] ?? ''); ?>" required autofocus>

            <label for="address">Address</label>
            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($branch['address'] ?? ''); ?>">

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($branch['phone'] ?? ''); ?>">

            <button type="submit" class="btn btn--primary"><?php echo $branch ? 'Save Changes' : 'Add Branch'; ?></button>
        </form>

        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=admin/branches">&larr; Back to Branches</a>
    </main>
</div>
