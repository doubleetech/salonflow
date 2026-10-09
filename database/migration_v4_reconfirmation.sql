-- SalonFlow v4: re-confirmation after edits / resolved appeals
-- Run AFTER migration_v3_worker_confirmation.sql, on an EXISTING database (MariaDB / XAMPP):
--   mysql -u root -P 3307 salonflow < database/migration_v4_reconfirmation.sql
-- Safe to run twice. Fresh installs: schema.sql already includes all of this.

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS appeal_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER appeal_reason,
    ADD COLUMN IF NOT EXISTS confirmation_started_at DATETIME NULL AFTER confirmation_deadline,
    ADD COLUMN IF NOT EXISTS revision_note VARCHAR(255) NULL AFTER appeal_resolved_at,
    ADD COLUMN IF NOT EXISTS revision_by BIGINT UNSIGNED NULL AFTER revision_note,
    ADD COLUMN IF NOT EXISTS revision_old_amount DECIMAL(12,2) NULL AFTER revision_by;

-- Records that were already appealed once before this update count as 1 appeal.
UPDATE transactions SET appeal_count = 1 WHERE appeal_reason IS NOT NULL AND appeal_count = 0;

-- Anything still waiting on a worker keeps its current confirmation window.
UPDATE transactions SET confirmation_started_at = created_at
 WHERE confirmation_status IN ('pending','expired') AND confirmation_started_at IS NULL;

-- Permanent record of every appeal that was closed (by Admin "Mark resolved" or by a cashier edit).
CREATE TABLE IF NOT EXISTS appeal_resolutions (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id   BIGINT UNSIGNED NOT NULL,
    resolution_type  ENUM('resolved','edited') NOT NULL,
    appeal_reason    ENUM('wrong_amount','other') NULL,
    appeal_number    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    note             VARCHAR(255) NOT NULL,
    old_amount       DECIMAL(12,2) NULL,
    new_amount       DECIMAL(12,2) NULL,
    resolved_by      BIGINT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resolutions_created (created_at),
    INDEX idx_resolutions_txn (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
