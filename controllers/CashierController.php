<?php

/**
 * CashierController
 *
 * Cashiers no longer belong to one fixed branch — they rotate, and pick
 * which branch they're working fresh each business day (BranchAssignmentModel).
 * Every method here calls currentBranchId() to get that pick, which:
 *   - re-checks the database fresh on every request (never trusts a
 *     cached session value, since the pick could theoretically change
 *     at midnight into a new business day),
 *   - and redirects to the branch-picker screen if nothing's picked yet
 *     for today, exactly like redirectIfMustChangePassword() does for a
 *     pending password change.
 */
class CashierController
{
    public function dashboard(): void
    {
        Auth::requireCashier();
        $this->redirectIfMustChangePassword();
        $branchId = $this->currentBranchId();

        $pageTitle = 'Cashier Dashboard';
        $branch = BranchModel::find($branchId);
        $summary = TransactionModel::summaryForBranchToday($branchId);
        $isTodayClosed = ClosureModel::isClosed($branchId, date('Y-m-d'));
        $pendingReopen = ClosureModel::findReopenedForBranch($branchId);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/cashier/dashboard.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /** Shown once per business day, before anything else, if no branch has been picked yet. */
    public function chooseBranchForm(): void
    {
        Auth::requireCashier();
        $this->redirectIfMustChangePassword();

        if (BranchAssignmentModel::hasChosenToday(Auth::id())) {
            header('Location: ' . APP_URL . '/index.php?route=cashier/dashboard');
            exit;
        }

        $pageTitle = 'Choose Your Branch';
        $branches = BranchModel::allActive();
        $error = Session::flash('branch_choice_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/cashier/choose-branch.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function chooseBranchSubmit(): void
    {
        Auth::requireCashier();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');

        if (BranchAssignmentModel::hasChosenToday(Auth::id())) {
            header('Location: ' . APP_URL . '/index.php?route=cashier/dashboard');
            exit;
        }

        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $branch = BranchModel::find($branchId);

        if (!$branch || $branch['status'] !== 'active') {
            Session::flash('branch_choice_error', 'Please choose a valid, active branch.');
            header('Location: ' . APP_URL . '/index.php?route=cashier/choose-branch');
            exit;
        }

        BranchAssignmentModel::assignForToday(Auth::id(), $branchId);
        AuditLog::record('choose_branch', "Chose to work at {$branch['name']} for " . date('Y-m-d'));

        header('Location: ' . APP_URL . '/index.php?route=cashier/dashboard');
        exit;
    }

    /**
     * Records list — today by default, but can also show YESTERDAY
     * (always allowed, for backdating) or an Admin-reopened older day.
     * Anything else silently falls back to today, so a tampered URL
     * can't be used to peek at an ordinary closed day.
     */
    public function todayRecords(): void
    {
        Auth::requireCashier();
        $this->redirectIfMustChangePassword();
        $branchId = $this->currentBranchId();

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $requestedDate = $_GET['date'] ?? $today;

        if (!$this->isValidDate($requestedDate)) {
            $requestedDate = $today;
        }
        $isToday = $requestedDate === $today;
        $isYesterday = $requestedDate === $yesterday;

        if (!$isToday && !$isYesterday && !ClosureModel::isReopened($branchId, $requestedDate)) {
            $requestedDate = $today;
            $isToday = true;
            $isYesterday = false;
        }

        $viewDate = $requestedDate;

        $pageTitle = $isToday ? "Today's Records" : "Records for {$viewDate}";
        $records = TransactionModel::recordsForBranchAndDate($branchId, $viewDate);
        $closure = ClosureModel::findByBranchAndDate($branchId, $viewDate);
        // Only surface the "you have a reopened day waiting" banner while
        // looking at today — no point mentioning it while already there.
        $pendingReopen = $isToday ? ClosureModel::findReopenedForBranch($branchId) : null;
        $success = Session::flash('sale_success');
        $error = Session::flash('sale_error');

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/cashier/today-records.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /** Closes today, yesterday, or a specific reopened past day (validated below either way). */
    public function closeDay(): void
    {
        Auth::requireCashier();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');
        $branchId = $this->currentBranchId();

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $date = trim($_POST['date'] ?? $today);

        if (!$this->isValidDate($date)) {
            $date = $today;
        }

        $isToday = $date === $today;
        $isYesterday = $date === $yesterday;

        // A cashier can only ever close today, yesterday (backdating window),
        // or a day their Admin has explicitly reopened for them — never an
        // arbitrary older date.
        if (!$isToday && !$isYesterday && !ClosureModel::isReopened($branchId, $date)) {
            Session::flash('sale_error', 'You can only close today, yesterday, or a day your Admin has reopened for you.');
            header('Location: ' . APP_URL . '/index.php?route=cashier/sales');
            exit;
        }

        [$success, $errorMsg] = ClosureModel::close($branchId, $date, Auth::id());

        if (!$success) {
            Session::flash('sale_error', $errorMsg);
        } else {
            AuditLog::record('close_business_day', "Closed business day {$date}.");
            Session::flash('sale_success', "Business day {$date} closed.");
        }

        $redirectUrl = APP_URL . '/index.php?route=cashier/sales';
        if (!$isToday) {
            $redirectUrl .= '&date=' . urlencode($date);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function saleForm(): void
    {
        Auth::requireCashier();
        $this->redirectIfMustChangePassword();
        $branchId = $this->currentBranchId();

        // ?for=yesterday is just a pre-fill hint (e.g. from a "record a
        // backdated sale" link on the Records screen) — the real check
        // happens against whatever's actually submitted, in saleSubmit().
        $recordForHint = ($_GET['for'] ?? '') === 'yesterday' ? 'yesterday' : 'today';

        $this->renderSaleForm(
            branchId: $branchId,
            sale: null,
            old: ['record_for' => $recordForHint],
            error: Session::flash('sale_error')
        );
    }

    public function saleSubmit(): void
    {
        Auth::requireCashier();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');
        $branchId = $this->currentBranchId();

        [$valid, $data, $errorMsg] = $this->validateSale($_POST);

        if (!$valid) {
            // IMPORTANT: we redisplay the form right here, in the same request,
            // instead of redirecting. A redirect throws away $_POST entirely —
            // that's what was making an earlier error message confusing:
            // the form came back blank, so a "combination doesn't add up" error
            // looked like it came from nowhere. Redisplaying keeps everything
            // the cashier typed, so they can see exactly what to fix.
            $this->renderSaleForm(branchId: $branchId, sale: null, old: $_POST, error: $errorMsg);
            return;
        }

        // Backdating check happens here, not in TransactionModel::create() —
        // the model just writes whatever date it's told; deciding whether
        // that's ALLOWED right now is this controller's job.
        if (ClosureModel::isClosed($branchId, $data['business_date'])) {
            $dayLabel = $data['business_date'] === date('Y-m-d') ? 'Today' : $data['business_date'];
            Session::flash('sale_error', "{$dayLabel} is already closed. Ask your Admin to reopen it if you need to record more sales for that day.");
            header('Location: ' . APP_URL . '/index.php?route=cashier/sales');
            exit;
        }

        $transactionId = TransactionModel::create(
            $branchId,
            $data['worker_id'],
            Auth::id(),
            $data['amount_made'],
            $data['payment_method'],
            $data['amounts'],
            $data['tip_amount'],
            $data['note'],
            $data['business_date']
        );

        AuditLog::record('record_sale', "Recorded sale #{$transactionId} for ₦{$data['amount_made']} (business date {$data['business_date']})");
        Session::flash('sale_success', 'Sale recorded.');

        $redirectUrl = APP_URL . '/index.php?route=cashier/sales';
        if ($data['business_date'] !== date('Y-m-d')) {
            $redirectUrl .= '&date=' . urlencode($data['business_date']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function editSaleForm(): void
    {
        Auth::requireCashier();
        $this->redirectIfMustChangePassword();
        $branchId = $this->currentBranchId();

        $id = (int) ($_GET['id'] ?? 0);
        $sale = TransactionModel::find($id);

        if (!$sale || $sale['branch_id'] != $branchId || !TransactionModel::isEditable($sale)) {
            header('Location: ' . APP_URL . '/index.php?route=cashier/sales');
            exit;
        }

        $this->renderSaleForm(
            branchId: $branchId,
            sale: $sale,
            old: [],
            error: Session::flash('sale_error')
        );
    }

    public function editSaleSubmit(): void
    {
        Auth::requireCashier();
        Csrf::verifyOrFail($_POST['csrf_token'] ?? '');
        $branchId = $this->currentBranchId();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = TransactionModel::find($id);

        if (!$existing || $existing['branch_id'] != $branchId || !TransactionModel::isEditable($existing)) {
            Session::flash('sale_error', 'This record can no longer be edited.');
            header('Location: ' . APP_URL . '/index.php?route=cashier/sales');
            exit;
        }

        [$valid, $data, $errorMsg] = $this->validateSale($_POST);

        if (!$valid) {
            // Same fix as saleSubmit(): redisplay with what was typed, don't redirect.
            $this->renderSaleForm(branchId: $branchId, sale: $existing, old: $_POST, error: $errorMsg);
            return;
        }

        // Editing never changes WHICH day a record belongs to — only its
        // content (amount/worker/etc). That's fixed at creation.
        TransactionModel::update(
            $id,
            $data['worker_id'],
            $data['amount_made'],
            $data['payment_method'],
            $data['amounts'],
            $data['tip_amount'],
            $data['note']
        );

        AuditLog::record('edit_sale', "Edited sale #{$id}");
        Session::flash('sale_success', 'Sale updated.');

        // Land back on the day the record actually belongs to — matters
        // when editing a backdated or reopened PAST day, not just today.
        $redirectUrl = APP_URL . '/index.php?route=cashier/sales';
        if ($existing['business_date'] !== date('Y-m-d')) {
            $redirectUrl .= '&date=' . urlencode($existing['business_date']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Reports, scoped to whichever branch the cashier is currently working
     * today — never other branches, never "entire business" (that's Admin-only).
     */
    public function reports(): void
    {
        Auth::requireCashier();
        $this->redirectIfMustChangePassword();
        $branchId = $this->currentBranchId();

        $pageTitle = 'Reports';
        $branch = BranchModel::find($branchId);
        [$range, $rangeError] = DateRange::resolve($_GET);

        $summary = null;
        $workerPerformance = null;
        $error = $rangeError;

        if (!$rangeError) {
            $summary = ReportModel::summary($branchId, $range['start'], $range['end']);
            $workerPerformance = ReportModel::workerPerformance($range['start'], $range['end'], $branchId);
        }

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/cashier/reports.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    private function redirectIfMustChangePassword(): void
    {
        if (Auth::mustChangePassword()) {
            header('Location: ' . APP_URL . '/index.php?route=change-password');
            exit;
        }
    }

    /**
     * Returns the branch this cashier picked for TODAY, checked fresh
     * against the database on every call — never trusts a cached session
     * value, since the pick is only valid for one calendar date and
     * should never silently carry over past midnight.
     * Redirects to the branch-picker (and ends the request) if nothing's
     * been picked yet.
     */
    private function currentBranchId(): int
    {
        $branchId = BranchAssignmentModel::getForCashierToday(Auth::id());

        if ($branchId === null) {
            header('Location: ' . APP_URL . '/index.php?route=cashier/choose-branch');
            exit;
        }

        return $branchId;
    }

    /**
     * Renders the shared sale form for both create and edit, in either
     * its fresh state or a "here's what you typed, here's what's wrong"
     * state. $sale is the existing record in edit mode (or null when
     * creating). $old is the raw $_POST from a failed submit — when
     * present, it takes priority over $sale so the cashier sees their
     * own attempted values, not the stale saved ones.
     */
    private function renderSaleForm(int $branchId, ?array $sale, array $old, ?string $error): void
    {
        $pageTitle = $sale ? 'Edit Sale' : 'Record Sale';
        $workers = WorkerModel::allActiveByBranch($branchId);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/cashier/sale-form.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Shared validation for create + edit sale forms.
     * Returns [isValid, cleanedData, errorMessage].
     * $data['business_date'] is only meaningful for CREATE (edit never
     * changes which day a record belongs to) but is always computed here
     * for consistency — callers that don't need it (editSaleSubmit) just
     * ignore it.
     */
    private function validateSale(array $post): array
    {
        $workerId = (int) ($post['worker_id'] ?? 0);
        $amountMade = (float) ($post['amount_made'] ?? 0);
        $paymentMethod = $post['payment_method'] ?? '';
        $tipAmount = (float) ($post['tip_amount'] ?? 0);
        $note = trim($post['note'] ?? '');
        $recordFor = ($post['record_for'] ?? 'today') === 'yesterday' ? 'yesterday' : 'today';

        $validMethods = ['cash', 'transfer', 'pos', 'combination'];

        if ($workerId <= 0) {
            return [false, [], 'Please select a worker.'];
        }
        if ($amountMade <= 0) {
            return [false, [], 'Amount made must be greater than zero.'];
        }
        if (!in_array($paymentMethod, $validMethods, true)) {
            return [false, [], 'Please select a valid payment method.'];
        }
        if ($tipAmount < 0) {
            return [false, [], 'Tip cannot be negative.'];
        }

        // Figure out how amount_made splits across cash/transfer/pos.
        if ($paymentMethod === 'combination') {
            $cash = (float) ($post['combo_cash'] ?? 0);
            $transfer = (float) ($post['combo_transfer'] ?? 0);
            $pos = (float) ($post['combo_pos'] ?? 0);

            if ($cash < 0 || $transfer < 0 || $pos < 0) {
                return [false, [], 'Combination amounts cannot be negative.'];
            }

            $sum = round($cash + $transfer + $pos, 2);
            if (abs($sum - round($amountMade, 2)) > 0.01) {
                return [false, [], "Combination amounts (₦{$sum}) must add up to the total amount made (₦{$amountMade}). Fill in the Cash/Transfer/POS boxes below the payment method — they default to 0 each."];
            }

            $amounts = ['cash' => $cash, 'transfer' => $transfer, 'pos' => $pos];
        } else {
            // Single method: the full amount sits in its matching column,
            // the other two are zero. Keeps future SUM() totals simple
            // regardless of how a given sale was actually paid.
            $amounts = ['cash' => 0.0, 'transfer' => 0.0, 'pos' => 0.0];
            $amounts[$paymentMethod] = $amountMade;
        }

        $businessDate = $recordFor === 'yesterday'
            ? date('Y-m-d', strtotime('-1 day'))
            : date('Y-m-d');

        return [true, [
            'worker_id' => $workerId,
            'amount_made' => round($amountMade, 2),
            'payment_method' => $paymentMethod,
            'amounts' => $amounts,
            'tip_amount' => round($tipAmount, 2),
            'note' => $note,
            'business_date' => $businessDate,
        ], ''];
    }

    private function isValidDate(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
