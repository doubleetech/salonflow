<div class="auth-screen">
    <div class="auth-card">
        <h2>Forgot Password</h2>
        <p class="field-hint">Enter your Admin email and we'll send a 6-digit code to it.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=forgot-password">
            <?php echo Csrf::field(); ?>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">

            <button type="submit" class="btn btn--primary">Send Code</button>
        </form>

        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=admin-login">&larr; Back to Login</a>
    </div>
</div>
