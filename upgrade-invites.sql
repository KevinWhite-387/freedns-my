-- freedns.my - invite gate for registration.
--
-- One row per code. A code is meant to be used once (max_uses defaults to 1); the note says who it was
-- sent to, so an operator can see the whole trail: GitHub request -> code -> the account that used it.
--
-- Import with:  mysql -uroot -p freedns_my < upgrade-invites.sql

CREATE TABLE IF NOT EXISTS `invites` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(32)  NOT NULL,
  `note`        VARCHAR(190) DEFAULT NULL,
  `max_uses`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `used_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `disabled`    TINYINT(1)   NOT NULL DEFAULT 0,
  `expires_at`  DATETIME     DEFAULT NULL,
  `created_by`  VARCHAR(64)  DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL,
  `last_used_at` DATETIME    DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invite_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `users` ADD COLUMN `invite_used` VARCHAR(32) DEFAULT NULL AFTER `referred_by`;

INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`)
VALUES ('invite_required', '1', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

SELECT COUNT(*) AS invites_table_ok FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invites';