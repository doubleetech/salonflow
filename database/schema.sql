-- SalonFlow v1.0 Database Schema
-- Engine: InnoDB | Charset: utf8mb4
-- Run this once against a fresh database, e.g.:
--   mysql -u root -P 3307 -p salonflow < schema.sql

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- businesses  (single row in practice, but modeled as a table so
-- settings are editable through normal CRUD, not hardcoded config)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS businesses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    logo_path       VARCHAR(255) NULL,
    phone           VARCHAR(30) NULL,
    address         VARCHAR(255) NULL,
    currency        VARCHAR(10) NOT NULL DEFAULT 'NGN',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- branches
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS branches (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    address         VARCHAR(255) NULL,
    phone           VARCHAR(30) NULL,
    status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_branches_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- users  (Admin + Cashier accounts only — workers never log in,
-- so they live in worker_profiles instead, not here)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('admin','cashier','worker') NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NULL UNIQUE,        -- admin login field
    username        VARCHAR(100) NULL UNIQUE,        -- cashier/worker login field
    password_hash   VARCHAR(255) NOT NULL,
    branch_id       INT UNSIGNED NULL,               -- superseded for cashiers by cashier_branch_assignments
                                                       -- (cashiers now pick a branch fresh each business day,
                                                       -- since they rotate between branches); left in place,
                                                       -- unused, rather than dropped, to avoid a destructive
                                                       -- schema change. Admin and Worker rows never use this.
    status          ENUM('pending_password_change','active','suspended','inactive') NOT NULL DEFAULT 'pending_password_change',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- worker_profiles
-- Workers now CAN log in (as of this update) — user_id links a profile
-- to its login account in `users` (role='worker'). Nullable because
-- existing workers created before this feature won't have one yet;
-- Admin can backfill login access for them via "Enable Login".
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_profiles (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NULL,
    branch_id               INT UNSIGNED NOT NULL,
    full_name               VARCHAR(150) NOT NULL,
    commission_percentage   DECIMAL(5,2) NOT NULL DEFAULT 0.00, -- current rate; historical txns keep their own frozen rate
    specialty               VARCHAR(150) NULL,
    employment_date         DATE NULL,
    notes                   TEXT NULL,
    status                  ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_workers_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_workers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- cashier_branch_assignments
-- Cashiers rotate between branches, so branch is no longer a fixed
-- fact on their account — it's a choice made fresh each business day,
-- locked for that whole calendar date once picked. One row per
-- cashier per date; UNIQUE constraint enforces "can't switch mid-day".
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cashier_branch_assignments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cashier_id      BIGINT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    business_date   DATE NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cba_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cba_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    UNIQUE KEY uq_cashier_date (cashier_id, business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- transactions  (one sale record copied from the physical book)
-- commission_percentage_applied + worker_commission + salon_share
-- are frozen at insert time and never recalculated retroactively.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id                              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id                       INT UNSIGNED NOT NULL,
    worker_id                       INT UNSIGNED NOT NULL,
    cashier_id                      BIGINT UNSIGNED NOT NULL,
    amount_made                     DECIMAL(12,2) NOT NULL,
    payment_method                  ENUM('cash','transfer','pos','combination') NOT NULL,
    amount_cash                     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_transfer                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_pos                      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    commission_percentage_applied   DECIMAL(5,2) NOT NULL,
    worker_commission                DECIMAL(12,2) NOT NULL,
    salon_share                     DECIMAL(12,2) NOT NULL,
    note                             VARCHAR(255) NULL,
    business_date                   DATE NOT NULL,       -- the day this belongs to (for closure grouping)
    is_locked                       TINYINT(1) NOT NULL DEFAULT 0, -- becomes 1 once business day is closed
    created_at                      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id),
    CONSTRAINT fk_txn_worker  FOREIGN KEY (worker_id)  REFERENCES worker_profiles(id),
    CONSTRAINT fk_txn_cashier FOREIGN KEY (cashier_id) REFERENCES users(id),
    INDEX idx_txn_branch_date (branch_id, business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- transaction_tips  (tips are optional, belong 100% to worker,
-- excluded from commission math — stored separately by design)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaction_tips (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id  BIGINT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tip_txn FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- daily_closures  (one row per branch per business day)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_closures (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id           INT UNSIGNED NOT NULL,
    business_date       DATE NOT NULL,
    total_revenue       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cash_total          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    transfer_total      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    pos_total           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    worker_commissions  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    salon_earnings      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tips_total          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status              ENUM('closed','reopened') NOT NULL DEFAULT 'closed',
    closed_by           BIGINT UNSIGNED NOT NULL,
    closed_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reopened_by         BIGINT UNSIGNED NULL,
    reopen_reason       VARCHAR(255) NULL,
    reopened_at         DATETIME NULL,
    CONSTRAINT fk_closure_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_closure_closer FOREIGN KEY (closed_by) REFERENCES users(id),
    CONSTRAINT fk_closure_reopener FOREIGN KEY (reopened_by) REFERENCES users(id),
    UNIQUE KEY uq_branch_date (branch_id, business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- audit_logs
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    user_label      VARCHAR(150) NULL,   -- snapshot of name/role in case user is later altered
    action          VARCHAR(100) NOT NULL,
    description     VARCHAR(500) NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- password_resets  (Admin-only OTP recovery)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    otp_code        VARCHAR(6) NOT NULL,
    expires_at      DATETIME NOT NULL,
    used            TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Seed: one business + one admin account so you can log in on
-- first run. CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN.
-- Default password below is: Admin@12345
-- ---------------------------------------------------------------
INSERT INTO businesses (name, currency) VALUES ('My Salon', 'NGN');

INSERT INTO users (role, full_name, email, password_hash, status)
VALUES (
    'admin',
    'Salon Owner',
    'owner@example.com',
    '$2b$10$pNWuSv6Mg5u3Z5WyT5mJJ./uUw.xJY3v1Yli4AHInT/0FbTiyV8U.', -- hash of 'Admin@12345', verified working with PHP password_verify()
    'pending_password_change'
);
