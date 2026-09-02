<?php

/**
 * AdminSignupController
 * One-time admin signup for first-time deployment.
 * After signup, the route is permanently disabled.
 */
class AdminSignupController
{
    private const SIGNUP_LOCK_FILE = __DIR__ . '/../storage/admin_signup.lock';

    /**
     * Check if signup is available
     * ALWAYS checks the database FIRST, then the lock file
     */
    private function isSignupAvailable(): bool
    {
        // FIRST: Check if any admin exists in the database
        if ($this->adminExists()) {
            // Admin exists, signup should NOT be available
            // Create lock file if it doesn't exist (self-healing)
            $this->createLockFile();
            return false;
        }
        
        // SECOND: Check if lock file exists (signup already used)
        if (file_exists(self::SIGNUP_LOCK_FILE)) {
            return false;
        }
        
        return true;
    }

    /**
     * Show the signup form
     */
    public function showForm(): void
    {
        // If already logged in, redirect to dashboard
        if (Auth::check()) {
            header('Location: ' . APP_URL . '/index.php?route=admin/dashboard');
            exit;
        }

        // Check if signup is available (database-first check)
        if (!$this->isSignupAvailable()) {
            $pageTitle = 'Signup Unavailable';
            $error = 'The admin signup has already been completed.';
            require __DIR__ . '/../views/layouts/header.php';
            require __DIR__ . '/../views/auth/signup-unavailable.php';
            require __DIR__ . '/../views/layouts/footer.php';
            return;
        }

        $pageTitle = 'Admin Signup';
        $error = Session::flash('signup_error');
        $success = Session::flash('signup_success');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/auth/admin-signup.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Process the signup form
     */
    public function submit(): void
    {
        // Check if signup is available (database-first check)
        if (!$this->isSignupAvailable()) {
            Session::flash('signup_error', 'Signup is no longer available.');
            header('Location: ' . APP_URL . '/index.php?route=admin-signup');
            exit;
        }

        // Verify CSRF token
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        // Get form data
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        // Validate inputs
        $errors = [];

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($fullName)) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($fullName) < 2) {
            $errors[] = 'Full name must be at least 2 characters.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            Session::flash('signup_error', implode(' ', $errors));
            header('Location: ' . APP_URL . '/index.php?route=admin-signup');
            exit;
        }

        try {
            $db = Database::connect();

            // Check if user already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                Session::flash('signup_error', 'A user with this email already exists.');
                header('Location: ' . APP_URL . '/index.php?route=admin-signup');
                exit;
            }

            // Double-check if any admin exists (safety check)
            if ($this->adminExists()) {
                Session::flash('signup_error', 'An admin account already exists.');
                header('Location: ' . APP_URL . '/index.php?route=admin-signup');
                exit;
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Create admin user
            $stmt = $db->prepare(
                "INSERT INTO users (email, full_name, password_hash, role, status, created_at) 
                 VALUES (:email, :full_name, :password_hash, 'admin', 'active', NOW())"
            );
            $stmt->execute([
                'email' => $email,
                'full_name' => $fullName,
                'password_hash' => $passwordHash
            ]);

            // Create lock file to disable future signups
            $this->createLockFile();

            // Log the signup
            AuditLog::record('admin_signup', "Admin account created for {$email}");

            // Redirect to login with success message
            Session::flash('login_success', 'Account created successfully! Please login.');
            header('Location: ' . APP_URL . '/index.php?route=admin-login');
            exit;

        } catch (PDOException $e) {
            error_log('Admin signup error: ' . $e->getMessage());
            Session::flash('signup_error', 'An error occurred during signup. Please try again.');
            header('Location: ' . APP_URL . '/index.php?route=admin-signup');
            exit;
        }
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
            error_log('adminExists() check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create lock file to disable signup
     */
    private function createLockFile(): void
    {
        $storageDir = dirname(self::SIGNUP_LOCK_FILE);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        
        // Only write if the file doesn't exist (preserve existing content)
        if (!file_exists(self::SIGNUP_LOCK_FILE)) {
            file_put_contents(self::SIGNUP_LOCK_FILE, date('Y-m-d H:i:s') . ' - Signup completed');
        }
    }

    public function reset(): void
    {
        // Only allow in development mode
        if (defined('ENV') && ENV === 'production') {
            http_response_code(403);
            echo "This action is not allowed in production.";
            exit;
        }

        $lockFile = self::SIGNUP_LOCK_FILE;
        
        if (file_exists($lockFile)) {
            unlink($lockFile);
            echo "Signup has been reset. You can now sign up again.\n";
        } else {
            echo "No lock file found. Signup is already available.\n";
        }
    }

    /**
     * Get signup status (for debugging)
     */
    public function status(): void
    {
        header('Content-Type: application/json');
        
        $adminExists = $this->adminExists();
        $lockExists = file_exists(self::SIGNUP_LOCK_FILE);
        $signupAvailable = $this->isSignupAvailable();
        
        echo json_encode([
            'admin_exists' => $adminExists,
            'lock_file_exists' => $lockExists,
            'signup_available' => $signupAvailable,
            'lock_file_path' => self::SIGNUP_LOCK_FILE,
        ], JSON_PRETTY_PRINT);
    }
}