-- freedns.my - upgrade from the previous ("IP only") revision to the current one.
--
-- The previous revision stored one `addresses` row per submitted IP address and a single
-- `zone_name` in the settings table. The current revision has several `zones`, a general
-- `records` table (A, AAAA, CNAME, TXT with sub-names) and a `change_requests` review queue.
--
-- Use this file ONLY when the database was created by that previous revision (it must contain
-- an `addresses` table). A fresh installation uses schema.sql and must not run this file.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file;
--   3. run it once. MySQL 5.7 has no "ADD COLUMN IF NOT EXISTS", so a second run reports
--      "duplicate column/key" errors - that is expected and harmless;
--   4. log in and check every domain: the old addresses became A/AAAA records of the registered
--      name, and the `zone` of each domain was filled from the former zone_name setting;
--   5. afterwards you may drop the old table:  DROP TABLE addresses;

-- 1. the zones table, seeded from the former single zone name
CREATE TABLE IF NOT EXISTS zones (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                  VARCHAR(190) NOT NULL,
  title                 VARCHAR(190) DEFAULT NULL,
  title_zh              VARCHAR(190) DEFAULT NULL,
  description           TEXT,
  description_zh        TEXT,
  is_open               TINYINT(1)   NOT NULL DEFAULT 1,
  allow_subdomains      TINYINT(1)   NOT NULL DEFAULT 1,
  max_domains_per_email INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order            INT          NOT NULL DEFAULT 0,
  created_at            DATETIME     NOT NULL,
  updated_at            DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_zone_name (name),
  KEY idx_zone_open (is_open),
  KEY idx_zone_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO zones (name, title, description, is_open, allow_subdomains,
                   max_domains_per_email, sort_order, created_at, updated_at)
SELECT s.setting_value, s.setting_value, '', 1, 1, 0, 10, NOW(), NOW()
FROM settings s
WHERE s.setting_key = 'zone_name'
  AND s.setting_value <> ''
  AND NOT EXISTS (SELECT 1 FROM zones z WHERE z.name = s.setting_value);

-- 2. the zone and the submission language of every existing domain
--    (existing names are treated as English requests)
ALTER TABLE domains ADD COLUMN zone VARCHAR(190) NOT NULL DEFAULT '' AFTER label;
ALTER TABLE domains ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'en' AFTER zone;

UPDATE domains
SET zone = COALESCE((SELECT setting_value FROM settings WHERE setting_key = 'zone_name'), '')
WHERE zone = '';

ALTER TABLE domains ADD KEY idx_domain_zone (zone);

-- 3. the records table
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

-- 4. the former addresses become A/AAAA records of the registered name itself
INSERT INTO records (domain_id, sub, type, value, ttl, position, created_at, updated_at)
SELECT a.domain_id, '', IF(a.version = 'ipv6', 'AAAA', 'A'), a.address, 3600, a.position, NOW(), NOW()
FROM addresses a;

-- 5. the review queue for record changes
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

-- 6. housekeeping: the single zone name and the global record cap are now per zone / per setting
DELETE FROM settings WHERE setting_key = 'zone_name';

-- DROP TABLE IF EXISTS addresses;
