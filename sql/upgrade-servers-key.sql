-- freedns.my - make server_bindings carry what the code needs (safe to run any number of times).
--
-- What went wrong before: the statements used "AFTER provider_ref", and this database does not have that
-- column - so the ALTER failed with "Unknown column 'provider_ref'" and the column was never added. Nothing
-- here depends on the position or on any other column existing.
--
-- Every block asks information_schema whether the column is already there and does nothing when it is, so the
-- import reports OK whether the database is fresh, half-migrated, or already up to date.
--
-- Why these columns:
--   key_cipher / key_hint  the holder's own RainYun API key (encrypted) and its four character hint
--   access_level           read = may only look, full = may operate (his own key means full)
--   assigned_at / assigned_by  who handed the instance over, and when
--   request_id             which approved request it came from
--
-- How to run it: phpMyAdmin -> Import, or paste the whole file into the SQL box. Then run the SELECT at the
-- bottom and compare its output with the list in the comment above.

-- ------------------------------------------------------------------ helper: add a column only if missing
-- (one block per column; the pattern is identical, only the name and the definition change)

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

-- ------------------------------------------------------------------ the request table the gate needs
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

-- ------------------------------------------------------------------ check
-- Compare this list with the comment at the top: key_cipher, key_hint, access_level, assigned_at,
-- assigned_by and request_id all have to appear.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'server_bindings'
 ORDER BY ORDINAL_POSITION;
