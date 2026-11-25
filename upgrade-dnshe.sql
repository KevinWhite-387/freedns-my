-- freedns.my - upgrade for the DNSHE publishing release.
--
-- Adds:
--   zones.provider          - which service publishes this suffix: 'cloudflare' or 'dnshe'
--                             (NULL = Cloudflare when a zone id is set, which keeps existing rows working)
--   zones.dnshe_key         - DNSHE API key of this suffix (empty = use the general one)
--   zones.dnshe_secret      - DNSHE API secret of this suffix (empty = use the general one)
--   domains.provider_ref    - DNSHE subdomain id of this name, so later syncs do not have to search
--   domains.provider_owned  - 1 when the registry registered that DNSHE subdomain itself, 0 when it was
--                             already there (a subdomain the registry did not create is never deleted)
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.

ALTER TABLE zones
  ADD COLUMN provider VARCHAR(16) DEFAULT NULL AFTER cf_token,
  ADD COLUMN dnshe_key VARCHAR(190) DEFAULT NULL AFTER provider,
  ADD COLUMN dnshe_secret VARCHAR(190) DEFAULT NULL AFTER dnshe_key;

ALTER TABLE domains
  ADD COLUMN provider_ref VARCHAR(64) DEFAULT NULL AFTER request_code,
  ADD COLUMN provider_owned TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_ref;
