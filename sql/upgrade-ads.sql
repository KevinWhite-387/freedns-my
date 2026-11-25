-- freedns.my - paid advertising: orders.
--
-- A visitor (or a signed-in holder) picks a slot, writes the advertisement and sends the order. The registry
-- approves it, money is collected offline (bank / Alipay / WeChat - there is no payment gateway here), the
-- order is marked as paid, and from that moment the advertisement appears in its slot until the paid period
-- runs out, when it disappears by itself and the slot falls back to the hiring notice.
--
-- Prices are settings, per slot and per month (see below), so nothing here has to change when a price does.
--
-- How to run it: phpMyAdmin -> Import, or paste into the SQL box. Running it twice is harmless.

CREATE TABLE IF NOT EXISTS ad_orders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED DEFAULT NULL,          -- filled in when the applicant happened to be signed in
  slot          VARCHAR(32)  NOT NULL,              -- side_left | side_right | request_bottom | console_middle
  title         VARCHAR(190) NOT NULL,
  url           VARCHAR(190) NOT NULL,
  note          VARCHAR(190) DEFAULT NULL,
  image_url     VARCHAR(190) DEFAULT NULL,          -- an outside picture; the registry never stores images
  months        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  amount        INT UNSIGNED NOT NULL DEFAULT 0,    -- what it costs in total, in the smallest currency unit
  currency      VARCHAR(8)   NOT NULL DEFAULT 'CNY',
  contact_name  VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_way   VARCHAR(190) DEFAULT NULL,          -- QQ / WeChat / phone, whatever they prefer
  message       TEXT         DEFAULT NULL,
  status        VARCHAR(16)  NOT NULL DEFAULT 'pending',
      -- pending  = waiting for the registry to look at it
      -- approved = accepted, waiting for the money
      -- paid     = money received: from here the ad is live until end_at
      -- rejected / cancelled / expired
  reason        VARCHAR(255) DEFAULT NULL,
  decided_by    VARCHAR(64)  DEFAULT NULL,
  paid_at       DATETIME     DEFAULT NULL,
  start_at      DATETIME     DEFAULT NULL,
  end_at        DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL,
  decided_at    DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_adorders_status (status),
  KEY idx_adorders_slot (slot),
  KEY idx_adorders_end (end_at),
  KEY idx_adorders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prices, one per slot, per month. 0 means "not for sale" and the page then says "ask us".
-- Settings this feature uses (set them in the back office -> Advertising):
--   ad_enabled              '1'            the advertising page and the ordering form
--   ad_currency             'CNY'          shown next to every price
--   ad_price_side_left      '0'
--   ad_price_side_right     '0'
--   ad_price_request_bottom '0'
--   ad_price_console_middle '0'
--   ad_price_bundle         '0'            both rails together, per month (0 = no bundle offer)
--   ad_contact_note         ''             how to pay, written by the registry (bank, Alipay, WeChat)
--   ad_terms                ''             anything else the applicant should know
--   ad_min_months           '1'
--   ad_max_months           '12'
