<div class="auth-screen">
    <div class="auth-card">
        <div class="auth-logo">
            <h2><?php echo APP_NAME; ?></h2>
            <p class="hint-muted">Admin Account Setup</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=admin-signup/submit">
            <?php echo Csrf::field(); ?>

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" required autocomplete="email">
                <p class="field-hint">This will be your login email.</p>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Min 8 characters" required minlength="8" autocomplete="new-password">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility" style="display: none;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <p class="field-hint">Must be at least 8 characters long.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility" style="display: none;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Create Admin Account</button>
        </form>

        <div class="auth-footer">
            <p class="hint-muted">This is a one-time setup. After creating your account, this page will be disabled.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get ALL password wrappers on the page
    const passwordWrappers = document.querySelectorAll('.password-wrapper');
    
    passwordWrappers.forEach(function(wrapper) {
        const passwordInput = wrapper.querySelector('input[type="password"]');
        const toggleButton = wrapper.querySelector('.toggle-password');
        
        if (!passwordInput || !toggleButton) return;
        
        const eyeIcon = toggleButton.querySelector('i');
        
        // Show toggle button when user starts typing in this specific input
        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                toggleButton.style.display = 'block';
            } else {
                toggleButton.style.display = 'none';
            }
        });
        
        // Toggle password visibility for this specific input
        toggleButton.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        });
    });
});
</script>