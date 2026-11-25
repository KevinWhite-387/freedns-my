-- freedns.my - adds a Cloudflare API token per suffix (zones.cf_token).
--
-- Why: a Cloudflare API token belongs to one account, and its "Zone Resources" can only list zones
-- of that account. When your suffixes are spread over several Cloudflare accounts (for example
-- freedns.my in one account and asia.ci in another), each suffix needs its own token. A suffix
-- without one falls back to the general token on the DNS page.
--
-- Run this file when you already imported upgrade-cloudflare.sql before this release; a fresh
-- installation gets the column from schema.sql, and upgrade-cloudflare.sql now contains it as well.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste the statement below);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE zones
  ADD COLUMN cf_token VARCHAR(190) DEFAULT NULL AFTER cf_zone_id;
