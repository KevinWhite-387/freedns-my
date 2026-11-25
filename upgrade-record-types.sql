-- ============================================================================
-- 记录类型扩容 ＋ 开发者申请机制
--
-- 原来的 records.type 只是 ENUM('A','AAAA','CNAME','TXT')，所以 MX / SRV / CAA / PTR
-- 在代码里"支持"、在数据库里存不下。这里把它扩到全部 21 种。
--
-- 注意：扩 ENUM 是安全操作 ✓ 不会影响已有行 ✓
--       反方向（缩窄）会丢数据 ✗ 千万不要把这段回滚 ✗
--
-- 幂等 ✓ 可重复执行 ✓ 不使用 AFTER 子句 ✓
-- ============================================================================

SET NAMES utf8mb4;
SET @db := DATABASE();

-- ---------------------------------------------------------------- 1. 记录类型

ALTER TABLE `records`
  MODIFY COLUMN `type` ENUM(
    'A','AAAA','TXT','MX','CNAME','CAA','SRV',
    'NS','PTR','TLSA','SVCB','HTTPS',
    'CERT','DNSKEY','DS','LOC','NAPTR','OPENPGPKEY','SMIMEA','SSHFP','URL'
  ) NOT NULL;

-- 公开类型：所有人都能直接提交（其余类型需要开发者身份 ＋ 人工批准 ✓）
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('record_types_open', 'A,AAAA,TXT,MX,CNAME,SRV', NOW()),
  ('developer_auto_approve', '0', NOW())
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --------------------------------------------------------------- 2. 开发者身份

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'developer');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD COLUMN `developer` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''may use gated record types''',
  'SELECT ''users.developer 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'developer_since');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD COLUMN `developer_since` DATETIME DEFAULT NULL',
  'SELECT ''users.developer_since 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------- 3. 开发者权限申请表

CREATE TABLE IF NOT EXISTS `developer_requests` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `username`       VARCHAR(64)  NOT NULL DEFAULT '',
  `email`          VARCHAR(190) NOT NULL DEFAULT '',
  `domain_id`      INT UNSIGNED DEFAULT NULL,
  `domain_name`    VARCHAR(190) NOT NULL DEFAULT '',
  `record_type`    VARCHAR(16)  NOT NULL DEFAULT '',
  `record_sub`     VARCHAR(63)  NOT NULL DEFAULT '',
  `record_value`   TEXT,
  `reason`         TEXT,
  `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note`     TEXT,
  `decided_by`     VARCHAR(32)  DEFAULT NULL,
  `decided_at`     DATETIME     DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devreq_status` (`status`),
  KEY `idx_devreq_user` (`user_id`),
  KEY `idx_devreq_domain` (`domain_id`),
  CONSTRAINT `fk_devreq_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ 4. 验证

-- SELECT COLUMN_TYPE FROM information_schema.COLUMNS
--  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'type';
-- SHOW COLUMNS FROM users LIKE 'developer%';
-- SHOW TABLES LIKE 'developer_requests';
-- SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'record_types%';
