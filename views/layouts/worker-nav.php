<?php
$currentRoute = $_GET['route'] ?? '';
function workerNavClass($matchRoutes, $current) {
    return in_array($current, (array) $matchRoutes, true) ? 'admin-nav__link admin-nav__link--active' : 'admin-nav__link';
}
?>
<nav class="admin-nav">
    <a class="<?php echo workerNavClass('worker/dashboard', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=worker/dashboard">Dashboard</a>
    <a class="<?php echo workerNavClass('worker/reports', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=worker/reports">My Reports</a>
</nav>
