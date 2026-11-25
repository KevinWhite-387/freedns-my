-- freedns.my - upgrade for the market place (selling domains that were bought elsewhere).
--
-- Console accounts may offer a registrable domain they own elsewhere: registration date, expiry date,
-- registrar, transfer price and asking price, currency, a description and a negotiable flag. Every
-- listing is reviewed by hand before it appears; the operator can pin one to the top, the seller can
-- withdraw it or mark it sold. Buyers need an account to apply, and both sides then receive each other's
-- address by mail - the registry itself never takes part in the deal.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Table already exists" on a second run is harmless.

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

-- One row per "I want to buy this" - the registry only introduces the two sides by mail.
CREATE TABLE IF NOT EXISTS market_enquiries (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id    INT UNSIGNED NOT NULL,
  buyer_user    INT UNSIGNED DEFAULT NULL,
  buyer_email   VARCHAR(190) NOT NULL,
  buyer_name    VARCHAR(190) DEFAULT NULL,
  message       TEXT,
  mailed        TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_enq_listing (listing_id),
  KEY idx_enq_buyer (buyer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: pending   - the seller submitted it, the operator has not decided yet
--         active    - visible in the market
--         rejected  - refused (reject_reason holds why, the seller is mailed)
--         sold      - the seller marked it sold
--         withdrawn - the seller took it off the market
