<?php

/**
 * AdminWorkerController
 * Not to be confused with WorkerPortalController — that one is the
 * worker's OWN dashboard/reports. This one is the admin-side screen for
 * managing worker profiles (add/edit/suspend, and — new — granting login access).
 *
 * Login access is OPTIONAL per worker: leaving the username blank at
 * creation keeps the original spec's default ("workers don't log in").
 * Admin can grant it later via the "Enable Login" action for any worker
 * who doesn't have one yet.
 */
class AdminWorkerController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Workers';
        $workers = WorkerModel::allWithBranch();
        $success = Session::flash('worker_success');
        $tempPassword = Session::flash('worker_temp_password'); // shown once, right after enabling/resetting login

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/workers/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function createForm(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Add Worker';
        $worker = null; // null = create mode for the shared form view
        $branches = BranchModel::allActive();
        $error = Session::flash('worker_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/workers/form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function createSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        [$valid, $data, $errorMsg] = $this->validate($_POST);

        if (!$valid) {
            Session::flash('worker_error', $errorMsg);
            header('Location: ' . APP_URL . '/index.php?route=admin/workers/create');
            exit;
        }

        $userId = null;
        $tempPassword = null;

        if ($data['username'] !== '') {
            if (UserModel::usernameExists($data['username'])) {
                Session::flash('worker_error', 'That username is already taken.');
                header('Location: ' . APP_URL . '/index.php?route=admin/workers/create');
                exit;
            }
            $result = UserModel::createWorkerAccount($data['full_name'], $data['username']);
            $userId = $result['id'];
            $tempPassword = $result['temp_password'];
        }

        WorkerModel::create(
            $data['branch_id'], $data['full_name'], $data['commission'],
            $data['specialty'], $data['employment_date'], $data['notes'], $userId
        );

        AuditLog::record('create_worker', "Created worker: {$data['full_name']}" . ($userId ? " (with login access)" : ''));
        Session::flash('worker_success', $tempPassword
            ? "Worker added with login access. Share this temporary password with {$data['full_name']} — it won't be shown again."
            : 'Worker added.');
        if ($tempPassword) {
            Session::flash('worker_temp_password', $tempPassword);
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/workers');
        exit;
    }

    public function editForm(): void
    {
        Auth::requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $worker = WorkerModel::find($id);

        if (!$worker) {
            header('Location: ' . APP_URL . '/index.php?route=admin/workers');
            exit;
        }

        $pageTitle = 'Edit Worker';
        $branches = BranchModel::allActive();
        $error = Session::flash('worker_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/workers/form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function editSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        [$valid, $data, $errorMsg] = $this->validate($_POST, editingUsernameLocked: true);

        if (!$valid) {
            Session::flash('worker_error', $errorMsg);
            header('Location: ' . APP_URL . '/index.php?route=admin/workers/edit&id=' . $id);
            exit;
        }

        // Note: this updates the CURRENT commission rate only.
        // Past transactions already froze their own rate at the time
        // they were recorded and are not affected by this change.
        WorkerModel::update(
            $id, $data['branch_id'], $data['full_name'], $data['commission'],
            $data['specialty'], $data['employment_date'], $data['notes']
        );

        AuditLog::record('edit_worker', "Edited worker #{$id}: {$data['full_name']} (commission now {$data['commission']}%)");
        Session::flash('worker_success', 'Worker updated.');

        header('Location: ' . APP_URL . '/index.php?route=admin/workers');
        exit;
    }

    public function toggleStatus(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $worker = WorkerModel::find($id);

        if ($worker) {
            $newStatus = $worker['status'] === 'active' ? 'suspended' : 'active';
            WorkerModel::setStatus($id, $newStatus);
            AuditLog::record('edit_worker', "Set worker #{$id} ({$worker['full_name']}) status to {$newStatus}");
            Session::flash('worker_success', 'Worker status updated.');
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/workers');
        exit;
    }

    /** Grants login access to a worker who was created before this feature existed (or opted out at the time). */
    public function enableLogin(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $worker = WorkerModel::find($id);

        if (!$worker) {
            header('Location: ' . APP_URL . '/index.php?route=admin/workers');
            exit;
        }
        if ($worker['user_id']) {
            Session::flash('worker_error', 'This worker already has login access.');
            header('Location: ' . APP_URL . '/index.php?route=admin/workers');
            exit;
        }
        if ($username === '') {
            Session::flash('worker_error', 'Please enter a username.');
            header('Location: ' . APP_URL . '/index.php?route=admin/workers');
            exit;
        }
        if (UserModel::usernameExists($username)) {
            Session::flash('worker_error', 'That username is already taken.');
            header('Location: ' . APP_URL . '/index.php?route=admin/workers');
            exit;
        }

        $result = UserModel::createWorkerAccount($worker['full_name'], $username);
        WorkerModel::linkUserAccount($id, $result['id']);

        AuditLog::record('create_user', "Enabled login access for worker #{$id} ({$worker['full_name']}): {$username}");
        Session::flash('worker_success', "Login access enabled for {$worker['full_name']}. Share this temporary password — it won't be shown again.");
        Session::flash('worker_temp_password', $result['temp_password']);

        header('Location: ' . APP_URL . '/index.php?route=admin/workers');
        exit;
    }

    /** Resets a worker's login password — same idea as AdminCashierController::resetPassword(). */
    public function resetPassword(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $worker = WorkerModel::find($id);

        if ($worker && $worker['user_id']) {
            $tempPassword = UserModel::resetToTempPassword((int) $worker['user_id']);
            AuditLog::record('password_reset', "Admin reset login password for worker #{$id} ({$worker['full_name']})");
            Session::flash('worker_success', "Password reset for {$worker['full_name']}. Share the new temporary password below — it won't be shown again.");
            Session::flash('worker_temp_password', $tempPassword);
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/workers');
        exit;
    }

    /**
     * Shared validation for create + edit. Returns [isValid, cleanedData, errorMessage].
     * $editingUsernameLocked is true on edit — username is never editable there
     * (matches the existing cashier pattern), so it's simply not validated at all.
     */
    private function validate(array $post, bool $editingUsernameLocked = false): array
    {
        $fullName = trim($post['full_name'] ?? '');
        $branchId = (int) ($post['branch_id'] ?? 0);
        $commission = (float) ($post['commission_percentage'] ?? -1);
        $specialty = trim($post['specialty'] ?? '');
        $employmentDate = trim($post['employment_date'] ?? '');
        $notes = trim($post['notes'] ?? '');
        $username = $editingUsernameLocked ? '' : trim($post['username'] ?? '');

        if ($fullName === '') {
            return [false, [], 'Full name is required.'];
        }
        if ($branchId <= 0) {
            return [false, [], 'Please select a branch.'];
        }
        if ($commission < 0 || $commission > 100) {
            return [false, [], 'Commission percentage must be between 0 and 100.'];
        }

        return [true, [
            'full_name' => $fullName,
            'branch_id' => $branchId,
            'commission' => $commission,
            'specialty' => $specialty,
            'employment_date' => $employmentDate ?: null,
            'notes' => $notes,
            'username' => $username,
        ], ''];
    }
}
