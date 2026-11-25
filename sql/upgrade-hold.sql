-- freedns.my - upgrade for the holding page (maintenance / under attack).
--
-- When the registry cannot serve normally - it is being hammered, an upstream is down, or the operator is
-- repairing something - every visitor gets a calm "please wait" page with a REFERENCE CODE. The registry
-- writes down what it knows about that visit (time, address, page asked for, browser) under that code, and
-- the visitor can mail the code in; the operator looks it up in the back office.
--
-- The code is short and readable on purpose, because people retype it from a screen into a mail.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS hold_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(32)  NOT NULL,
  reason     VARCHAR(32)  NOT NULL DEFAULT 'hold',
  path       VARCHAR(255) DEFAULT NULL,
  ip         VARCHAR(45)  DEFAULT NULL,
  agent      VARCHAR(255) DEFAULT NULL,
  user_id    INT UNSIGNED DEFAULT NULL,
  referer    VARCHAR(255) DEFAULT NULL,
  status     VARCHAR(16)  NOT NULL DEFAULT 'open',
  note       VARCHAR(255) DEFAULT NULL,
  handled_by VARCHAR(64)  DEFAULT NULL,
  handled_at DATETIME     DEFAULT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_hold_code (code),
  KEY idx_hold_status (status),
  KEY idx_hold_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- reason: hold     - the operator switched the holding page on
--         flood    - the visitor hit the rate limit
--         upstream - the database or a provider is not answering
--         attack   - the site is being hammered (set by hand or by the flood guard)
--
-- status: open     - nobody looked at it yet
--         seen     - somebody opened the code in the back office
--         done     - handled (note says what was done)
--
-- The visitor row is kept on purpose: it is what lets the operator tell a curious user from an attacker,
-- so nothing here is written from the public side except through the page itself.
