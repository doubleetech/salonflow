<div class="auth-screen">
    <div class="auth-card">
        <h2>Cashier Login</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=cashier-login">
            <?php echo Csrf::field(); ?>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit" class="btn btn--primary">Log In</button>
        </form>

        <p class="hint-muted">Forgot your password? Ask your Admin to reset it.</p>
        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=who-are-you">&larr; Back</a>
    </div>
</div>
