-- freedns.my - upgrade for domain sharing, step 1: READ-ONLY sharing.
--
-- The holder of a name can invite a console account to look at it: the invited account sees the name and
-- its records in its own console, but cannot change anything. Sharing is deliberately read-only here:
-- no write path consults this table, so a mistake in this feature cannot hand out rights over somebody
-- else's name.
--
-- Step 2 (writing) would extend this table with a level column and would have to touch user_domains() and
-- every page that writes records - that is a separate, carefully reviewed change.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS domain_access (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  invited_by VARCHAR(190) DEFAULT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_access (domain_id, user_id),
  KEY idx_access_user (user_id),
  CONSTRAINT fk_access_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
  CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invited_by holds the address of the holder who invited, so both sides can see where the access came from.
