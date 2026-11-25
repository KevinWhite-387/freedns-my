-- freedns.my - upgrade for the publishing watchdog (the alert state of every name).
--
-- The endpoint /alert-cron.php is called by cron with the key from the settings; it reads what the
-- provider really holds for each active name, compares it with what is stored here, and mails the
-- operator when a name has a NEW problem. The hash of the last problem is remembered, so the same diff
-- is reported once, not every run.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS domain_checks (
  domain_id    INT UNSIGNED NOT NULL,
  ok           TINYINT(1)   NOT NULL DEFAULT 1,
  stored_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  live_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  missing      TEXT,
  extra        TEXT,
  error        VARCHAR(255) DEFAULT NULL,
  checked_at   DATETIME     NOT NULL,
  alert_hash   CHAR(32)     DEFAULT NULL,
  alerted_at   DATETIME     DEFAULT NULL,
  PRIMARY KEY (domain_id),
  KEY idx_checks_ok (ok),
  CONSTRAINT fk_check_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ok      : 1 when the published records are exactly the stored ones
-- alert_hash: md5 of the diff; a mail goes out only when it differs from the previous run
