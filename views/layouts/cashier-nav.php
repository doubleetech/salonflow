<?php
$currentRoute = $_GET['route'] ?? '';
function cashierNavClass($matchRoutes, $current) {
    return in_array($current, (array) $matchRoutes, true) ? 'cashier-nav__link cashier-nav__link--active' : 'cashier-nav__link';
}
?>
<nav class="cashier-nav" id="navMenu">
    <!-- Close button inside the sidebar -->
    <div class="nav-close" id="navClose" aria-label="Close navigation" role="button" tabindex="0">
        <span class="nav-close__bar"></span>
        <span class="nav-close__bar"></span>
    </div>
    
    <div class="nav-menu-inner">
        <a class="<?php echo cashierNavClass('cashier/dashboard', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/dashboard">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a class="<?php echo cashierNavClass('cashier/sales/create', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create">
            <i class="fas fa-plus-circle"></i> Record Sale
        </a>
        <a class="<?php echo cashierNavClass(['cashier/sales', 'cashier/sales/edit'], $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales">
            <i class="fas fa-receipt"></i> Today's Records
        </a>
        <a class="<?php echo cashierNavClass('cashier/reports', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/reports">
            <i class="fas fa-file-alt"></i> Reports
        </a>
    </div>
</nav>