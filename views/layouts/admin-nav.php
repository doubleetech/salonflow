<?php
// This file expects nothing from the caller except that Auth/Session are available.
$currentRoute = $_GET['route'] ?? '';
function navClass($route, $current) {
    return strpos($current, $route) === 0 ? 'admin-nav__link admin-nav__link--active' : 'admin-nav__link';
}
?>
<nav class="admin-nav" id="navMenu">
    <!-- Close button inside the sidebar -->
    <div class="nav-close" id="navClose" aria-label="Close navigation" role="button" tabindex="0">
        <span class="nav-close__bar"></span>
        <span class="nav-close__bar"></span>
    </div>
    
    <div class="nav-menu-inner">
        <a class="<?php echo navClass('admin/dashboard', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/dashboard">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a class="<?php echo navClass('admin/branches', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/branches">
            <i class="fas fa-store"></i> Branches
        </a>
        <a class="<?php echo navClass('admin/workers', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/workers">
            <i class="fas fa-users"></i> Staffs
        </a>
        <a class="<?php echo navClass('admin/cashiers', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/cashiers">
            <i class="fas fa-user-tie"></i> Cashiers
        </a>
        <a class="<?php echo navClass('admin/reports', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/reports">
            <i class="fas fa-file-alt"></i> Reports
        </a>
        <a class="<?php echo navClass('admin/closures', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/closures">
            <i class="fas fa-lock"></i> Closures
        </a>
        <a class="<?php echo navClass('admin/audit-log', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/audit-log">
            <i class="fas fa-history"></i> Audit Log
        </a>
        <a class="<?php echo navClass('admin/settings', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=admin/settings">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>
</nav>