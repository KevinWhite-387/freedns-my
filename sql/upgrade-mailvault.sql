-- freedns.my - upgrade for the mail-forwarding request flow and the mail vault.
--
-- Two things in one file, because they belong to the same feature:
--
--   1. REQUEST + QUOTA. Mail forwarding is no longer open to everybody: a holder sends a ticket explaining
--      what he needs it for, the operator approves it AND sets how many addresses that account may keep.
--      Without an approved ticket (forward_quota = 0) the console refuses to create a forwarding address.
--
--   2. THE VAULT. Every message that we ever get our hands on is stored encrypted, and looking at one needs
--      the operator's master password; a single message can additionally be locked with its own password.
--      Losing a password does not open anything: the reset link is mailed to postmaster@freedns.my only, and
--      3008@live.cn is told that a reset happened (without the link).
--
--      NOTE, honestly: with Cloudflare Email Routing the mail goes straight from Cloudflare to the holder's
--      mailbox - our server never sees the body. So the vault is empty until the mail itself is routed
--      through us (own MX or CF Email Workers). The tables are ready for that day; nothing here pretends
--      that content is being stored today.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists"/"Duplicate column" on a second run is harmless.

CREATE TABLE IF NOT EXISTS mail_forward_requests (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  domain_id    INT UNSIGNED DEFAULT NULL,     -- the name he wants to forward from, if he named one
  purpose      TEXT         NOT NULL,         -- what the addresses are for
  wanted       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status       VARCHAR(16)  NOT NULL DEFAULT 'pending',
  quota        SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- what the operator granted
  reason       VARCHAR(255) DEFAULT NULL,
  decided_by   VARCHAR(64)  DEFAULT NULL,
  created_at   DATETIME     NOT NULL,
  decided_at   DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_fwdreq_user (user_id),
  KEY idx_fwdreq_status (status),
  CONSTRAINT fk_fwdreq_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- How many forwarding addresses an account may keep. 0 = forwarding not granted (the default).
ALTER TABLE users
  ADD COLUMN forward_quota SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER referred_by;

-- Every message we are allowed to keep, encrypted with the site key (AES-256-GCM, see inc/rainyun.php).
-- body_cipher holds the whole message; nothing is stored in clear text.
CREATE TABLE IF NOT EXISTS mail_archive (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED DEFAULT NULL,     -- the holder it belongs to, when it can be told
  direction    VARCHAR(8)   NOT NULL DEFAULT 'in',   -- in | out
  mail_from    VARCHAR(190) DEFAULT NULL,
  mail_to      VARCHAR(190) DEFAULT NULL,
  subject      VARCHAR(255) DEFAULT NULL,
  body_cipher  MEDIUMBLOB,                    -- encrypted body (may be NULL when only headers were kept)
  body_size    INT UNSIGNED NOT NULL DEFAULT 0,
  scan_state   VARCHAR(16)  NOT NULL DEFAULT 'unscanned',  -- unscanned | clean | dangerous
  review_state VARCHAR(16)  NOT NULL DEFAULT 'none',       -- none | queued | released | blocked
  review_note  VARCHAR(255) DEFAULT NULL,
  pass_hash    VARCHAR(255) DEFAULT NULL,     -- its own password, if the operator set one
  pass_hint    VARCHAR(64)  DEFAULT NULL,
  opened_at    DATETIME     DEFAULT NULL,
  opened_by    VARCHAR(64)  DEFAULT NULL,
  created_at   DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_archive_user (user_id),
  KEY idx_archive_review (review_state),
  KEY idx_archive_scan (scan_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time tokens for "I lost the master password / a message password". The token is only ever mailed to
-- postmaster@freedns.my; the second address gets a notice without it.
CREATE TABLE IF NOT EXISTS mail_vault_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope      VARCHAR(16)  NOT NULL,           -- master | entry
  ref_id     INT UNSIGNED DEFAULT NULL,       -- mail_archive.id when scope = entry
  token_hash CHAR(64)     NOT NULL,
  requested_by VARCHAR(64) DEFAULT NULL,
  used_at    DATETIME     DEFAULT NULL,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_vault_token (token_hash),
  KEY idx_vault_scope (scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings this feature uses (insert once; the back office can also write them):
--   mail_forward_enabled  '1'                       the request flow and the console page
--   mail_vault_hash       ''                        password_hash() of the master password (empty = vault closed)
--   mail_vault_postmaster 'postmaster@freedns.my'   where a reset link is sent
--   mail_vault_notify     '3008@live.cn'            who is told that a reset happened (never the link)
--   mail_scan_mode        'rules'                   rules | ai | off   (AI needs an endpoint + key later)
