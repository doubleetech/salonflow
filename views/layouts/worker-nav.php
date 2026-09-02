<?php
$currentRoute = $_GET['route'] ?? '';
function workerNavClass($matchRoutes, $current) {
    return in_array($current, (array) $matchRoutes, true) ? 'worker-nav__link worker-nav__link--active' : 'worker-nav__link';
}
?>
<nav class="worker-nav" id="navMenu">
    <!-- Close button inside the sidebar -->
    <div class="nav-close" id="navClose" aria-label="Close navigation" role="button" tabindex="0">
        <span class="nav-close__bar"></span>
        <span class="nav-close__bar"></span>
    </div>
    
    <div class="nav-menu-inner">
        <a class="<?php echo workerNavClass('worker/dashboard', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=worker/dashboard">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a class="<?php echo workerNavClass('worker/reports', $currentRoute); ?>" href="<?php echo APP_URL; ?>/index.php?route=worker/reports">
            <i class="fas fa-file-alt"></i> My Reports
        </a>
    </div>
</nav>