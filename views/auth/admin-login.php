<div class="auth-screen">
    <div class="auth-card">
        <h2>Admin Login</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin-login">
            <?php echo Csrf::field(); ?>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit" class="btn btn--primary">Log In</button>
        </form>

        <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=forgot-password">Forgot Password?</a>
        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=who-are-you">&larr; Back</a>
    </div>
</div>
