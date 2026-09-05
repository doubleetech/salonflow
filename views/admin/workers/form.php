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
        <h1><?php echo $worker ? 'Edit Staff' : 'Add Staff'; ?></h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($branches)): ?>
            <div class="alert alert--error">
                You need at least one active branch before adding staff.
                <a href="<?php echo APP_URL; ?>/index.php?route=admin/branches/create">Add a branch first</a>.
            </div>
        <?php else: ?>

        <form method="POST"
              action="<?php echo APP_URL; ?>/index.php?route=admin/workers/<?php echo $worker ? 'edit' : 'create'; ?>"
              class="panel-form">
            <?php echo Csrf::field(); ?>
            <?php if ($worker): ?>
                <input type="hidden" name="id" value="<?php echo $worker['id']; ?>">
            <?php endif; ?>

            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($worker['full_name'] ?? ''); ?>" required autofocus>

            <?php if (!$worker): ?>
                <label for="username">Username (optional)</label>
                <input type="text" id="username" name="username">
                <p class="field-hint">Leave blank if this staff member doesn't need their own login. You can enable it later from the Staff list.</p>
            <?php endif; ?>

            <label for="branch_id">Assigned Branch</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">-- Select Branch --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['id']; ?>" <?php echo (isset($worker['branch_id']) && $worker['branch_id'] == $b['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="commission_percentage">Commission Percentage</label>
            <input type="number" id="commission_percentage" name="commission_percentage" step="0.01" min="0" max="100"
                   value="<?php echo htmlspecialchars($worker['commission_percentage'] ?? '0'); ?>" required>
            <p class="field-hint">Changing this only affects future sales. Past records keep the rate that applied when they were recorded.</p>

            <label for="specialty">Specialty</label>
            <input type="text" id="specialty" name="specialty" value="<?php echo htmlspecialchars($worker['specialty'] ?? ''); ?>">

            <label for="employment_date">Employment Date</label>
            <input type="date" id="employment_date" name="employment_date" value="<?php echo htmlspecialchars($worker['employment_date'] ?? ''); ?>">

            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($worker['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn btn--primary"><?php echo $worker ? 'Save Changes' : 'Add Staff'; ?></button>
        </form>
        <?php endif; ?>

        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=admin/workers"> Back to Staff</a>
    </main>
</div>