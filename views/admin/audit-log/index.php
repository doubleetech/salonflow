<?php
$selectedAction = $_GET['action'] ?? '';
$selectedStart = $_GET['start_date'] ?? '';
$selectedEnd = $_GET['end_date'] ?? '';

// Small helper to build a pagination/filter link that keeps whatever
// filters are currently applied, only swapping out the page number.
$buildPageUrl = function (int $targetPage) use ($selectedAction, $selectedStart, $selectedEnd) {
    $params = [
        'route' => 'admin/audit-log',
        'page' => $targetPage,
        'action' => $selectedAction,
        'start_date' => $selectedStart,
        'end_date' => $selectedEnd,
    ];
    return APP_URL . '/index.php?' . http_build_query($params);
};
?>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Admin</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../../layouts/admin-nav.php'; ?>

    <main class="content">
        <h1>Audit Log</h1>

        <form method="GET" action="<?php echo APP_URL; ?>/index.php" class="panel-form">
            <input type="hidden" name="route" value="admin/audit-log">
            <div class="filter-row">
                <div>
                    <label for="action">Action</label>
                    <select id="action" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $selectedAction === $a ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="start_date">From</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($selectedStart); ?>">
                </div>
                <div>
                    <label for="end_date">To</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($selectedEnd); ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">Filter</button>
                <a class="btn btn--brass" href="<?php echo APP_URL; ?>/index.php?route=admin/audit-log">Clear</a>
            </div>
        </form>

        <p class="field-hint"><?php echo number_format($total); ?> total entries.</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="empty-row">No matching audit log entries.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($log['user_label'] ?? 'system'); ?></td>
                    <td><span class="badge badge--muted"><?php echo htmlspecialchars($log['action']); ?></span></td>
                    <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn--small btn--brass" href="<?php echo $buildPageUrl($page - 1); ?>">&larr; Previous</a>
            <?php endif; ?>
            <span class="field-hint">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn--small btn--brass" href="<?php echo $buildPageUrl($page + 1); ?>">Next &rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
