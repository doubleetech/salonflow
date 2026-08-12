<?php

/**
 * AdminCashierController
 * Not to be confused with CashierController — that one runs the cashier's
 * OWN dashboard. This one is the admin-side screen for managing cashier
 * accounts (create, edit, suspend, reset password).
 *
 * Cashiers are no longer assigned a fixed branch here — they rotate
 * between branches and pick one fresh each business day instead (see
 * BranchAssignmentModel). The list below shows whichever branch a
 * cashier has picked for TODAY, purely as a status display.
 */
class AdminCashierController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Cashiers';
        $cashiers = UserModel::allCashiersWithTodayBranch();
        $success = Session::flash('cashier_success');
        $tempPassword = Session::flash('cashier_temp_password'); // shown once, right after create/reset

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/cashiers/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function createForm(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Add Cashier';
        $cashier = null; // null = create mode for the shared form view
        $error = Session::flash('cashier_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/cashiers/form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function createSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($fullName === '' || $username === '') {
            Session::flash('cashier_error', 'Full name and username are both required.');
            header('Location: ' . APP_URL . '/index.php?route=admin/cashiers/create');
            exit;
        }

        if (UserModel::usernameExists($username)) {
            Session::flash('cashier_error', 'That username is already taken.');
            header('Location: ' . APP_URL . '/index.php?route=admin/cashiers/create');
            exit;
        }

        $result = UserModel::createCashier($fullName, $username);

        AuditLog::record('create_user', "Created cashier account: {$username} ({$fullName})");
        Session::flash('cashier_success', "Cashier created. Share this temporary password with {$fullName} — it won't be shown again.");
        Session::flash('cashier_temp_password', $result['temp_password']);

        header('Location: ' . APP_URL . '/index.php?route=admin/cashiers');
        exit;
    }

    public function editForm(): void
    {
        Auth::requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $cashier = UserModel::findById($id);

        if (!$cashier || $cashier['role'] !== 'cashier') {
            header('Location: ' . APP_URL . '/index.php?route=admin/cashiers');
            exit;
        }

        $pageTitle = 'Edit Cashier';
        $error = Session::flash('cashier_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/cashiers/form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function editSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');

        if ($fullName === '') {
            Session::flash('cashier_error', 'Full name is required.');
            header('Location: ' . APP_URL . '/index.php?route=admin/cashiers/edit&id=' . $id);
            exit;
        }

        UserModel::updateCashier($id, $fullName);

        AuditLog::record('edit_user', "Edited cashier #{$id}: {$fullName}");
        Session::flash('cashier_success', 'Cashier updated.');

        header('Location: ' . APP_URL . '/index.php?route=admin/cashiers');
        exit;
    }

    public function toggleStatus(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $cashier = UserModel::findById($id);

        if ($cashier && $cashier['role'] === 'cashier') {
            $newStatus = $cashier['status'] === 'suspended' ? 'active' : 'suspended';
            UserModel::setStatus($id, $newStatus);
            AuditLog::record('edit_user', "Set cashier #{$id} ({$cashier['full_name']}) status to {$newStatus}");
            Session::flash('cashier_success', 'Cashier status updated.');
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/cashiers');
        exit;
    }

    /** Admin resets a cashier's password — cashiers have no self-serve recovery. */
    public function resetPassword(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $cashier = UserModel::findById($id);

        if ($cashier && $cashier['role'] === 'cashier') {
            $tempPassword = UserModel::resetToTempPassword($id);
            AuditLog::record('password_reset', "Admin reset password for cashier #{$id} ({$cashier['full_name']})");
            Session::flash('cashier_success', "Password reset for {$cashier['full_name']}. Share the new temporary password below — it won't be shown again.");
            Session::flash('cashier_temp_password', $tempPassword);
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/cashiers');
        exit;
    }
}
