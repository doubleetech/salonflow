<?php
// This file expects nothing from the caller except that Auth/Session are available.
// It's just the list of links shown across every admin-side page.
$currentRoute = $_GET['route'] ?? '';
function navClass($route, $current) {
    return strpos($current, $route) === 0 ? 'admin-nav__link admin-nav__link--active' : 'admin-nav__link';
}
?>
<nav class="admin-nav">
    <a class="<?php echo navClass('admin/dashboard', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/dashboard">Dashboard</a>
    <a class="<?php echo navClass('admin/branches', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/branches">Branches</a>
    <a class="<?php echo navClass('admin/workers', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/workers">Workers</a>
    <a class="<?php echo navClass('admin/cashiers', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/cashiers">Cashiers</a>
    <a class="<?php echo navClass('admin/reports', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/reports">Reports</a>
    <a class="<?php echo navClass('admin/closures', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/closures">Closures</a>
    <a class="<?php echo navClass('admin/audit-log', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/audit-log">Audit Log</a>
    <a class="<?php echo navClass('admin/settings', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/settings">Settings</a>
</nav>
