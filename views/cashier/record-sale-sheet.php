<?php
/**
 * Quick-sale popup (bottom sheet) + "Saved" toast. Included by
 * views/cashier/dashboard.php after the page shell, so the fixed-position
 * popup is never inside a scrolling or clipped container.
 * Needs $workers and $isTodayClosed, same as record-sale.php.
 */
?>
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
            <p class="field-hint">Leave empty if this is cashback only.</p>

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

            <label for="qsTip">Cashback (₦, optional)</label>
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
