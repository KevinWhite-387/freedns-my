-- freedns.my - one file to import, corrected for THIS database.
--
-- TWO THINGS WERE WRONG BEFORE, both fixed here:
--   1. the settings table uses the columns setting_key / setting_value (not name / value), which is why the
--      settings block failed with "Unknown column 'name'";
--   2. the importer stops the whole file at the first error, so nothing was created at all. The statements that
--      cannot fail (CREATE TABLE IF NOT EXISTS, guarded ALTERs) now come first, and everything touching the
--      settings table comes last.
--
-- HOW TO USE: phpMyAdmin -> select the freedns database -> Import -> this file -> Go.
-- Importing it twice is harmless. At the end four rows should read 24 / 6 / 2 / 1.

-- ==================================================================== 1. advertising

CREATE TABLE IF NOT EXISTS ad_orders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED DEFAULT NULL,
  slot          VARCHAR(32)  NOT NULL,
  title         VARCHAR(190) NOT NULL,
  url           VARCHAR(190) NOT NULL,
  note          VARCHAR(190) DEFAULT NULL,
  image_url     VARCHAR(190) DEFAULT NULL,
  months        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  amount        INT UNSIGNED NOT NULL DEFAULT 0,
  currency      VARCHAR(8)   NOT NULL DEFAULT 'CNY',
  contact_name  VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_way   VARCHAR(190) DEFAULT NULL,
  message       TEXT         DEFAULT NULL,
  status        VARCHAR(16)  NOT NULL DEFAULT 'pending',
  reason        VARCHAR(255) DEFAULT NULL,
  decided_by    VARCHAR(64)  DEFAULT NULL,
  paid_at       DATETIME     DEFAULT NULL,
  start_at      DATETIME     DEFAULT NULL,
  end_at        DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL,
  decided_at    DATETIME     DEFAULT NULL,
  reminded_at   DATETIME     DEFAULT NULL,
  clicks        INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_adorders_status (status),
  KEY idx_adorders_slot (slot),
  KEY idx_adorders_end (end_at),
  KEY idx_adorders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For a database where ad_orders already existed without these two columns.
SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE ad_orders ADD COLUMN reminded_at DATETIME DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_orders' AND COLUMN_NAME = 'reminded_at');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE ad_orders ADD COLUMN clicks INT UNSIGNED NOT NULL DEFAULT 0', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_orders' AND COLUMN_NAME = 'clicks');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

-- ==================================================================== 2. cloud server bindings

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE server_bindings ADD COLUMN key_cipher MEDIUMBLOB DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings' AND COLUMN_NAME = 'key_cipher');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE server_bindings ADD COLUMN key_hint VARCHAR(8) DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings' AND COLUMN_NAME = 'key_hint');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE server_bindings ADD COLUMN access_level VARCHAR(16) NOT NULL DEFAULT ''read''', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings' AND COLUMN_NAME = 'access_level');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE server_bindings ADD COLUMN assigned_at DATETIME DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings' AND COLUMN_NAME = 'assigned_at');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE server_bindings ADD COLUMN assigned_by VARCHAR(64) DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings' AND COLUMN_NAME = 'assigned_by');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE server_bindings ADD COLUMN request_id INT UNSIGNED DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings' AND COLUMN_NAME = 'request_id');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

CREATE TABLE IF NOT EXISTS server_requests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  remote_id   VARCHAR(64)  DEFAULT NULL,
  purpose     TEXT         NOT NULL,
  status      VARCHAR(16)  NOT NULL DEFAULT 'pending',
  reason      VARCHAR(255) DEFAULT NULL,
  binding_id  INT UNSIGNED DEFAULT NULL,
  decided_by  VARCHAR(64)  DEFAULT NULL,
  created_at  DATETIME     NOT NULL,
  decided_at  DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_srvreq_user (user_id),
  KEY idx_srvreq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================== 3. the two quotas on users

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN forward_quota SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'forward_quota');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

SET @fd_sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN mailbox_quota SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mailbox_quota');
PREPARE fd_stmt FROM @fd_sql; EXECUTE fd_stmt; DEALLOCATE PREPARE fd_stmt;

-- ==================================================================== 4. settings
-- The real column names of this database: setting_key / setting_value (+ updated_at).
-- These are only starting values; the advertising prices are 0, which means "not for sale" - set the real
-- ones in the back office (Advertising -> Selling: prices and terms).

INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
  ('ad_enabled', '1', NOW()),
  ('ad_currency', 'CNY', NOW()),
  ('ad_price_side_left', '0', NOW()),
  ('ad_price_side_right', '0', NOW()),
  ('ad_price_request_bottom', '0', NOW()),
  ('ad_price_console_middle', '0', NOW()),
  ('ad_price_bundle', '0', NOW()),
  ('ad_min_months', '1', NOW()),
  ('ad_max_months', '12', NOW()),
  ('ad_remind_days', '3', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- ==================================================================== 5. check
-- Expect 24 / 6 / 2 / 1.

SELECT 'ad_orders columns' AS what, COUNT(*) AS n FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_orders'
UNION ALL
SELECT 'binding key columns', COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings'
   AND COLUMN_NAME IN ('key_cipher','key_hint','access_level','assigned_at','assigned_by','request_id')
UNION ALL
SELECT 'users quota columns', COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
   AND COLUMN_NAME IN ('forward_quota','mailbox_quota')
UNION ALL
SELECT 'server_requests table', COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_requests';
