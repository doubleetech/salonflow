<?php

class AuthController
{
    /** The very first screen: "Who are you? Admin / Cashier" */
    public function whoAreYou(): void
    {
        if (Auth::check()) {
            $this->redirectToDashboard();
            return;
        }

        $pageTitle = 'Who are you?';
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/who-are-you.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function adminLoginForm(): void
    {
        if (Auth::check()) {
            $this->redirectToDashboard();
            return;
        }

        // Check if any admin exists - if not, redirect to signup
        if (!$this->adminExists()) {
            // No admin exists - redirect to signup
            header('Location: ' . APP_URL . '/index.php?route=admin-signup');
            exit;
        }

        $pageTitle = 'Admin Login';
        $error = Session::flash('login_error');
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/admin-login.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function adminLoginSubmit(): void
    {
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $user = Auth::attemptAdminLogin($email, $password);

        if (!$user) {
            AuditLog::record('login_failed', "Failed admin login attempt for email: {$email}");
            Session::flash('login_error', 'Incorrect email or password.');
            header('Location: ' . APP_URL . '/index.php?route=admin-login');
            exit;
        }

        AuditLog::record('login', 'Admin logged in: ' . $user['full_name']);
        $this->redirectToDashboard();
    }

    public function cashierLoginForm(): void
    {
        if (Auth::check()) {
            $this->redirectToDashboard();
            return;
        }

        $pageTitle = 'Cashier Login';
        $error = Session::flash('login_error');
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/cashier-login.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function cashierLoginSubmit(): void
    {
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $user = Auth::attemptCashierLogin($username, $password);

        if (!$user) {
            AuditLog::record('login_failed', "Failed cashier login attempt for username: {$username}");
            Session::flash('login_error', 'Incorrect username or password.');
            header('Location: ' . APP_URL . '/index.php?route=cashier-login');
            exit;
        }

        AuditLog::record('login', 'Cashier logged in: ' . $user['full_name']);
        $this->redirectToDashboard();
    }

    public function workerLoginForm(): void
    {
        if (Auth::check()) {
            $this->redirectToDashboard();
            return;
        }

        $pageTitle = 'Staff Login';
        $error = Session::flash('login_error');
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/worker-login.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function workerLoginSubmit(): void
    {
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $user = Auth::attemptWorkerLogin($username, $password);

        if (!$user) {
            AuditLog::record('login_failed', "Failed worker login attempt for username: {$username}");
            Session::flash('login_error', 'Incorrect username or password.');
            header('Location: ' . APP_URL . '/index.php?route=worker-login');
            exit;
        }

        AuditLog::record('login', 'Worker logged in: ' . $user['full_name']);
        $this->redirectToDashboard();
    }

    /** Shown when status = pending_password_change (forced on first login). */
    public function changePasswordForm(): void
    {
        Auth::requireLogin();

        $pageTitle = 'Set Your Password';
        $error = Session::flash('password_error');
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/change-password.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function changePasswordSubmit(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $newPassword     = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            Session::flash('password_error', 'Password must be at least 8 characters.');
            header('Location: ' . APP_URL . '/index.php?route=change-password');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('password_error', 'Passwords do not match.');
            header('Location: ' . APP_URL . '/index.php?route=change-password');
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        UserModel::updatePasswordAndActivate(Auth::id(), $hash);
        Session::set('user_status', 'active');

        AuditLog::record('password_change', 'User set a new password after first login.');
        $this->redirectToDashboard();
    }

    public function logout(): void
    {
        AuditLog::record('logout', Session::get('user_name', 'unknown') . ' logged out.');
        Auth::logout();
        header('Location: ' . APP_URL . '/index.php?route=who-are-you');
        exit;
    }

    // ------------------------------------------------------------------
    // Admin password recovery (Email OTP) — Admin-only per the spec;
    // cashiers have no self-serve recovery, their Admin resets them instead.
    // ------------------------------------------------------------------

    public function forgotPasswordForm(): void
    {
        if (Auth::check()) {
            $this->redirectToDashboard();
            return;
        }

        $pageTitle = 'Forgot Password';
        $error = Session::flash('forgot_error');
        $success = Session::flash('forgot_success');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/forgot-password.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function forgotPasswordSubmit(): void
    {
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $email = trim($_POST['email'] ?? '');

        // Same message whether or not the email exists — never reveal
        // which admin emails are registered (same reasoning as the
        // timing-safe dummy password_verify() call in Auth::verifyAndLogin()).
        $genericMessage = 'If that email is registered, a 6-digit code has been sent to it. The code expires in 10 minutes.';

        $user = UserModel::findAdminByEmail($email);

        if ($user) {
            $otp = PasswordResetModel::generateOtp();
            PasswordResetModel::create((int) $user['id'], $otp);
            Mailer::sendOtpEmail($user['email'], $user['full_name'], $otp);
            Session::set('password_reset_user_id', $user['id']);
        }

        Session::flash('forgot_success', $genericMessage);
        header('Location: ' . APP_URL . '/index.php?route=verify-otp');
        exit;
    }

    public function verifyOtpForm(): void
    {
        if (!Session::has('password_reset_user_id')) {
            header('Location: ' . APP_URL . '/index.php?route=forgot-password');
            exit;
        }

        $pageTitle = 'Enter Verification Code';
        $error = Session::flash('otp_error');
        $success = Session::flash('forgot_success');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/verify-otp.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function verifyOtpSubmit(): void
    {
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $userId = Session::get('password_reset_user_id');

        if (!$userId) {
            header('Location: ' . APP_URL . '/index.php?route=forgot-password');
            exit;
        }

        $code = trim($_POST['otp_code'] ?? '');
        $resetRow = PasswordResetModel::findActiveForUser((int) $userId);

        if (!$resetRow || !PasswordResetModel::verify($resetRow, $code)) {
            Session::flash('otp_error', 'That code is incorrect or has expired. Please try again or request a new one.');
            header('Location: ' . APP_URL . '/index.php?route=verify-otp');
            exit;
        }

        // Remember exactly which reset row was verified, so the next step
        // marks THAT one used — not just any row for this user.
        Session::set('password_reset_verified_id', $resetRow['id']);
        header('Location: ' . APP_URL . '/index.php?route=reset-password');
        exit;
    }

    public function resetPasswordViaOtpForm(): void
    {
        if (!Session::has('password_reset_verified_id')) {
            header('Location: ' . APP_URL . '/index.php?route=forgot-password');
            exit;
        }

        $pageTitle = 'Set a New Password';
        $error = Session::flash('reset_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/reset-password.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function resetPasswordViaOtpSubmit(): void
    {
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $userId = Session::get('password_reset_user_id');
        $resetId = Session::get('password_reset_verified_id');

        if (!$userId || !$resetId) {
            header('Location: ' . APP_URL . '/index.php?route=forgot-password');
            exit;
        }

        // Defense in depth: re-check the reset row is still valid right
        // now, in case time passed or it was somehow already used since
        // the OTP verification step.
        $resetRow = PasswordResetModel::findById((int) $resetId);
        if (!$resetRow || (int) $resetRow['used'] === 1 || strtotime($resetRow['expires_at']) < time()) {
            Session::remove('password_reset_user_id');
            Session::remove('password_reset_verified_id');
            Session::flash('forgot_error', 'That reset session has expired. Please start again.');
            header('Location: ' . APP_URL . '/index.php?route=forgot-password');
            exit;
        }

        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            Session::flash('reset_error', 'Password must be at least 8 characters.');
            header('Location: ' . APP_URL . '/index.php?route=reset-password');
            exit;
        }
        if ($newPassword !== $confirmPassword) {
            Session::flash('reset_error', 'Passwords do not match.');
            header('Location: ' . APP_URL . '/index.php?route=reset-password');
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        UserModel::updatePasswordAndActivate((int) $userId, $hash);
        PasswordResetModel::markUsed((int) $resetId);

        AuditLog::record('password_reset', 'Admin reset their own password via email OTP recovery.');

        Session::remove('password_reset_user_id');
        Session::remove('password_reset_verified_id');

        header('Location: ' . APP_URL . '/index.php?route=admin-login');
        exit;
    }

    /**
     * Check if any admin exists in the database
     */
    private function adminExists(): bool
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            // If table doesn't exist yet, no admin exists
            return false;
        }
    }

    private function redirectToDashboard(): void
    {
        if (Auth::mustChangePassword()) {
            header('Location: ' . APP_URL . '/index.php?route=change-password');
            exit;
        }

        if (Auth::isAdmin()) {
            header('Location: ' . APP_URL . '/index.php?route=admin/dashboard');
        } elseif (Auth::isCashier()) {
            header('Location: ' . APP_URL . '/index.php?route=cashier/dashboard');
        } else {
            // Auth::isWorker() — the only role left. Explicit elseif chain
            // rather than a catch-all "else = cashier" now that there are
            // three roles, not two.
            header('Location: ' . APP_URL . '/index.php?route=worker/dashboard');
        }
        exit;
    }
}