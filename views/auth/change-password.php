<div class="auth-screen">
    <div class="auth-card">
        <h2>Set Your Password</h2>
        <p class="hint-muted">This is your first login. Choose a new password to continue.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=change-password">
            <?php echo Csrf::field(); ?>

            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autofocus>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

            <button type="submit" class="btn btn--primary">Save Password</button>
        </form>
    </div>
</div>
