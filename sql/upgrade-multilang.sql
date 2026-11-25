-- freedns.my - upgrade for the multilanguage release (Chinese public pages).
--
-- Use this file when the database was created by the previous release (zones + records +
-- change_requests, but without the Chinese texts). It adds:
--   * zones.title_zh / zones.description_zh  - the Chinese title and description of a suffix
--   * domains.language                       - the language a request was submitted in, so the
--                                              decision mail can be written in that language
--
-- The Chinese texts of the pages themselves (announcement, policy, documentation) need no rows:
-- as long as settings has no * _zh row, the built-in Chinese defaults are used, and you can
-- override them any time under Settings in the back office.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file;
--   3. run it once. MySQL 5.7 has no "ADD COLUMN IF NOT EXISTS", so a second run reports
--      "duplicate column" errors - that is expected and harmless.
--
-- A database of the older "IP only" revision needs upgrade-ip-only.sql (which already contains
-- these two columns); a fresh installation uses schema.sql and must not run either file.

ALTER TABLE zones
  ADD COLUMN title_zh VARCHAR(190) DEFAULT NULL AFTER title,
  ADD COLUMN description_zh TEXT AFTER description;

ALTER TABLE domains
  ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'en' AFTER zone;

-- Code from this release writes domains.language on every new request, so import this file
-- BEFORE uploading the new PHP files (or immediately afterwards, before the next request).
--
-- If your build predates the review-queue hardening, you may also want these two (uncomment):
-- ALTER TABLE change_requests
--   ADD COLUMN pending_domain INT UNSIGNED GENERATED ALWAYS AS (IF(status = 'pending', domain_id, NULL)) STORED,
--   ADD UNIQUE KEY uniq_change_pending (pending_domain);
-- ALTER TABLE records MODIFY COLUMN position SMALLINT UNSIGNED NOT NULL DEFAULT 1;
