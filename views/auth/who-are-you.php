<div class="auth-screen">
    <div class="auth-card auth-card--wide">
        <div class="brand">
            <h1><?php echo APP_NAME; ?></h1>
            <p class="brand-sub">Your salon's digital record book</p>
        </div>

        <h2 class="auth-question">Who are you?</h2>

        <div class="role-choices">
            <a class="role-btn" href="<?php echo APP_URL; ?>/index.php?route=admin-login">
                <span class="role-btn__icon">A</span>
                <span class="role-btn__label">Admin</span>
            </a>
            <a class="role-btn" href="<?php echo APP_URL; ?>/index.php?route=cashier-login">
                <span class="role-btn__icon">C</span>
                <span class="role-btn__label">Cashier</span>
            </a>
            <a class="role-btn" href="<?php echo APP_URL; ?>/index.php?route=worker-login">
                <span class="role-btn__icon">W</span>
                <span class="role-btn__label">Worker</span>
            </a>
        </div>
    </div>
</div>
