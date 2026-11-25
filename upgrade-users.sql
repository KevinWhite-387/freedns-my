-- freedns.my - upgrade for the user-console release.
--
-- Adds the "users" table: registrant accounts for /account/, registered with e-mail + password +
-- user name, linked to the names they requested through the e-mail address of the request.
-- The old "request code + e-mail" channel keeps working unchanged.
--
-- The admin entry gate needs no schema change: its switch, secret slug and pass phrase hash are
-- ordinary rows in the settings table.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Table already exists" on a second run is harmless.
--
-- Fresh installations run schema.sql through install.php and must not run this file.

CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(32)  NOT NULL,
  username_lower VARCHAR(32)  NOT NULL,
  email          VARCHAR(190) NOT NULL,
  email_lower    VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
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
  KEY idx_user_status (status),
  KEY idx_user_verify (verify_token),
  KEY idx_user_reset (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
