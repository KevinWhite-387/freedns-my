-- freedns.my - upgrade for the registration-quota release.
--
-- Adds to "users":
--   * quota_unlimited - 1 = this account may register as many names as it likes
--   * quota_limit     - a personal total for this account; NULL = use the global default
--
-- Every request is counted per E-MAIL ADDRESS across all suffixes (pending, active and suspended names
-- count; rejected and deleted ones do not). Accounts that already existed when the quota was switched on
-- are marked unlimited by the settings page, so nothing changes for them.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE users
  ADD COLUMN quota_unlimited TINYINT(1) NOT NULL DEFAULT 0 AFTER github_linked_at,
  ADD COLUMN quota_limit SMALLINT UNSIGNED DEFAULT NULL AFTER quota_unlimited,
  ADD COLUMN quota_bonus SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER quota_limit,
  ADD COLUMN star_bonus_at DATETIME DEFAULT NULL AFTER quota_bonus,
  ADD COLUMN star_bonus_repo VARCHAR(190) DEFAULT NULL AFTER star_bonus_at;

-- quota_bonus / star_bonus_at / star_bonus_repo: the extra allowance an account earned by starring the
-- repository on GitHub (Settings -> GitHub star bonus). It is added on top of the normal allowance.
