-- freedns.my - upgrade for the reviewer role release.
--
-- Adds to "admins":
--   * zones - the suffixes a REVIEWER may handle, comma separated (for example "freedns.my,asia.ci").
--             An owner ignores this column: owners keep full access to everything.
--
-- How the roles work:
--   owner (is_owner = 1)    - everything: settings, announcement, zones, reserved names, mail,
--                             Cloudflare, rate limits, user accounts, administrators, audit log, export.
--   reviewer (is_owner = 0) - may decide new requests and record changes for the suffixes listed in
--                             "zones", may read those names, and may suspend or delete them. Everything
--                             else answers 403.
--
-- Existing rows are owners (is_owner already defaults to 0 only for new rows, so set them explicitly
-- if your installation added administrators without ticking the box):
--
--   UPDATE admins SET is_owner = 1 WHERE zones IS NULL AND is_owner = 0;   -- optional, see below
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE admins
  ADD COLUMN zones VARCHAR(255) DEFAULT NULL AFTER is_owner,
  ADD COLUMN gate_slug VARCHAR(64) DEFAULT NULL AFTER zones,
  ADD COLUMN gate_pass VARCHAR(255) DEFAULT NULL AFTER gate_slug;

-- gate_slug / gate_pass: an OPTIONAL entry key of one account - its own secret address parameter and its
-- own pass phrase, created by the owner under Administrators. The owner's master entry in the settings
-- table stays as it is, and such an entry may only sign in the account it belongs to.
