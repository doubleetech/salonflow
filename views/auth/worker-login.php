<div class="auth-screen">
    <div class="auth-card">
        <h2>Worker Login</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=worker-login">
            <?php echo Csrf::field(); ?>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="button" class="toggle-password" aria-label="Toggle password visibility" style="display: none;">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn btn--primary">Log In</button>
        </form>

        <p class="hint-muted">Forgot your password? Ask your Admin to reset it.</p>
        <a class="link-back" href="<?php echo APP_URL; ?>/index.php?route=who-are-you">&larr; Back</a>
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
