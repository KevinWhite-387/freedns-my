-- freedns.my - upgrade for the GitHub sign-in / binding release.
--
-- Adds to "users":
--   * has_password     - 0 for accounts created through GitHub, which have no local password yet
--   * github_id        - the numeric GitHub account id (unique)
--   * github_login     - the GitHub user name at the time of linking
--   * github_linked_at - when the link was made
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column/key" on a second run is harmless.

ALTER TABLE users
  ADD COLUMN has_password TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
  ADD COLUMN github_id VARCHAR(32) DEFAULT NULL AFTER has_password,
  ADD COLUMN github_login VARCHAR(64) DEFAULT NULL AFTER github_id,
  ADD COLUMN github_linked_at DATETIME DEFAULT NULL AFTER github_login,
  ADD UNIQUE KEY uniq_user_github (github_id);
