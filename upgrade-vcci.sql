-- ============================================================================
-- vc.ci 登录 / 绑定 所需的列
--
--   users.vcci_id         vc.ci 的账号 id（userinfo 的 sub）✓ 唯一 ✓
--   users.vcci_email      vc.ci 那边的邮箱（只作展示 ✓ 不覆盖本地的 email ✓）
--   users.vcci_linked_at  绑定时间
--   users.vcci_migrated_at 上次自动迁移域名的时间
--
-- 幂等 ✓ 重复执行安全 ✓ 不用 AFTER 子句 ✓ 先判断列是否存在 ✓
-- ============================================================================

SET NAMES utf8mb4;
SET @db := DATABASE();

-- vcci_id
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'vcci_id');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD COLUMN `vcci_id` VARCHAR(64) DEFAULT NULL COMMENT ''vc.ci account id (userinfo sub)''',
  'SELECT ''vcci_id 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vcci_email
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'vcci_email');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD COLUMN `vcci_email` VARCHAR(190) DEFAULT NULL COMMENT ''e-mail at vc.ci''',
  'SELECT ''vcci_email 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vcci_linked_at
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'vcci_linked_at');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD COLUMN `vcci_linked_at` DATETIME DEFAULT NULL COMMENT ''when vc.ci was linked''',
  'SELECT ''vcci_linked_at 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vcci_migrated_at
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'vcci_migrated_at');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD COLUMN `vcci_migrated_at` DATETIME DEFAULT NULL COMMENT ''last automatic domain migration''',
  'SELECT ''vcci_migrated_at 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 唯一索引（一个 vc.ci 账号只能绑一个本地账号 ✓）
SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'uniq_user_vcci');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `uniq_user_vcci` (`vcci_id`)',
  'SELECT ''uniq_user_vcci 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 设置项：凭据（密钥类不在代码里 ✓ 全部由后台填写 ✓）
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('vcci_issuer', 'https://register.vc.ci', NOW()),
  ('vcci_client_id', '', NOW()),
  ('vcci_client_secret', '', NOW()),
  ('vcci_redirect_uri', 'https://freedns.my/account/vcci-callback.php', NOW()),
  ('vcci_auto_migrate', '1', NOW()),
  ('vcci_migrate_active_only', '1', NOW())
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- 验证
-- SHOW COLUMNS FROM users LIKE 'vcci_%';
-- SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vcci_%';

-- 修正已导入过的旧值（幂等 ✓ 只改这一项的空值或旧路径 ✓）
UPDATE `settings`
   SET `setting_value` = 'https://freedns.my/account/vcci-callback.php'
 WHERE `setting_key` = 'vcci_redirect_uri'
   AND (`setting_value` = '' OR `setting_value` = 'https://freedns.my/account/vcci.php');
-- 修正 issuer：真正的应用在 register.vc.ci，vc.ci 只是官网（幂等 ✓）
UPDATE `settings`
   SET `setting_value` = 'https://register.vc.ci'
 WHERE `setting_key` = 'vcci_issuer'
   AND (`setting_value` = '' OR `setting_value` = 'https://vc.ci');
-- vc.ci 数据库连接（自动迁移用 ✓ 只读 SELECT ✓）
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('vcci_db_host', '127.0.0.1', NOW()),
  ('vcci_db_name', 'vc_ci', NOW()),
  ('vcci_db_user', '', NOW()),
  ('vcci_db_pass', '', NOW())
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- 已导入过的话，至少把库名补成 vc_ci（幂等 ✓）
UPDATE `settings` SET `setting_value` = 'vc_ci'
 WHERE `setting_key` = 'vcci_db_name' AND (`setting_value` = '' OR `setting_value` = 'vc-ci');