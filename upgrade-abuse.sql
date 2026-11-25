-- freedns.my - upgrade for abuse reports.
--
-- Anybody may report a name (phishing, spam, malware, trademark, ...) without an account; the report lands
-- in the back office, where the operator answers it. The registry itself only keeps what was reported and
-- what was decided, so a complaint can always be shown to an upstream provider.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS abuse_reports (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain        VARCHAR(190) NOT NULL,
  reporter      VARCHAR(190) NOT NULL,
  kind          VARCHAR(32)  NOT NULL DEFAULT 'other',
  message       TEXT         NOT NULL,
  status        VARCHAR(16)  NOT NULL DEFAULT 'open',
  note          VARCHAR(255) DEFAULT NULL,
  decided_by    VARCHAR(64)  DEFAULT NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_abuse_domain (domain),
  KEY idx_abuse_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kind:   phishing | spam | malware | trademark | illegal | other
-- status: open     - waiting for the operator
--         resolved - acted on (note says what was done: suspended, deleted, answered, ...)
--         rejected - the report was unfounded (note says why)
