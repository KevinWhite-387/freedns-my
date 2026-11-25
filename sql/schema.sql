-- freedns.my - database schema
-- MySQL 5.7+ / MariaDB 10.2+ , utf8mb4
-- The installer (install.php) executes this file automatically.
--
-- Model: a registry that serves every approved name itself (A/AAAA/CNAME/TXT records),
-- with several zones (suffixes) such as freedns.my and asia.ci, and a review queue for
-- record changes proposed by the applicants.

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(32)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email         VARCHAR(190) DEFAULT NULL,
  is_owner      TINYINT(1)   NOT NULL DEFAULT 0,
  zones         VARCHAR(255) DEFAULT NULL,
  gate_slug     VARCHAR(64)  DEFAULT NULL,
  gate_pass     VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME     NOT NULL,
  last_login_at DATETIME     DEFAULT NULL,
  last_login_ip VARCHAR(45)  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zones (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                  VARCHAR(190) NOT NULL,
  title                 VARCHAR(190) DEFAULT NULL,
  title_zh              VARCHAR(190) DEFAULT NULL,
  description           TEXT,
  description_zh        TEXT,
  cf_zone_id            VARCHAR(64)  DEFAULT NULL,
  cf_token              VARCHAR(190) DEFAULT NULL,
  provider              VARCHAR(16)  DEFAULT NULL,
  dnshe_key             VARCHAR(190) DEFAULT NULL,
  dnshe_secret          VARCHAR(190) DEFAULT NULL,
  is_open               TINYINT(1)   NOT NULL DEFAULT 1,
  allow_subdomains      TINYINT(1)   NOT NULL DEFAULT 1,
  allow_ns              TINYINT(1)   NOT NULL DEFAULT 0,
  max_domains_per_email INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order            INT          NOT NULL DEFAULT 0,
  created_at            DATETIME     NOT NULL,
  updated_at            DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_zone_name (name),
  KEY idx_zone_open (is_open),
  KEY idx_zone_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domains (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(190) NOT NULL,
  label            VARCHAR(63)  NOT NULL,
  zone             VARCHAR(190) NOT NULL,
  language         VARCHAR(5)   NOT NULL DEFAULT 'en',
  reserved         TINYINT(1)   NOT NULL DEFAULT 0,
  proxied          TINYINT(1)   NOT NULL DEFAULT 0,
  delegated        TINYINT(1)   NOT NULL DEFAULT 0,
  nameservers      VARCHAR(512) DEFAULT NULL,
  glue             VARCHAR(512) DEFAULT NULL,
  status           ENUM('pending','active','rejected','suspended','deleted') NOT NULL DEFAULT 'pending',
  request_code     VARCHAR(20)  NOT NULL,
  provider_ref     VARCHAR(64)  DEFAULT NULL,
  provider_owned   TINYINT(1)   NOT NULL DEFAULT 0,
  ref_code         VARCHAR(32)  DEFAULT NULL,
  registrant_name  VARCHAR(190) NOT NULL,
  registrant_email VARCHAR(190) NOT NULL,
  organization     VARCHAR(190) DEFAULT NULL,
  country          VARCHAR(64)  DEFAULT NULL,
  justification    TEXT,
  public_note      TEXT,
  admin_note       TEXT,
  created_at       DATETIME     NOT NULL,
  updated_at       DATETIME     NOT NULL,
  decided_at       DATETIME     DEFAULT NULL,
  decided_by       VARCHAR(32)  DEFAULT NULL,
  created_ip       VARCHAR(45)  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_domain_name (name),
  UNIQUE KEY uniq_domain_code (request_code),
  KEY idx_domain_status (status),
  KEY idx_domain_zone (zone),
  KEY idx_domain_email (registrant_email),
  KEY idx_domain_label (label),
  KEY idx_domain_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live DNS records. "sub" is empty for the registered name itself (the apex) and holds a
-- single label for names such as www.myname.freedns.my.
CREATE TABLE IF NOT EXISTS records (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id  INT UNSIGNED NOT NULL,
  sub        VARCHAR(63)  NOT NULL DEFAULT '',
  type       ENUM('A','AAAA','CNAME','TXT') NOT NULL,
  value      VARCHAR(255) NOT NULL,
  ttl        INT UNSIGNED NOT NULL DEFAULT 3600,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_record (domain_id, sub, type, value),
  KEY idx_record_domain (domain_id),
  KEY idx_record_sub (sub),
  CONSTRAINT fk_record_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record changes proposed by the applicant and waiting for a human decision.
-- "snapshot" holds the complete proposed record set as JSON. The generated column keeps at
-- most one pending change per domain, whatever the concurrent requests do.
CREATE TABLE IF NOT EXISTS change_requests (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id      INT UNSIGNED NOT NULL,
  status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  snapshot       MEDIUMTEXT   NOT NULL,
  applicant_note VARCHAR(500) DEFAULT NULL,
  admin_note     VARCHAR(500) DEFAULT NULL,
  submitted_at   DATETIME     NOT NULL,
  submitted_ip   VARCHAR(45)  DEFAULT NULL,
  decided_at     DATETIME     DEFAULT NULL,
  decided_by     VARCHAR(32)  DEFAULT NULL,
  pending_domain INT UNSIGNED GENERATED ALWAYS AS (IF(status = 'pending', domain_id, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_change_pending (pending_domain),
  KEY idx_change_domain (domain_id),
  KEY idx_change_status (status),
  KEY idx_change_submitted (submitted_at),
  CONSTRAINT fk_change_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every DNS record the registry pushed to Cloudflare, with the identifier the API returned.
-- Suspending or deleting a name removes exactly these records, so anything the operator added
-- in the Cloudflare dashboard by hand is never touched.
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

-- Anti-flood counters: one row per visitor address, with the sliding window and a possible block.
CREATE TABLE IF NOT EXISTS rate_limits (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip             VARCHAR(45) NOT NULL,
  hits           INT UNSIGNED NOT NULL DEFAULT 0,
  window_started DATETIME    NOT NULL,
  blocked_until  DATETIME    DEFAULT NULL,
  strikes        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  note           VARCHAR(190) DEFAULT NULL,
  last_seen      DATETIME    NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_rate_ip (ip),
  KEY idx_rate_blocked (blocked_until),
  KEY idx_rate_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answers of external WHOIS/RDAP lookups, so a repeated query does not hit the other registry again.
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

-- One row per external lookup, for the per-address hourly limit.
CREATE TABLE IF NOT EXISTS whois_queries (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARCHAR(45)  NOT NULL,
  domain     VARCHAR(190) NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_whois_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transfers of a name to somebody else: a support ticket with a one-time code for the recipient.
CREATE TABLE IF NOT EXISTS transfer_requests (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id        INT UNSIGNED NOT NULL,
  requester_email  VARCHAR(190) NOT NULL,
  requester_user   INT UNSIGNED DEFAULT NULL,
  recipient_name   VARCHAR(190) NOT NULL,
  recipient_email  VARCHAR(190) NOT NULL,
  reason           TEXT,
  status           VARCHAR(16)  NOT NULL DEFAULT 'open',
  transfer_code    VARCHAR(190) DEFAULT NULL,
  issued_at        DATETIME     DEFAULT NULL,
  expires_at       DATETIME     DEFAULT NULL,
  decided_by       VARCHAR(64)  DEFAULT NULL,
  created_at       DATETIME     NOT NULL,
  updated_at       DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tr_domain (domain_id),
  KEY idx_tr_status (status),
  KEY idx_tr_recipient (recipient_email),
  KEY idx_tr_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfer_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id  INT UNSIGNED NOT NULL,
  author      VARCHAR(16)  NOT NULL,
  author_name VARCHAR(64)  DEFAULT NULL,
  body        TEXT         NOT NULL,
  mailed      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tm_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domains offered for sale by console accounts (bought elsewhere, reviewed by hand).
CREATE TABLE IF NOT EXISTS market_listings (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  seller_email    VARCHAR(190) NOT NULL,
  contact_email   VARCHAR(190) NOT NULL,
  domain          VARCHAR(190) NOT NULL,
  registered_on   DATE         DEFAULT NULL,
  expires_on      DATE         DEFAULT NULL,
  registrar       VARCHAR(190) DEFAULT NULL,
  transfer_price  DECIMAL(10,2) DEFAULT NULL,
  price           DECIMAL(10,2) DEFAULT NULL,
  currency        VARCHAR(8)   NOT NULL DEFAULT 'USD',
  negotiable      TINYINT(1)   NOT NULL DEFAULT 0,
  description     TEXT,
  status          VARCHAR(16)  NOT NULL DEFAULT 'pending',
  reject_reason   VARCHAR(255) DEFAULT NULL,
  pinned          TINYINT(1)   NOT NULL DEFAULT 0,
  views           INT UNSIGNED NOT NULL DEFAULT 0,
  decided_by      VARCHAR(64)  DEFAULT NULL,
  sold_at         DATETIME     DEFAULT NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_market_domain (domain),
  KEY idx_market_user (user_id),
  KEY idx_market_status (status, pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_enquiries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id  INT UNSIGNED NOT NULL,
  buyer_user  INT UNSIGNED DEFAULT NULL,
  buyer_email VARCHAR(190) NOT NULL,
  buyer_name  VARCHAR(190) DEFAULT NULL,
  message     TEXT,
  mailed      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_enq_listing (listing_id),
  KEY idx_enq_buyer (buyer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotions submitted for an extra registration allowance (the Shop).
CREATE TABLE IF NOT EXISTS shop_promos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  user_email  VARCHAR(190) NOT NULL,
  url         VARCHAR(255) NOT NULL,
  description TEXT,
  status      VARCHAR(16)  NOT NULL DEFAULT 'pending',
  reason      VARCHAR(255) DEFAULT NULL,
  bonus       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  decided_by  VARCHAR(64)  DEFAULT NULL,
  created_at  DATETIME     NOT NULL,
  decided_at  DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_promo_user (user_id),
  KEY idx_promo_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support chat (auto-refreshing pages, guests may leave a message).
CREATE TABLE IF NOT EXISTS chat_threads (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED DEFAULT NULL,
  email           VARCHAR(190) NOT NULL,
  display_name    VARCHAR(190) DEFAULT NULL,
  language        VARCHAR(5)   NOT NULL DEFAULT 'en',
  status          VARCHAR(16)  NOT NULL DEFAULT 'open',
  last_from       VARCHAR(16)  DEFAULT NULL,
  last_message_at DATETIME     DEFAULT NULL,
  unread_staff    INT UNSIGNED NOT NULL DEFAULT 0,
  unread_user     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_chat_user (user_id),
  KEY idx_chat_status (status),
  KEY idx_chat_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id   INT UNSIGNED NOT NULL,
  author      VARCHAR(16)  NOT NULL,
  author_name VARCHAR(190) DEFAULT NULL,
  body        TEXT         NOT NULL,
  created_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_msg_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(64) NOT NULL,
  setting_value MEDIUMTEXT,
  updated_at    DATETIME DEFAULT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrant accounts for the user console (/account/). A name is linked to an account by the
-- e-mail address it was requested with, so the old "request code + e-mail" channel keeps working.
CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(32)  NOT NULL,
  username_lower VARCHAR(32)  NOT NULL,
  email          VARCHAR(190) NOT NULL,
  email_lower    VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  has_password   TINYINT(1)   NOT NULL DEFAULT 1,
  github_id      VARCHAR(32)  DEFAULT NULL,
  github_login   VARCHAR(64)  DEFAULT NULL,
  github_linked_at DATETIME   DEFAULT NULL,
  quota_unlimited TINYINT(1)  NOT NULL DEFAULT 0,
  quota_limit     SMALLINT UNSIGNED DEFAULT NULL,
  quota_bonus     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  star_bonus_at   DATETIME    DEFAULT NULL,
  star_bonus_repo VARCHAR(190) DEFAULT NULL,
  invite_code     VARCHAR(32)  DEFAULT NULL,
  referred_by     VARCHAR(32)  DEFAULT NULL,
  status         ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  language       VARCHAR(5)   NOT NULL DEFAULT 'en',
  verify_token   VARCHAR(64)  DEFAULT NULL,
  verify_expires DATETIME     DEFAULT NULL,
  verify_sent_at DATETIME     DEFAULT NULL,
  reset_token    VARCHAR(64)  DEFAULT NULL,
  reset_expires  DATETIME     DEFAULT NULL,
  created_at     DATETIME     NOT NULL,
  created_ip     VARCHAR(45)  DEFAULT NULL,
  last_login_at  DATETIME     DEFAULT NULL,
  last_login_ip  VARCHAR(45)  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_user_username (username_lower),
  UNIQUE KEY uniq_user_email (email_lower),
  UNIQUE KEY uniq_user_github (github_id),
  KEY idx_user_status (status),
  KEY idx_user_verify (verify_token),
  KEY idx_user_reset (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Names the registry keeps for itself. They cannot be requested through the ordinary form;
-- the applicant has to tick the reserved-name option (on the request form) and these names may
-- then also be a single character long. Managed under Administration -> Reserved.
CREATE TABLE IF NOT EXISTS reserved_names (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone       VARCHAR(190) NOT NULL,
  label      VARCHAR(63)  NOT NULL,
  note       VARCHAR(190) DEFAULT NULL,
  created_by VARCHAR(32)  DEFAULT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_reserved_name (zone, label),
  KEY idx_reserved_zone (zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id       INT UNSIGNED DEFAULT NULL,
  admin_username VARCHAR(32)  NOT NULL DEFAULT 'system',
  action         VARCHAR(64)  NOT NULL,
  target_type    VARCHAR(32)  DEFAULT NULL,
  target_id      INT UNSIGNED DEFAULT NULL,
  detail         TEXT,
  ip             VARCHAR(45)  DEFAULT NULL,
  created_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_log_created (created_at),
  KEY idx_log_admin (admin_id),
  KEY idx_log_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username   VARCHAR(64) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  success    TINYINT(1)  NOT NULL DEFAULT 0,
  created_at DATETIME    NOT NULL,
  PRIMARY KEY (id),
  KEY idx_attempt_ip_time (ip, created_at),
  KEY idx_attempt_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
