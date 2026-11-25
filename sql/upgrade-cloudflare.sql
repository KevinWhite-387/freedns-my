-- freedns.my - upgrade for the automatic Cloudflare publishing release.
--
-- Adds:
--   * zones.cf_zone_id    - the Cloudflare zone identifier per suffix
--   * domains.proxied     - whether the applicant asked for the orange cloud (proxy) or DNS only
--   * dns_records         - every record pushed to Cloudflare, with the identifier the API returned;
--                           suspending/deleting a name removes exactly these records and leaves
--                           anything added by hand in the dashboard alone
--
-- The API token, the enable switch and the cron key live in the settings table and need no schema
-- change.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Table already exists" / "duplicate column" on a second run is harmless.
--
-- Fresh installations run schema.sql through install.php and must not run this file.

CREATE TABLE IF NOT EXISTS dns_records (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id  INT UNSIGNED NOT NULL,
  provider   VARCHAR(16)  NOT NULL DEFAULT 'cloudflare',
  remote_id  VARCHAR(64)  NOT NULL,
  fqdn       VARCHAR(255) NOT NULL,
  sub        VARCHAR(63)  NOT NULL DEFAULT '',
  type       VARCHAR(8)   NOT NULL,
  value      VARCHAR(255) NOT NULL,
  proxied    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_dns_remote (provider, remote_id),
  KEY idx_dns_domain (domain_id),
  CONSTRAINT fk_dns_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE zones
  ADD COLUMN cf_zone_id VARCHAR(64) DEFAULT NULL AFTER description_zh;

-- Each suffix may carry its own API token: a Cloudflare token belongs to one account, so suffixes
-- spread over several Cloudflare accounts need separate tokens. Empty falls back to the general
-- token on the DNS page. (Already imported this file before? Run upgrade-cf-tokens.sql instead.)
ALTER TABLE zones
  ADD COLUMN cf_token VARCHAR(190) DEFAULT NULL AFTER cf_zone_id;

ALTER TABLE domains
  ADD COLUMN proxied TINYINT(1) NOT NULL DEFAULT 0 AFTER reserved;
