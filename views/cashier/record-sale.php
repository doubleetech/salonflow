<?php
/**
 * Quick-sale page.
 *
 * $workers       - active workers of the cashier's branch, each row already
 *                  carrying 'sale_count' and 'revenue' for today.
 * $isTodayClosed - true once today's business day has been closed.
 *
 * The popup form below posts to the SAME route as the old Record Sale form
 * (cashier/sales/create -> saleSubmit). app.js sends it in the background;
 * if JavaScript is off it still works as a normal form post.
 */
?>
<div class="app-shell">
    <header class="topbar">
    <div class="topbar__left">
        <div class="nav-toggle" id="navToggle" aria-label="Toggle navigation" role="button" tabindex="0">
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
        </div>
        <div class="topbar__brand"><?php echo APP_NAME; ?> · Cashier</div>
    </div>
    <div class="topbar__user">
        <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(Session::get('user_name')); ?></span>
        <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </a>
    </div>
    </header>

    <?php require __DIR__ . '/../layouts/cashier-nav.php'; ?>

    <main class="content quick-sale" id="quickSale"
          data-submit-url="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create"
          data-login-url="<?php echo APP_URL; ?>/index.php?route=who-are-you">

        <div class="content-header">
            <h1>Record Sale</h1>
            <a class="btn btn--outline btn--small"
               href="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create&amp;for=yesterday">
                <i class="fas fa-history"></i> Record a past sale
            </a>
        </div>

        <?php if ($isTodayClosed): ?>
            <div class="alert alert--warning">Today is already closed, so new sales can't be recorded. Ask your Admin to reopen it.</div>
        <?php endif; ?>

        <?php if (empty($workers)): ?>
            <div class="alert alert--error">No active staffs are assigned to your branch yet. Ask your Admin to add one.</div>
        <?php else: ?>

        <div class="quick-search">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="staffSearch" placeholder="Search staff by name..."
                   autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Search staff">
        </div>

        <div class="staff-list" id="staffList">
            <?php foreach ($workers as $w): ?>
                <button type="button" class="staff-card"
                        data-worker-id="<?php echo (int) $w['id']; ?>"
                        data-worker-name="<?php echo htmlspecialchars($w['full_name']); ?>"
                        <?php echo $isTodayClosed ? 'disabled' : ''; ?>>
                    <span class="staff-card__name"><?php echo htmlspecialchars($w['full_name']); ?></span>
                    <span class="staff-card__stats">
                        <span class="staff-card__count"><strong class="js-count"><?php echo (int) $w['sale_count']; ?></strong> <span class="js-count-label"><?php echo (int) $w['sale_count'] === 1 ? 'sale' : 'sales'; ?></span></span>
                        <span class="staff-card__revenue js-revenue">₦<?php echo number_format((float) $w['revenue'], 2); ?></span>
                    </span>
                    <span class="staff-card__add" aria-hidden="true"><i class="fas fa-plus"></i></span>
                </button>
            <?php endforeach; ?>
        </div>

        <p class="field-hint" id="staffNoMatch" hidden>No staff match your search.</p>

        <?php endif; ?>
    </main>
</div>

<?php if (!empty($workers) && !$isTodayClosed): ?>
<!-- Bottom-sheet popup (opened by app.js when a staff card is tapped) -->
<div class="qs-backdrop" id="qsBackdrop" hidden></div>
<div class="qs-sheet" id="qsSheet" role="dialog" aria-modal="true" aria-labelledby="qsWorkerName" hidden>
    <div class="qs-sheet__header">
        <div>
            <div class="qs-sheet__eyebrow">Record sale for</div>
            <div class="qs-sheet__title" id="qsWorkerName"></div>
        </div>
        <button type="button" class="qs-sheet__close" id="qsClose" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <form method="POST" id="qsForm" class="qs-form" novalidate
          action="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="worker_id" id="qsWorkerId" value="">

        <div class="qs-fields">
            <div class="alert alert--error" id="qsError" role="alert" hidden></div>

            <label for="qsAmount">Amount Made (₦)</label>
            <input type="number" id="qsAmount" name="amount_made" step="0.01" min="0"
                   inputmode="decimal" placeholder="0.00" autocomplete="off">
            <p class="field-hint">Leave empty if this is a tip only.</p>

            <label for="qsMethod">Payment Method</label>
            <select id="qsMethod" name="payment_method" required>
                <option value="cash">Cash</option>
                <option value="transfer" selected>Transfer</option>
                <option value="pos">POS</option>
                <option value="combination">Combination</option>
            </select>

            <div id="qsCombo" class="qs-combo" hidden>
                <p class="field-hint">These three must add up to the Amount Made above.</p>
                <label for="qsComboCash">Cash portion (₦)</label>
                <input type="number" id="qsComboCash" name="combo_cash" step="0.01" min="0" inputmode="decimal" value="0">

                <label for="qsComboTransfer">Transfer portion (₦)</label>
                <input type="number" id="qsComboTransfer" name="combo_transfer" step="0.01" min="0" inputmode="decimal" value="0">

                <label for="qsComboPos">POS portion (₦)</label>
                <input type="number" id="qsComboPos" name="combo_pos" step="0.01" min="0" inputmode="decimal" value="0">
            </div>

            <label for="qsTip">Tip (₦, optional)</label>
            <input type="number" id="qsTip" name="tip_amount" step="0.01" min="0"
                   inputmode="decimal" placeholder="0" autocomplete="off">

            <label for="qsNote">Note (optional)</label>
            <textarea id="qsNote" name="note" rows="2"></textarea>
        </div>

        <div class="qs-actions">
            <button type="submit" class="btn btn--primary btn--full" id="qsSave">Save Sale</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="qs-toast" id="qsToast" role="status" aria-live="polite" hidden></div>
