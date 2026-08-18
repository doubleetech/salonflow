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
            <div class="password-wrapper">
                <input type="password" id="new_password" name="new_password" required minlength="8">
                <button type="button" class="toggle-password" aria-label="Toggle password visibility" style="display: none;">
                    <i class="fas fa-eye"></i>
                </button>

            <label for="confirm_password">Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                <button type="button" class="toggle-password" aria-label="Toggle password visibility" style="display: none;">
                    <i class="fas fa-eye"></i>
                </button>

            <button type="submit" class="btn btn--primary">Save Password</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password');
    const eyeIcon = toggleButton.querySelector('i');
    
    // Show toggle button when user starts typing
    passwordInput.addEventListener('input', function() {
        if (this.value.length > 0) {
            toggleButton.style.display = 'block';
        } else {
            toggleButton.style.display = 'none';
        }
    });
    
    // Toggle password visibility
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
</script>
