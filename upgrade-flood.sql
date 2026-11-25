-- freedns.my - upgrade for the anti-flood protection release.
--
-- Adds the "rate_limits" table: one row per visitor address with the sliding window, the number of
-- hits and a possible block. The thresholds, the whitelist and the switch live in the settings table
-- and need no schema change.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste the CREATE TABLE below);
--   3. run it once. "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS rate_limits (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip             VARCHAR(45) NOT NULL,
  hits           INT UNSIGNED NOT NULL DEFAULT 0,
  window_started DATETIME    NOT NULL,
  blocked_until  DATETIME    DEFAULT NULL,
  strikes        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  note           VARCHAR(190) DEFAULT NULL,
  last_seen      DATETIME    NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_rate_ip (ip),
  KEY idx_rate_blocked (blocked_until),
  KEY idx_rate_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
