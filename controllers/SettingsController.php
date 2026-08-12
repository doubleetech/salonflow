<?php

class SettingsController
{
    public function edit(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Business Settings';
        $business = BusinessModel::get();
        $success = Session::flash('settings_success');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/admin/settings.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function update(): void
    {
        Auth::requireAdmin();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        $business = BusinessModel::get();

        $name     = trim($_POST['name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $currency = trim($_POST['currency'] ?? 'NGN');

        BusinessModel::update($business['id'], $name, $phone, $address, $currency);

        AuditLog::record('edit_business_settings', "Updated business settings: {$name}");
        Session::flash('settings_success', 'Business settings saved.');

        header('Location: ' . APP_URL . '/index.php?route=admin/settings');
        exit;
    }
}
