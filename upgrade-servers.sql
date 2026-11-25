-- freedns.my - upgrade for bound cloud servers (RainYun).
--
-- A console account hands over its own RainYun API key; the registry stores it encrypted, reads the
-- instance list with it, keeps the name, the specs, the state and the expiry date, and can later start,
-- stop, reboot or reinstall the instance. The platform never pays for anything: it only shows and reminds.
--
-- The instance itself is identified by its RainYun id; a "label" is whatever the holder wants to call it.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS server_bindings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  provider       VARCHAR(16)  NOT NULL DEFAULT 'rainyun',
  label          VARCHAR(64)  DEFAULT NULL,
  remote_id      VARCHAR(64)  NOT NULL,
  name           VARCHAR(190) DEFAULT NULL,
  specs          VARCHAR(190) DEFAULT NULL,
  status         VARCHAR(32)  DEFAULT NULL,
  expires_at     DATETIME     DEFAULT NULL,
  detail         TEXT,                          -- the raw answer, for the operator to look at
  key_cipher     VARBINARY(600) DEFAULT NULL,   -- that holder's key, AES-256-GCM
  key_hint       VARCHAR(16)  DEFAULT NULL,     -- last four characters, to tell keys apart
  reminded_at    DATETIME     DEFAULT NULL,     -- last expiry reminder (so it is not sent daily)
  last_synced_at DATETIME     DEFAULT NULL,
  sync_error     VARCHAR(255) DEFAULT NULL,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_binding (user_id, provider, remote_id),
  KEY idx_binding_expires (expires_at),
  CONSTRAINT fk_binding_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every action against a provider is written down, because it can destroy a customer's data.
CREATE TABLE IF NOT EXISTS server_actions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  binding_id INT UNSIGNED DEFAULT NULL,
  user_id    INT UNSIGNED DEFAULT NULL,
  action     VARCHAR(32)  NOT NULL,
  result     VARCHAR(16)  NOT NULL,
  message    VARCHAR(500) DEFAULT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_action_binding (binding_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings used by this feature (insert them once):
--   rainyun_enabled          '1'   the console page and the sync
--   rainyun_platform_key     ''    the operator's own key (read-only is enough), used by the back office
--   rainyun_allow_power      '1'   may a holder start/stop/reboot his own instance
--   rainyun_allow_reinstall  '0'   may a holder reinstall / change the OS (deletes data - off by default)
--   rainyun_remind_days      '7'   how many days before the expiry the reminder goes out
--
-- The key that encrypts the holders' API keys is generated on first use and kept in settings.secret_key.
-- It never lives in a file, so a copy of the site alone is not enough to read anybody's key.
