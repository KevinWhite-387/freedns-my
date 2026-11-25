-- ============================================================================
-- 给 zones 表加两个新 DNS 服务商所需的凭据列
--   Spaceship : spaceship_key / spaceship_secret   （X-Api-Key / X-Api-Secret）
--   ANYNS     : anyns_token                        （Authorization: Bearer adns_…）
--
-- 两个服务商都是「每个后缀单独一套凭据」✓ 空值表示该后缀不用这个服务商 ✓
-- 幂等 ✓ 重复执行安全 ✓ 不用 AFTER 子句 ✓ 先判断列是否存在 ✓
-- ============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- spaceship_key
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'zones' AND COLUMN_NAME = 'spaceship_key');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `zones` ADD COLUMN `spaceship_key` VARCHAR(190) DEFAULT NULL COMMENT ''Spaceship API key''',
  'SELECT ''spaceship_key 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- spaceship_secret
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'zones' AND COLUMN_NAME = 'spaceship_secret');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `zones` ADD COLUMN `spaceship_secret` VARCHAR(190) DEFAULT NULL COMMENT ''Spaceship API secret''',
  'SELECT ''spaceship_secret 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- anyns_token
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'zones' AND COLUMN_NAME = 'anyns_token');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `zones` ADD COLUMN `anyns_token` VARCHAR(190) DEFAULT NULL COMMENT ''ANYNS bearer token (adns_...)''',
  'SELECT ''anyns_token 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 全局开关（设置表里没有就插入 ✓ 有就不动 ✗ 免得覆盖你的选择）
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('spaceship_enabled', '0', NOW()),
  ('anyns_enabled',     '0', NOW())
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- 验证
-- SHOW COLUMNS FROM zones LIKE 'spaceship_%';
-- SHOW COLUMNS FROM zones LIKE 'anyns_%';
-- SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('spaceship_enabled','anyns_enabled');