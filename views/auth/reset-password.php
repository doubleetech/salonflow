<div class="auth-screen">
    <div class="auth-card">
        <h2>Set a New Password</h2>
        <p class="field-hint">Your code has been verified. Choose a new password below.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=reset-password">
            <?php echo Csrf::field(); ?>

            <label for="new_password">New Password</label>
            <div class="password-wrapper">
                <input type="password" id="new_password" name="new_password" required minlength="8">
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <label for="confirm_password">Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn btn--primary">Save New Password</button>
        </form>
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
        
        // Hide toggle button initially
        toggleButton.style.display = 'none';
        
        // Show toggle button when user starts typing in this specific input
        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                toggleButton.style.display = 'block';
            } else {
                toggleButton.style.display = 'none';
            }
        });
        
        // Toggle password visibility for this specific input
        toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
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