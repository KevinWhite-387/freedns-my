-- freedns.my - upgrade for the WHOIS query page release.
--
-- Adds:
--   * whois_cache   - the answer of an external lookup, so a repeated query does not hit the other
--                     registry again (15 minutes by default)
--   * whois_queries - one row per external lookup, for the per-address hourly limit
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS whois_cache (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain     VARCHAR(190) NOT NULL,
  source     VARCHAR(16)  NOT NULL,
  payload    MEDIUMTEXT   NOT NULL,
  fetched_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_whois_domain (domain),
  KEY idx_whois_fetched (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whois_queries (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARCHAR(45)  NOT NULL,
  domain     VARCHAR(190) NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_whois_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
