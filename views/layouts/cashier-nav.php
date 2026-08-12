<?php
$currentRoute = $_GET['route'] ?? '';
function cashierNavClass($matchRoutes, $current) {
    return in_array($current, (array) $matchRoutes, true) ? 'admin-nav__link admin-nav__link--active' : 'admin-nav__link';
}
?>
<nav class="admin-nav">
    <a class="<?php echo cashierNavClass('cashier/dashboard', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/dashboard">Dashboard</a>
    <a class="<?php echo cashierNavClass('cashier/sales/create', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create">Record Sale</a>
    <a class="<?php echo cashierNavClass(['cashier/sales', 'cashier/sales/edit'], $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/sales">Today's Records</a>
    <a class="<?php echo cashierNavClass('cashier/reports', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=cashier/reports">Reports</a>
</nav>
