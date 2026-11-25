-- freedns.my - adds the "star us on GitHub and get an extra quota" columns.
--
-- Adds to "users":
--   * quota_bonus     - the extra allowance this account earned (added on top of its normal allowance)
--   * star_bonus_at   - when the bonus was granted
--   * star_bonus_repo - the repository it was granted for
--
-- Run this file when you already imported upgrade-quota.sql before this release; a fresh installation
-- gets the columns from schema.sql, and upgrade-quota.sql now contains them as well.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE users
  ADD COLUMN quota_bonus SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER quota_limit,
  ADD COLUMN star_bonus_at DATETIME DEFAULT NULL AFTER quota_bonus,
  ADD COLUMN star_bonus_repo VARCHAR(190) DEFAULT NULL AFTER star_bonus_at;
