-- SalonFlow v3: Worker Record Confirmation
-- Run this on an EXISTING database (MariaDB / XAMPP):
--   mysql -u root -P 3307 salonflow < database/migration_v3_worker_confirmation.sql
-- Safe to run twice (uses IF NOT EXISTS). Every existing record is set to
-- 'accepted', so nobody's history disappears or gets blocked.
-- Fresh installs: schema.sql already includes all of this.

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS confirmation_status ENUM('pending','accepted','appealed','expired') NOT NULL DEFAULT 'accepted' AFTER is_locked,
    ADD COLUMN IF NOT EXISTS appeal_reason ENUM('wrong_amount','other') NULL AFTER confirmation_status,
    ADD COLUMN IF NOT EXISTS confirmation_deadline DATE NULL AFTER appeal_reason,
    ADD COLUMN IF NOT EXISTS responded_at DATETIME NULL AFTER confirmation_deadline,
    ADD COLUMN IF NOT EXISTS appeal_resolved_by BIGINT UNSIGNED NULL AFTER responded_at,
    ADD COLUMN IF NOT EXISTS appeal_resolved_at DATETIME NULL AFTER appeal_resolved_by;

ALTER TABLE transactions
    ADD INDEX IF NOT EXISTS idx_txn_worker_conf (worker_id, confirmation_status);
