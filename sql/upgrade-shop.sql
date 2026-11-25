-- freedns.my - upgrade for the Shop: GitHub star bonus, promotion rewards and invite codes.
--
-- Shop (console) offers non-paid ways to raise the registration allowance:
--   1. star the GitHub repository       - once per account (users.quota_bonus, already in use)
--   2. promote the platform             - the holder submits a link, the operator approves it and the
--                                         reward is added to users.quota_bonus as well (shop_promos)
--   3. invite code                      - every account gets a personal code; registrations and requests
--                                         that arrive with it are attributed to that account, so the
--                                         console shows "I brought N people" and the back office can rank
--                                         everybody by it.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column"/"Table already exists" on a second run is harmless.

-- 1. promotion submissions (the "promote the platform" item of the shop)
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

-- 2. the personal invite code and who was brought in by whom
ALTER TABLE users
  ADD COLUMN invite_code VARCHAR(32) DEFAULT NULL AFTER quota_bonus,
  ADD COLUMN referred_by VARCHAR(32) DEFAULT NULL AFTER invite_code,
  ADD UNIQUE KEY uniq_user_invite (invite_code);

-- 3. requests that were submitted with a code by somebody who has no account (yet)
ALTER TABLE domains
  ADD COLUMN ref_code VARCHAR(32) DEFAULT NULL AFTER provider_owned;

CREATE INDEX idx_domain_ref ON domains (ref_code);

-- status of a promotion: pending - waiting for the operator
--                        approved - reward granted (bonus holds how much)
--                        rejected - refused, reason holds why
--
-- The invite code is generated on first use (see inc/shop.php); referred_by stores the code that was
-- used. Counting is done live: users whose referred_by is that code, and their active names.
