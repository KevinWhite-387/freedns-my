-- freedns.my - automated abuse scanning of the sites published under registered names.
--
-- One row per check. A hit is RECORDED, never acted on by itself: the operator decides. That is
-- deliberate - a keyword match alone cannot tell a clinic explaining drug safety from a dealer.

CREATE TABLE IF NOT EXISTS abuse_scan (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain_id   INT UNSIGNED NOT NULL,
  domain_name VARCHAR(190) NOT NULL,
  url         VARCHAR(255) NOT NULL DEFAULT '',
  status      INT NOT NULL DEFAULT 0,          -- HTTP status, 0 when the fetch failed
  verdict     ENUM('clean','suspect','error') NOT NULL DEFAULT 'clean',
  score       INT NOT NULL DEFAULT 0,
  reasons     VARCHAR(512) NOT NULL DEFAULT '', -- "adult:3,gambling:1"
  sample      TEXT,                             -- a short excerpt around the first hit
  handled     ENUM('no','ignored','suspended','whitelisted') NOT NULL DEFAULT 'no',
  checked_at  DATETIME NOT NULL,
  handled_at  DATETIME DEFAULT NULL,
  handled_by  VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_scan_domain (domain_id),
  KEY idx_scan_verdict (verdict),
  KEY idx_scan_handled (handled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;