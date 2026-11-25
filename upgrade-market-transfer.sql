-- freedns.my - public give-away of a name (domain market, transfer mode).
--
-- The names carry an "mt_" prefix on purpose: an older market already owns the tables called
-- market_listings / market_applications, so reusing those names silently did nothing and the
-- columns this feature needs were never created.

CREATE TABLE IF NOT EXISTS mt_listings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id      INT UNSIGNED NOT NULL,
  domain_name    VARCHAR(190) NOT NULL,
  zone           VARCHAR(190) NOT NULL,
  from_email     VARCHAR(190) NOT NULL,
  from_user_id   INT UNSIGNED DEFAULT NULL,
  title          VARCHAR(190) NOT NULL DEFAULT '',
  note           TEXT,
  status         ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
  created_at     DATETIME NOT NULL,
  closed_at      DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_mt_domain (domain_id),
  KEY idx_mt_status (status),
  KEY idx_mt_from (from_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mt_applications (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id   INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED DEFAULT NULL,
  email        VARCHAR(190) NOT NULL,
  username     VARCHAR(64) NOT NULL DEFAULT '',
  reason       TEXT,
  status       ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL,
  decided_at   DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mt_apply (listing_id, email),
  KEY idx_mt_listing (listing_id),
  KEY idx_mt_astatus (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;