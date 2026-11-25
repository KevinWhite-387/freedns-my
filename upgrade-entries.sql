-- freedns.my - adds the per-account entry key (admins.gate_slug and admins.gate_pass).
--
-- Why: a reviewer should not have to know the owner's master entry. Each account can get its own secret
-- address parameter and its own pass phrase, so a leaked reviewer key does not expose the master entry,
-- and that entry may only sign in the account it belongs to. The master entry in the settings table is
-- unchanged.
--
-- Run this file when you already imported upgrade-reviewer.sql before this release; a fresh installation
-- gets both columns from schema.sql, and upgrade-reviewer.sql now contains them as well.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE admins
  ADD COLUMN gate_slug VARCHAR(64) DEFAULT NULL AFTER zones,
  ADD COLUMN gate_pass VARCHAR(255) DEFAULT NULL AFTER gate_slug;
