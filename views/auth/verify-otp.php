<div class="auth-screen">
    <div class="auth-card">
        <h2>Enter Verification Code</h2>
        <p class="field-hint">Check your email for a 6-digit code. It expires in 10 minutes.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=verify-otp">
            <?php echo Csrf::field(); ?>

            <label for="otp_code">6-Digit Code</label>
            <input type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{6}"
                   maxlength="6" required autofocus placeholder="000000">

            <button type="submit" class="btn btn--primary">Verify Code</button>
        </form>

        <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=forgot-password">Didn't get a code? Request a new one</a>
        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=admin-login">&larr; Back to Login</a>
    </div>
</div>
