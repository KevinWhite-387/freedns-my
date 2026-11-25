-- freedns.my - upgrade for mail forwarding through Cloudflare Email Routing.
--
-- A name under a suffix that is published by Cloudflare can receive mail: the holder types the address he
-- wants the mail forwarded to, and the registry creates the rule at Cloudflare for him. Cloudflare asks
-- that destination address to confirm once (it mails a link), and the rules themselves live at Cloudflare.
--
-- Only Cloudflare suffixes can do this: DNSHE has no equivalent service.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Duplicate column"/"Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS mail_routes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  local_part   VARCHAR(64)  NOT NULL,
  forward_to   VARCHAR(190) NOT NULL,
  cf_rule_id   VARCHAR(64)  DEFAULT NULL,
  status       VARCHAR(16)  NOT NULL DEFAULT 'pending',
  note         VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME     NOT NULL,
  updated_at   DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mail_route (domain_id, local_part),
  KEY idx_mail_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE zones
  ADD COLUMN cf_account_id VARCHAR(64) DEFAULT NULL AFTER cf_zone_id;

-- status of a route: pending - created here, waiting for Cloudflare (or for the confirmation of the
--                             destination address)
--                    active  - the rule exists at Cloudflare
--                    error   - Cloudflare refused it; note holds its wording
--
-- cf_account_id is remembered per zone because the Email Routing API addresses the account, not the zone.
