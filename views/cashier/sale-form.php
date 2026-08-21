<?php
/**
 * $sale  — the saved record, only in edit mode. Null when creating.
 * $old   — raw $_POST from a failed submit attempt. Empty array on a
 *          fresh page load. Takes priority over $sale so the cashier
 *          always sees what THEY just typed, not stale saved values.
 *
 * $field() below is just a small local helper — not used anywhere else —
 * that picks $old first, falls back to $sale, then falls back to a default.
 * $saleKey exists because $old's combo field names (combo_cash) don't match
 * $sale's column names (amount_cash).
 */
$field = function (string $key, $default = '', ?string $saleKey = null) use ($old, $sale) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    $saleKey = $saleKey ?? $key;
    if (isset($sale[$saleKey])) {
        return $sale[$saleKey];
    }
    return $default;
};

$currentMethod = $field('payment_method', 'cash');
?>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Cashier</div>
        <div class="topbar__user">
            <span><?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">Log Out</a>
        </div>
    </header>

    <?php require __DIR__ . '/../layouts/cashier-nav.php'; ?>

    <main class="content content--narrow">
        <h1><?php echo $sale ? 'Edit Sale' : 'Record Sale'; ?></h1>
        <p class="field-hint">Copy this in exactly as it's written in the physical record book.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($workers)): ?>
            <div class="alert alert--error">No active workers are assigned to your branch yet. Ask your Admin to add one.</div>
        <?php else: ?>

        <form method="POST"
              id="sale-form"
              action="<?php echo APP_URL; ?>/index.php?route=cashier/sales/<?php echo $sale ? 'edit' : 'create'; ?>"
              class="panel-form">
            <?php echo Csrf::field(); ?>
            <?php if ($sale): ?>
                <input type="hidden" name="id" value="<?php echo $sale['id']; ?>">
            <?php endif; ?>

            <?php if (!$sale): ?>
                <label for="record_for">Record For</label>
                <select id="record_for" name="record_for" required>
                    <option value="today" <?php echo $field('record_for', 'today') === 'today' ? 'selected' : ''; ?>>Today (<?php echo date('M j'); ?>)</option>
                    <option value="yesterday" <?php echo $field('record_for', 'today') === 'yesterday' ? 'selected' : ''; ?>>Yesterday (<?php echo date('M j', strtotime('-1 day')); ?>)</option>
                </select>
                <p class="field-hint">Only yesterday can be backdated, and only if it isn't closed yet.</p>
            <?php endif; ?>

            <label for="worker_id">Worker</label>
            <select id="worker_id" name="worker_id" required>
                <option value="">-- Select Worker --</option>
                <?php foreach ($workers as $w): ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo ((string) $field('worker_id') === (string) $w['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['full_name']); ?><?php echo $w['specialty'] ? ' — ' . htmlspecialchars($w['specialty']) : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="amount_made">Amount Made (₦)</label>
            <input type="number" id="amount_made" name="amount_made" step="0.01" min="0.01"
                   value="<?php echo htmlspecialchars($field('amount_made', '')); ?>" required
                   class="no-spinner">

            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method" required>
                <option value="cash" <?php echo $currentMethod === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="transfer" <?php echo $currentMethod === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                <option value="pos" <?php echo $currentMethod === 'pos' ? 'selected' : ''; ?>>POS</option>
                <option value="combination" <?php echo $currentMethod === 'combination' ? 'selected' : ''; ?>>Combination</option>
            </select>

            <div id="combo-fields" class="combo-fields" <?php echo $currentMethod !== 'combination' ? 'hidden' : ''; ?>>
                <p class="field-hint">These three must add up to the Amount Made above.</p>
                <label for="combo_cash">Cash portion (₦)</label>
                <input type="number" id="combo_cash" name="combo_cash" step="0.01" min="0"
                       value="<?php echo htmlspecialchars($field('combo_cash', '0', 'amount_cash')); ?>">

                <label for="combo_transfer">Transfer portion (₦)</label>
                <input type="number" id="combo_transfer" name="combo_transfer" step="0.01" min="0"
                       value="<?php echo htmlspecialchars($field('combo_transfer', '0', 'amount_transfer')); ?>">

                <label for="combo_pos">POS portion (₦)</label>
                <input type="number" id="combo_pos" name="combo_pos" step="0.01" min="0"
                       value="<?php echo htmlspecialchars($field('combo_pos', '0', 'amount_pos')); ?>">
            </div>

           <label for="tip_amount">Tip (₦, optional)</label>
            <input type="number" id="tip_amount" name="tip_amount" step="0.01" min="0"
                value="<?php echo htmlspecialchars($field('tip_amount', '0')); ?>"
                class="no-spinner">
            <p class="field-hint">Tips go entirely to the worker and are never part of the commission calculation.</p>

            <label for="note">Note (optional)</label>
            <textarea id="note" name="note" rows="2"><?php echo htmlspecialchars($field('note', '')); ?></textarea>

            <button type="submit" class="btn btn--primary"><?php echo $sale ? 'Save Changes' : 'Save Sale'; ?></button>
        </form>
        <?php endif; ?>

        <?php
        $backUrl = APP_URL . '/index.php?route=cashier/sales';
        if ($sale && $sale['business_date'] !== date('Y-m-d')) {
            $backUrl .= '&date=' . urlencode($sale['business_date']);
        }
        ?>
        <a class="link-back" href="<?php echo $backUrl; ?>">&larr; Back to Records</a>
    </main>
</div>
