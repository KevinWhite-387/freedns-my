-- freedns.my - upgrade for the new mailbox service (BillionMail, send and receive).
--
-- What this adds
--   1. mail_box_requests - a holder asks for a mailbox; the operator approves it AND decides how many
--      mailboxes that account may keep (the same pattern as mail forwarding).
--   2. users.mailbox_quota - the number decided above. 0 = not granted (the default), so nothing can be
--      created before an approval exists.
--   3. mail_boxes         - the registry's own record of every mailbox: the address, its size, its state,
--      the mailbox itself lives in BillionMail, this table is what the console and the back office read.
--      The password is kept encrypted (AES-256-GCM with the site key, the same helper the cloud servers
--      use), because the holder may ask to SEE it again after proving he owns the account - a verification
--      code is mailed to his registered address first, and only then is it decrypted for him.
--   4. settings          - email_enabled, the read-only switch for the old forwarding page, and the
--      verification window.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or /selftest.php -> repair).
-- Running it twice only reports "already exists", which is harmless.

CREATE TABLE IF NOT EXISTS mail_box_requests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  domain_id   INT UNSIGNED DEFAULT NULL,      -- the name he wants the mailbox on, if he named one
  wanted      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  purpose     TEXT NOT NULL,
  status      VARCHAR(16) NOT NULL DEFAULT 'pending',   -- pending | approved | rejected | withdrawn
  quota       SMALLINT UNSIGNED NOT NULL DEFAULT 0,     -- mailboxes granted
  size_mb     INT UNSIGNED NOT NULL DEFAULT 0,          -- size granted per mailbox
  reason      VARCHAR(255) DEFAULT NULL,
  decided_by  VARCHAR(64) DEFAULT NULL,
  created_at  DATETIME NOT NULL,
  decided_at  DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_boxreq_user (user_id),
  KEY idx_boxreq_status (status),
  CONSTRAINT fk_boxreq_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- How many mailboxes an account may keep. 0 = the service has not been granted to it.
ALTER TABLE users
  ADD COLUMN mailbox_quota SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER forward_quota;

CREATE TABLE IF NOT EXISTS mail_boxes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  domain_id     INT UNSIGNED DEFAULT NULL,
  local_part    VARCHAR(64)  NOT NULL,
  address       VARCHAR(190) NOT NULL,          -- the whole address, lower case
  size_mb       INT UNSIGNED NOT NULL DEFAULT 1024,
  status        VARCHAR(16)  NOT NULL DEFAULT 'pending',  -- pending | active | suspended | deleted
  pass_cipher   MEDIUMBLOB   DEFAULT NULL,      -- encrypted mailbox password (revealed after verification)
  pass_set_at   DATETIME     DEFAULT NULL,
  verify_hash   CHAR(64)     DEFAULT NULL,      -- sha256 of the code mailed to the registered address
  verify_until  DATETIME     DEFAULT NULL,
  verified_at   DATETIME     DEFAULT NULL,
  remote_state  VARCHAR(32)  DEFAULT NULL,      -- what BillionMail answered last time we touched it
  note          VARCHAR(255) DEFAULT NULL,
  request_id    INT UNSIGNED DEFAULT NULL,
  created_by    VARCHAR(64)  DEFAULT NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mail_box (address),
  KEY idx_box_user (user_id),
  KEY idx_box_status (status),
  CONSTRAINT fk_box_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings this feature uses (written by the back office, listed here so nothing is a surprise):
--   email_enabled            '1'      the mailbox service (console page + requests + back office page)
--   email_verify_minutes     '15'     how long a "show me my password" code stays valid
--   email_default_size_mb    '1024'   size given to a new mailbox unless the approval says otherwise
--   forward_legacy_readonly  '0'      the old Mail Forwarding page: 1 = read only, no new addresses
--   forward_legacy_closed    '0'      1 = the old page redirects to the new Email page
--   forward_legacy_note      ''       the wording shown at the top of the old page (both languages below)
--   forward_legacy_note_zh   ''
