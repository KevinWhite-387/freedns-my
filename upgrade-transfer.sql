-- freedns.my - upgrade for the transfer (ticket) release.
--
-- A transfer is a support ticket: the holder applies in the console, an owner replies in the back office
-- and either approves it (the registry mails a one-time transfer code to the recipient, valid for 72
-- hours) or rejects it. Only the account whose e-mail matches the recipient may accept, and while a
-- transfer is open or approved the records of that name are locked.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS transfer_requests (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id        INT UNSIGNED NOT NULL,
  requester_email  VARCHAR(190) NOT NULL,
  requester_user   INT UNSIGNED DEFAULT NULL,
  recipient_name   VARCHAR(190) NOT NULL,
  recipient_email  VARCHAR(190) NOT NULL,
  reason           TEXT,
  status           VARCHAR(16)  NOT NULL DEFAULT 'open',
  transfer_code    VARCHAR(190) DEFAULT NULL,
  issued_at        DATETIME     DEFAULT NULL,
  expires_at       DATETIME     DEFAULT NULL,
  decided_by       VARCHAR(64)  DEFAULT NULL,
  created_at       DATETIME     NOT NULL,
  updated_at       DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tr_domain (domain_id),
  KEY idx_tr_status (status),
  KEY idx_tr_recipient (recipient_email),
  KEY idx_tr_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfer_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT UNSIGNED NOT NULL,
  author     VARCHAR(16)  NOT NULL,
  author_name VARCHAR(64) DEFAULT NULL,
  body       TEXT         NOT NULL,
  mailed     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tm_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: open      - the holder applied, the operator has not decided yet
--         approved  - a transfer code was sent to the recipient, the clock runs (72 hours)
--         accepted  - the recipient confirmed, ownership moved
--         rejected  - the operator refused (decided_by holds the reason)
--         expired   - nobody accepted within the 72 hours
--         cancelled - the holder withdrew the request
--
-- transfer_code: the code the operator obtained MANUALLY in the DNSHE panel. The registry does not
-- validate it (it belongs to DNSHE); it only relays it to the recipient and remembers it for the record,
-- which is why it is stored as it was typed.
