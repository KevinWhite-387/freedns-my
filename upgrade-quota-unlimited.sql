-- ============================================================================
-- 两件事：邀请码下线 ＋ 域名配额无限
--
-- 1) 邀请码：注册页已经不再有输入框（代码层面已删），这里顺手把设置关掉。
--    历史数据（users.invite_code / referred_by）保留 ✓ 不影响任何东西 ✓
--
-- 2) 配额：quota_enabled = 0 时 quota_for_email() 直接返回 unlimited ✓
--    另外把每个后缀的 max_domains_per_email 设成 0（= 不限制）✓
--
-- 幂等 ✓ 可重复执行 ✓
-- ============================================================================

SET NAMES utf8mb4;

-- 配额总开关：关掉 = 无限 ✓
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('quota_enabled', '0', NOW())
ON DUPLICATE KEY UPDATE `setting_value` = '0';

-- 每个后缀的名称上限：0 = 不限制 ✓
UPDATE `zones` SET `max_domains_per_email` = 0, `updated_at` = NOW()
 WHERE `max_domains_per_email` <> 0;

-- 顺带把「每次请求的域名数量」也放开（如果这一项存在且大于 0）
UPDATE `settings` SET `setting_value` = '0'
 WHERE `setting_key` = 'max_domains_per_email' AND `setting_value` <> '0';

-- 邀请码：注册页已无入口，这里把旧设置标成关闭（存在才改 ✓）
UPDATE `settings` SET `setting_value` = '0'
 WHERE `setting_key` IN ('invite_required', 'invite_only') AND `setting_value` <> '0';

-- 验证
-- SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('quota_enabled','quota_per_email');
-- SELECT name, max_domains_per_email FROM zones ORDER BY name;