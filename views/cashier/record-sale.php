<?php
/**
 * Quick-sale staff list. Included by views/cashier/dashboard.php inside <main>.
 *
 * $workers       - active workers of the cashier's branch, each row already
 *                  carrying 'sale_count' and 'revenue' for today.
 * $isTodayClosed - true once today's business day has been closed.
 *
 * Tapping a card opens the popup in record-sale-sheet.php (wired up by app.js).
 */
?>
<section class="quick-sale" id="quickSale"
         data-submit-url="<?php echo APP_URL; ?>/index.php?route=cashier/sales/create"
         data-login-url="<?php echo APP_URL; ?>/index.php?route=who-are-you">

    <div class="content-header">
        <h2 class="quick-sale__title">Record a sale</h2>
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
</section>
