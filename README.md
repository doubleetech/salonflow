# SalonFlow v1.0 — Complete + v2 Feature Additions

## Phase 1 — Foundation & Authentication
- Full DB schema (all 9 tables from the spec), InnoDB, foreign keys, one seeded admin account.
- Core framework: PDO singleton, session handling, CSRF protection, Auth guards, audit logging.
- Complete auth flow: "Who are you?" → Admin login (email) / Cashier login (username) → forced
  password change on first login → role-specific dashboard shell.
- `.htaccess` lockdown on every folder except `public/`, since you tend to drop projects straight
  into `htdocs/` rather than pointing a vhost at a `public` folder.

## Phase 2 — Business Settings, Branches, Workers, Cashiers
- **Business Settings** (`admin/settings`): edit name, phone, address, currency.
- **Branches** (`admin/branches`): add, edit, and disable (never hard-deleted — same rule as
  user accounts, so old transactions always point at something real).
- **Workers** (`admin/workers`): add, edit, suspend/reactivate. Workers still don't log in —
  no username/password fields, matching the spec's repeated instruction on that. Editing a
  worker's commission % only affects future sales; every transaction already freezes its own
  rate at the moment it's recorded (this was built into the schema back in Phase 1).
- **Cashiers** (`admin/cashiers`): add, edit, suspend/reactivate, and reset password. New
  cashiers get a random temporary password shown once on-screen right after creation (never
  stored anywhere in plain text, never emailed) — the Admin is expected to hand it over directly.
  Cashiers still have no self-serve "forgot password," matching the spec.
- A shared admin navigation bar (`views/layouts/admin-nav.php`) now ties Dashboard / Branches /
  Workers / Cashiers / Settings together — one file, reused on every admin page.

## Setup (XAMPP, matching your usual local setup)

1. Copy the `salonflow` folder into `C:\xampp\htdocs\`.
2. Create the database:
   ```
   mysql -u root -P 3307 -e "CREATE DATABASE salonflow CHARACTER SET utf8mb4;"
   mysql -u root -P 3307 salonflow < database/schema.sql
   ```
   (Adjust `-P 3307` if your MariaDB is back on the default 3306 for this project — check
   `config/config.php` and match `DB_PORT` to whatever you're actually running.)
3. Open `config/config.php` and confirm `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` match your
   XAMPP setup, and set `APP_URL` to wherever you'll browse it, e.g.
   `http://localhost/salonflow/public`.
4. Visit `http://localhost/salonflow/public/index.php?route=who-are-you`.
5. Log in as Admin with:
   - Email: `owner@example.com`
   - Password: `Admin@12345`
   You'll be forced to set a new password immediately — **do this first, then use the
   Settings page (`admin/settings`) to update the business name**, and edit the seed
   admin's name/email directly in the `users` table if needed (there's no "edit my own
   admin profile" screen in the spec, so this one stays a direct DB edit by design).

## Phase 3 — Sales Recording
- **Record Sale** (`cashier/sales/create`): pick a worker (only ones assigned to the cashier's
  own branch), enter the amount made, choose a payment method — Cash, Transfer, POS, or
  Combination (which reveals three fields that must add up to the total, checked both in the
  browser via JS and again on the server, since JS alone is never trustworthy). Tip and note
  are optional. Saves immediately — no approval step, matching the spec.
- **The commission freeze, for real now:** the moment a sale saves, `TransactionModel::create()`
  reads the worker's *current* `commission_percentage`, computes `worker_commission` and
  `salon_share`, and writes all three onto the transaction row. From then on, changing that
  worker's rate in Phase 2's Workers screen cannot touch this row — it's just data now.
- **Today's Records** (`cashier/sales`): today's sales for the cashier's branch, newest first,
  with an "Edit" link that only appears if the record is still same-day and unlocked
  (`TransactionModel::isEditable()`). Editing recalculates commission against the worker's
  current rate — safe here because it's still the same day, before any closure.
- **Cashier Dashboard now shows real numbers**: today's record count, revenue, and the
  cash/transfer/POS totals, pulled live from `transactions`. (Admin's multi-branch dashboard
  still arrives in Phase 4 — one branch's same-day totals were a natural side-effect of
  building sales recording, so there was no reason to leave them as placeholders.)
- Every sale/edit is written to the audit log (`record_sale`, `edit_sale`).

## Phase 4 — Real Dashboards, Reports, PDF Export
- **Admin Dashboard now shows real business-wide numbers**: Today's/Week-to-Date/Month-to-Date
  revenue, today's Cash/Transfer/POS/Tips/Worker Commissions/Salon Earnings, plus today's
  Branch Revenue and Worker Performance tables — all pulled live via the new `ReportModel`.
  "Week-to-Date"/"Month-to-Date" mean Monday-so-far and 1st-of-month-so-far, not a full
  completed week/month (the Reports screen is where you'd pull a full completed period).
- **Reports** (`admin/reports`): filter by Daily / Weekly / Monthly / Custom Range, and by
  Entire Business or one specific branch. Shows the same kind of summary + branch breakdown
  + worker performance tables as the dashboard, just for whatever period/branch you pick.
- **PDF Export**: same filters, but a from-scratch, dependency-free PDF writer
  (`core/SimplePdf.php`) builds a downloadable PDF instead of an HTML page. No Composer, no
  external library — literally hand-writes the PDF file format. **Known limitation**: it uses
  the PDF standard Helvetica font, which can't render the ₦ symbol or other non-Latin-1
  characters — PDF reports print amounts as `NGN 1,234.56` instead of `₦1,234.56`. The website
  itself keeps ₦ everywhere; this only affects the exported PDF file.
- PDF exports are logged to the audit trail (`export_pdf`).

## Phase 5 — Business Day Closures & Audit Log Viewer
- **Close Business Day** (button on the cashier's Today's Records page): snapshots that day's
  totals into `daily_closures` (reusing the exact same `ReportModel::summary()` math the
  dashboard already uses, so a closure can never disagree with what was shown on screen) and
  locks every transaction for that branch/day (`is_locked = 1`). Once closed: no editing, and
  no new sales can be recorded for that day either (blocked with an explanation) until an Admin
  reopens it.
- **Reopen a Closed Day** (`admin/closures`, Admin-only): pick a branch + date + reason. Unlocks
  that day's transactions again and records who reopened it and why — that history is kept
  permanently, even after the day is closed again. The cashier then sees a banner on their
  dashboard/records screen pointing them straight to the reopened day, edits what's needed, and
  closes it again through the same button as always.
- **Editability rule changed under the hood**: a transaction is now editable purely based on its
  real `is_locked` flag, not "is it today" — that single flag correctly covers both the normal
  case (today, not yet closed) and the exception case (an older day an Admin just reopened).
- **Audit Log Viewer** (`admin/audit-log`, Admin-only): filterable by action type and date range,
  paginated 25 per page. Every audit-logged action throughout the app (logins, password resets,
  worker/branch/cashier changes, sales recorded/edited, PDF exports, closures, reopens) shows up
  here.
- Security note: a cashier can only ever close *today*, or a day their own Admin explicitly
  reopened for their own branch — verified server-side on every request, not just hidden in the
  UI, so a tampered URL/form can't be used to peek at or edit another branch's records or an
  ordinary closed day that was never reopened.

## Gap fix: Admin Password Recovery (Email OTP)
This was actually part of the original Phase 1 spec — the `password_resets` table and the
"Forgot Password?" link on the Admin login screen have both existed since Phase 1 — but the
real OTP-email workflow behind that link was never built until now. It's live as of this build:

- **Forgot Password** (`forgot-password`): Admin enters their email. Same generic message shows
  whether or not that email is actually registered — never reveals which admin emails exist.
- A 6-digit code is generated, **hashed** before being stored (via `password_hash()`, same as
  real passwords — not kept as readable text even though it's short-lived), and emailed via
  **PHPMailer** (the real, official library — downloaded directly from
  `github.com/PHPMailer/PHPMailer`, not hand-rolled).
- **Verify Code** (`verify-otp`): expires after 10 minutes, single-use (marked `used` the moment
  it's successfully consumed).
- **Set a New Password** (`reset-password`): hashes and saves the new password, marks the OTP
  used, and logs a `password_reset` entry to the audit trail — matching the spec's requirement
  exactly.

**To actually receive the email, you need to fill in real SMTP credentials** in
`config/config.php` (`SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD`, etc.) — a Gmail App Password,
Mailtrap (great for testing without spamming real inboxes), SendGrid, or your hosting provider's
SMTP all work. Until those are filled in, the OTP is still generated and stored correctly (so
the rest of the flow is fully testable), but the email itself will silently fail to send —
check `php_error_log` for `Mailer: failed to send OTP email` if you're testing this and nothing
arrives.

## v2 Feature Additions (5 changes requested after the original spec)

**⚠️ If you already have a working database, run the migration, NOT schema.sql:**
```
mysql -u root -P 3307 salonflow < database/migration_v2.sql
```
Re-running `schema.sql` would try to recreate tables that already exist and could wipe your
test data (branches, workers, cashiers, sales). `migration_v2.sql` only ADDs what's new —
worker login support and the branch-rotation table — without touching anything else.
Fresh install? Just run `schema.sql` as normal; it already includes everything below.

### 1. Workers now have their own dashboard + reports
Workers can optionally get login access (username + numeric temp password, same pattern as
cashiers). Leaving the username blank when adding a worker keeps them login-less, matching
the original spec's default — Admin can grant access later via **Enable Login** on the
Workers screen. A worker's dashboard/reports (`worker/dashboard`, `worker/reports`) show
**only their own** sales, commission, and tips — never another worker's numbers, never
salon-wide totals. This is enforced at the database query level (every query is scoped to
`worker_id = <their own, looked up from their session>`), not just hidden in the UI.

### 2. Cashiers now pick a branch fresh each business day (they rotate)
Cashier accounts no longer have a fixed branch. After logging in, if they haven't picked a
branch yet today, they're sent to `cashier/choose-branch` before anything else. Once picked,
it's locked for that whole calendar date — no switching mid-day or on re-login. The next
calendar date, they pick again. This is tracked in the new `cashier_branch_assignments`
table (one row per cashier per date), re-checked fresh against the database on every request
rather than trusted from session — a deliberate choice so a stale session can never silently
carry a branch pick past midnight into a new business day.

### 3. Business day closures are "signed" with the cashier's name
This already existed structurally (`daily_closures.closed_by` has tracked this since the
closures feature was built) but wasn't surfaced everywhere it should be. Now visible: on the
cashier's own closed-day summary panel ("Closed by [name] on [date]"), and in a new
"Closures in This Period" table on both the Admin and — since cashiers now have their own
Reports screen — nowhere else needed, since a cashier only ever sees their own branch's
closures via the closure panel itself.

### 4. Cashiers can backdate a sale to yesterday, no permission needed
The Record Sale form now has a Today/Yesterday choice. Yesterday stays available exactly
until that day gets closed — the instant it's closed, adding to it requires the existing
Admin-reopen flow (Phase 5), same as any other closed day. **Important caveat, worth
understanding clearly**: a backdated sale is always recorded against whichever branch the
cashier is *currently* signed into **today** — not whatever branch they may have worked
yesterday. If a cashier rotates from Branch A (yesterday) to Branch B (today), a "yesterday"
sale they backdate today gets attributed to Branch B, not Branch A. This was an explicit,
deliberate answer to a direct question, not an oversight — but it's a real consequence worth
knowing about if a cashier's rotation pattern makes this scenario common. If it turns out to
cause confusion in practice, it's a one-conversation fix to change.

### 5. Cashiers no longer require a branch when Admin creates them
The branch dropdown is gone entirely from the Add/Edit Cashier form. The Cashiers list now
shows "Working Today" (whichever branch they've picked so far today, or "Not chosen yet")
instead of a fixed branch column. Workers are unaffected — they still require a branch, per
the explicit instruction that only workers (not cashiers) stay tied to one.

### Also: temporary passwords are now numeric-only
Both cashier and worker temp passwords are 8-digit numbers now (e.g. `48291037`) instead of
the earlier mixed-format ones — easier to read aloud and type on a phone keypad, per request.

### Known limitation worth flagging
If two different cashiers both pick the **same** branch on the **same** day (e.g. a shift
handover), they can see and edit each other's records at that branch for that day — records
have always belonged to "this branch, this day," not to whichever individual cashier created
them (true since Phase 3, unchanged here). Closing that day still only records the ONE
cashier who clicked "Close Business Day," not every cashier who contributed sales to it.

## Known open issue (not yet resolved)
Sale recording (`record_sale`/`edit_sale`) audit log entries have been observed NOT appearing
in `audit_logs` even when the underlying sale genuinely saved — despite the controller code
path looking correct on inspection. Not yet root-caused; worth checking `php_error_log` /
Apache error log for anything logged at the exact moment of a test sale save, next time this
comes up.

## Business rules clarified during testing (worth remembering)
Two related tip-handling decisions were made after walking through real numbers together —
documenting them here since they're not obvious from the spec text alone:

- **Cash/Transfer/POS Total include tips**, folded in proportionally to how each sale's amount
  was split across channels. These represent actual money movement (for reconciling against a
  bank statement) — e.g. if a customer transfers ₦10,000 + a ₦1,000 tip in one go, Transfer
  Total shows ₦11,000, even though "Total Revenue" only counts the ₦10,000 service amount.
- **Salon Earnings = Revenue − Worker Commissions − Tips.** Even though a tip is fully the
  worker's money, it often arrives at the same time as the bill (customer pays cashier
  everything together), so the salon owner wants it treated as money the salon is briefly
  holding and then handing over — not money that ever counted as the salon's own earnings.
  Concretely: Total Revenue, Cash/Transfer/POS Totals, and Worker Commissions all stay exactly
  as the spec defines them; only the "Salon Earnings" figure itself has tips subtracted out of it.

## Known simplification worth knowing about
The ₦ currency symbol is hardcoded directly in the cashier views rather than pulled from
`BusinessModel::get()['currency']`. Fine for a single Naira-based salon; would need a
find-and-replace if this codebase were ever reused for a different currency.

## What's intentionally NOT here
Nothing from the original spec — all 5 phases are complete. Anything beyond this point (e.g.
booking/POS features) was explicitly out of scope per the spec's own "SalonFlow is NOT a
booking system, NOT a POS" framing.

## Folder structure
```
salonflow/
├── config/         # DB + app config (web-blocked)
├── core/           # Database, Session, Csrf, Auth, AuditLog, Router, Mailer, DateRange, bootstrap (web-blocked)
│   └── PHPMailer/  # Official PHPMailer library (3 files, no Composer) - also individually web-blocked
├── models/         # DB-facing classes, one per table cluster (web-blocked)
├── controllers/    # Thin controllers — flow only, no raw SQL (web-blocked)
├── views/          # Plain PHP templates, split by role + shared layouts
├── database/       # schema.sql (fresh installs) + migration_v2.sql (existing installs) (web-blocked)
├── storage/logs/   # reserved for future error/log files (web-blocked)
└── public/         # the ONLY web-accessible folder — index.php, css, js
```

## Conventions used throughout this codebase
- `users.id` and `transactions.cashier_id` are `BIGINT UNSIGNED` (matches your usual convention).
- Handlers/Controllers stay thin — flow only; Models own all DB logic.
- Commission % and salon share are frozen onto each transaction row at insert time, so future
  commission changes on `worker_profiles` never alter historical figures.
- Every state-changing POST requires a valid CSRF token (`Csrf::field()` / `Csrf::verifyOrFail()`).
- Every meaningful action calls `AuditLog::record()` — logins, password changes/resets,
  worker/branch/cashier changes, sales recorded/edited, PDF exports, and business day
  closures/reopens are all covered.
