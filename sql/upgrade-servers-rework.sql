-- freedns.my - rework of the cloud-server binding: the platform binds, the operator assigns.
--
-- What changes and why
--   * a holder no longer hands over an API key: only the PLATFORM key is used, so the columns that held an
--     encrypted holder key are dropped (as agreed: start over, and no stale secrets stay behind);
--   * the operator attaches an instance in the back office and then ASSIGNS it to an account, with one of
--     two levels:
--         read - the holder sees state, specs, expiry and usage, and nothing else
--         full - the holder may also start, stop, restart, back up and change the firewall
--     (reinstalling and restoring still sit behind their own switch in the back office);
--   * a holder may ASK for a server: either naming one he already bought (with its instance id) or asking
--     the operator to pick one. The request lands in the back office.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). Running it twice is harmless except for the two tables it empties - those are emptied on
-- purpose, see the next lines.

-- Start over: the old rows were made for the holder-key model and would be unusable (and their keys are
-- dropped below anyway). The audit log keeps the history of what happened.
TRUNCATE TABLE server_actions;
TRUNCATE TABLE server_bindings;

ALTER TABLE server_bindings
  DROP COLUMN key_cipher,
  DROP COLUMN key_hint,
  MODIFY COLUMN user_id INT UNSIGNED DEFAULT NULL,
  ADD COLUMN access_level VARCHAR(16) NOT NULL DEFAULT 'read' AFTER user_id,
  ADD COLUMN assigned_by VARCHAR(64) DEFAULT NULL AFTER access_level,
  ADD COLUMN assigned_at DATETIME DEFAULT NULL AFTER assigned_by,
  ADD COLUMN request_id INT UNSIGNED DEFAULT NULL AFTER assigned_at;

CREATE TABLE IF NOT EXISTS server_requests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  remote_id   VARCHAR(64)  DEFAULT NULL,        -- the instance he names, or NULL when he asks for one
  purpose     TEXT         NOT NULL,            -- what he wants it for
  status      VARCHAR(16)  NOT NULL DEFAULT 'pending',
  reason      VARCHAR(255) DEFAULT NULL,
  binding_id  INT UNSIGNED DEFAULT NULL,        -- filled in once it is answered with an assignment
  decided_by  VARCHAR(64)  DEFAULT NULL,
  created_at  DATETIME     NOT NULL,
  decided_at  DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_srvreq_user (user_id),
  KEY idx_srvreq_status (status),
  CONSTRAINT fk_srvreq_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: pending | approved (a server was assigned) | rejected | withdrawn
-- access_level: read | full   (see the comment at the top)
