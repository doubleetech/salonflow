-- SalonFlow v1.0 — Migration: Worker logins + Cashier branch rotation
--
-- Run this ONCE against your EXISTING salonflow database to add the new
-- features without losing your existing branches/workers/cashiers/sales.
-- Do NOT re-run schema.sql on an existing database — that would try to
-- recreate tables that already exist and could wipe your test data.
--
-- Usage:
--   mysql -u root -P 3307 salonflow < database/migration_v2.sql

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Allow 'worker' as a login role (existing 'admin'/'cashier' rows unaffected).
ALTER TABLE users MODIFY COLUMN role ENUM('admin','cashier','worker') NOT NULL;

-- 2. Link worker_profiles to a login account. NULL for existing workers —
--    use the new "Enable Login" button on the Workers screen to backfill.
ALTER TABLE worker_profiles ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE worker_profiles
    ADD CONSTRAINT fk_workers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 3. New table: which branch a cashier picked, for which business day.
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

SET FOREIGN_KEY_CHECKS = 1;

-- Note: existing cashiers keep whatever branch_id they already had on
-- `users`, but the app no longer reads it — cashiers now pick a branch
-- fresh each business day via cashier_branch_assignments instead. That
-- old column is left in place rather than dropped, since dropping columns
-- is a destructive, hard-to-undo operation and it costs nothing to leave
-- it there unused.
