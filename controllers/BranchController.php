<?php

class BranchController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Branches';
        $branches = BranchModel::all();
        $success = Session::flash('branch_success');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/branches/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function createForm(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Add Branch';
        $branch = null; // null = create mode for the shared form view
        $error = Session::flash('branch_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/branches/form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function createSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            Session::flash('branch_error', 'Branch name is required.');
            header('Location: ' . APP_URL . '/index.php?route=admin/branches/create');
            exit;
        }

        $business = BusinessModel::get();
        $id = BranchModel::create($business['id'], $name, $address, $phone);

        AuditLog::record('create_branch', "Created branch: {$name}");
        Session::flash('branch_success', 'Branch added.');

        header('Location: ' . APP_URL . '/index.php?route=admin/branches');
        exit;
    }

    public function editForm(): void
    {
        Auth::requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $branch = BranchModel::find($id);

        if (!$branch) {
            header('Location: ' . APP_URL . '/index.php?route=admin/branches');
            exit;
        }

        $pageTitle = 'Edit Branch';
        $error = Session::flash('branch_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/branches/form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function editSubmit(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            Session::flash('branch_error', 'Branch name is required.');
            header('Location: ' . APP_URL . '/index.php?route=admin/branches/edit&id=' . $id);
            exit;
        }

        BranchModel::update($id, $name, $address, $phone);

        AuditLog::record('edit_branch', "Edited branch #{$id}: {$name}");
        Session::flash('branch_success', 'Branch updated.');

        header('Location: ' . APP_URL . '/index.php?route=admin/branches');
        exit;
    }

    /** Toggles a branch between active/disabled. Never deletes. */
    public function toggleStatus(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $id = (int) ($_POST['id'] ?? 0);
        $branch = BranchModel::find($id);

        if ($branch) {
            $newStatus = $branch['status'] === 'active' ? 'disabled' : 'active';
            BranchModel::setStatus($id, $newStatus);
            AuditLog::record('edit_branch', "Set branch #{$id} ({$branch['name']}) status to {$newStatus}");
            Session::flash('branch_success', 'Branch status updated.');
        }

        header('Location: ' . APP_URL . '/index.php?route=admin/branches');
        exit;
    }
}
