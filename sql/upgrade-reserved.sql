-- freedns.my - upgrade for the reserved-names release.
--
-- Adds:
--   * reserved_names                       - names the registry keeps for itself, managed under
--                                            Administration -> Reserved; they can only be requested
--                                            with the reserved-name option on the request form, and
--                                            they may be a single character long
--   * domains.reserved                     - marks a request that came in through that option
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file;
--   3. run it once. The CREATE TABLE is skipped when the table exists; the ALTER statement reports
--      "duplicate column" on a second run, which is harmless.
--
-- Fresh installations run schema.sql through install.php and must not run this file.

CREATE TABLE IF NOT EXISTS reserved_names (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone       VARCHAR(190) NOT NULL,
  label      VARCHAR(63)  NOT NULL,
  note       VARCHAR(190) DEFAULT NULL,
  created_by VARCHAR(32)  DEFAULT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_reserved_name (zone, label),
  KEY idx_reserved_zone (zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE domains
  ADD COLUMN reserved TINYINT(1) NOT NULL DEFAULT 0 AFTER language;
