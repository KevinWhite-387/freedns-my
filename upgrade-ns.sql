-- freedns.my - upgrade for own name servers (delegation) per suffix.
--
-- Some upstream platforms allow a suffix to be delegated: the holder points it at his own name servers
-- (for example Cloudflare's) and the registry stops answering for that name - it only publishes the NS
-- records (and glue) upstream. Suffixes whose zone is in the Public Suffix List can do this; the suffixes
-- this registry hands out itself cannot, so the option is switched on per suffix.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE zones
  ADD COLUMN allow_ns TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_subdomains;

ALTER TABLE domains
  ADD COLUMN delegated TINYINT(1) NOT NULL DEFAULT 0 AFTER proxied,
  ADD COLUMN nameservers VARCHAR(512) DEFAULT NULL AFTER delegated,
  ADD COLUMN glue VARCHAR(512) DEFAULT NULL AFTER nameservers;
