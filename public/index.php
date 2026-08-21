<?php

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/Router.php';

$router = new Router();

// --- Public / auth routes ---
$router->get('who-are-you', ['AuthController', 'whoAreYou']);

$router->get('admin-login', ['AuthController', 'adminLoginForm']);
$router->post('admin-login', ['AuthController', 'adminLoginSubmit']);

$router->get('cashier-login', ['AuthController', 'cashierLoginForm']);
$router->post('cashier-login', ['AuthController', 'cashierLoginSubmit']);

$router->get('worker-login', ['AuthController', 'workerLoginForm']);
$router->post('worker-login', ['AuthController', 'workerLoginSubmit']);

$router->get('change-password', ['AuthController', 'changePasswordForm']);
$router->post('change-password', ['AuthController', 'changePasswordSubmit']);

// Admin password recovery (Email OTP) — see AuthController's dedicated section
$router->get('forgot-password', ['AuthController', 'forgotPasswordForm']);
$router->post('forgot-password', ['AuthController', 'forgotPasswordSubmit']);
$router->get('verify-otp', ['AuthController', 'verifyOtpForm']);
$router->post('verify-otp', ['AuthController', 'verifyOtpSubmit']);
$router->get('reset-password', ['AuthController', 'resetPasswordViaOtpForm']);
$router->post('reset-password', ['AuthController', 'resetPasswordViaOtpSubmit']);

$router->get('logout', ['AuthController', 'logout']);

// --- Admin routes ---
$router->get('admin/dashboard', ['AdminController', 'dashboard']);

// Heartbeat for live dashboard updates
$router->get('admin/heartbeat', ['AdminController', 'heartbeat']);

// Business settings
$router->get('admin/settings', ['SettingsController', 'edit']);
$router->post('admin/settings', ['SettingsController', 'update']);

// Branches
$router->get('admin/branches', ['BranchController', 'index']);
$router->get('admin/branches/create', ['BranchController', 'createForm']);
$router->post('admin/branches/create', ['BranchController', 'createSubmit']);
$router->get('admin/branches/edit', ['BranchController', 'editForm']);
$router->post('admin/branches/edit', ['BranchController', 'editSubmit']);
$router->post('admin/branches/toggle-status', ['BranchController', 'toggleStatus']);

// Workers (AdminWorkerController — renamed from WorkerController to avoid
// colliding with WorkerPortalController, the worker's own dashboard)
$router->get('admin/workers', ['AdminWorkerController', 'index']);
$router->get('admin/workers/create', ['AdminWorkerController', 'createForm']);
$router->post('admin/workers/create', ['AdminWorkerController', 'createSubmit']);
$router->get('admin/workers/edit', ['AdminWorkerController', 'editForm']);
$router->post('admin/workers/edit', ['AdminWorkerController', 'editSubmit']);
$router->post('admin/workers/toggle-status', ['AdminWorkerController', 'toggleStatus']);
$router->post('admin/workers/enable-login', ['AdminWorkerController', 'enableLogin']);
$router->post('admin/workers/reset-password', ['AdminWorkerController', 'resetPassword']);

// Cashiers (admin-managed accounts)
$router->get('admin/cashiers', ['AdminCashierController', 'index']);
$router->get('admin/cashiers/create', ['AdminCashierController', 'createForm']);
$router->post('admin/cashiers/create', ['AdminCashierController', 'createSubmit']);
$router->get('admin/cashiers/edit', ['AdminCashierController', 'editForm']);
$router->post('admin/cashiers/edit', ['AdminCashierController', 'editSubmit']);
$router->post('admin/cashiers/toggle-status', ['AdminCashierController', 'toggleStatus']);
$router->post('admin/cashiers/reset-password', ['AdminCashierController', 'resetPassword']);

// Reports — both are GET: viewing a report and exporting it as a PDF are
// both read-only actions, no data changes, so no CSRF needed.
$router->get('admin/reports', ['ReportController', 'index']);
$router->get('admin/reports/export', ['ReportController', 'exportPdf']);

// Business day closures — Admin reopens a closed day here.
$router->get('admin/closures', ['ClosureController', 'reopenForm']);
$router->post('admin/closures/reopen', ['ClosureController', 'reopenSubmit']);

// Audit log viewer — Admin-only, read-only.
$router->get('admin/audit-log', ['AuditLogController', 'index']);

// --- Cashier routes ---
$router->get('cashier/dashboard', ['CashierController', 'dashboard']);
$router->get('cashier/heartbeat', ['CashierController', 'heartbeat']); // Heartbeat for live updates

// Branch choice — cashiers rotate between branches and pick one fresh
// each business day, before they can reach anything else.
$router->get('cashier/choose-branch', ['CashierController', 'chooseBranchForm']);
$router->post('cashier/choose-branch', ['CashierController', 'chooseBranchSubmit']);

$router->get('cashier/sales', ['CashierController', 'todayRecords']);
$router->get('cashier/sales/create', ['CashierController', 'saleForm']);
$router->post('cashier/sales/create', ['CashierController', 'saleSubmit']);
$router->get('cashier/sales/edit', ['CashierController', 'editSaleForm']);
$router->post('cashier/sales/edit', ['CashierController', 'editSaleSubmit']);
$router->post('cashier/sales/close', ['CashierController', 'closeDay']);

$router->get('cashier/reports', ['CashierController', 'reports']);

// --- Worker routes (worker's OWN dashboard/reports — WorkerPortalController) ---
$router->get('worker/dashboard', ['WorkerPortalController', 'dashboard']);
$router->get('worker/reports', ['WorkerPortalController', 'reports']);
$router->get('worker/reports/export', ['WorkerPortalController', 'exportPdf']); 
$router->get('worker/heartbeat', ['WorkerPortalController', 'heartbeat']);

$router->dispatch();