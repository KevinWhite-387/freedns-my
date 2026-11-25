-- freedns.my - upgrade for the user API (tokens that manage a name's records).
--
-- A console account can create a token and then drive its own names over HTTP:
--   GET  /api.php?action=domains
--   GET  /api.php?action=records&domain=myname.us.ci
--   POST /api.php  action=record.add|record.update|record.delete   (x-www-form-urlencoded or JSON)
-- Only tokens of the account that holds the name work, and only its own names are visible.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS api_tokens (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  label        VARCHAR(64)  DEFAULT NULL,
  token_hash   CHAR(64)     NOT NULL,
  token_hint   VARCHAR(16)  NOT NULL,
  calls        INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at DATETIME     DEFAULT NULL,
  last_ip      VARCHAR(45)  DEFAULT NULL,
  created_at   DATETIME     NOT NULL,
  revoked_at   DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_api_hash (token_hash),
  KEY idx_api_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only the hash of a token is stored; the token itself is shown once, when it is created.
