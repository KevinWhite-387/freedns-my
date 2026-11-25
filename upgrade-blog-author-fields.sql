-- ============================================================================
-- 博客作者：可编辑的"认证内容"
--
-- 原来 blog_authors 只有 status / verified（能不能发帖、有没有徽章），
-- 但没有地方放"认证说明"本身。这三列补上：
--
--   badge   徽章文字（例如「官方」「认证作者」），显示在作者名旁边
--   bio     作者简介（纯文本，显示在文章页作者信息处）
--   website 作者主页 / 联系地址（可选，http(s) 开头）
--
-- 幂等 ✓ 可重复执行 ✓ 先判断列是否存在 ✓ 不使用 AFTER 子句 ✓
-- ============================================================================

SET NAMES utf8mb4;
SET @db := DATABASE();

-- badge
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'blog_authors' AND COLUMN_NAME = 'badge');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `blog_authors` ADD COLUMN `badge` VARCHAR(64) DEFAULT NULL COMMENT ''badge text next to the name''',
  'SELECT ''badge 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- bio
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'blog_authors' AND COLUMN_NAME = 'bio');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `blog_authors` ADD COLUMN `bio` TEXT COMMENT ''author biography, plain text''',
  'SELECT ''bio 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- website
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'blog_authors' AND COLUMN_NAME = 'website');
SET @sql := IF(@exists = 0,
  'ALTER TABLE `blog_authors` ADD COLUMN `website` VARCHAR(255) DEFAULT NULL COMMENT ''author homepage''',
  'SELECT ''website 已存在，跳过'' ');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 给已有的官方账号补一个默认徽章（只补空的 ✓）
UPDATE `blog_authors` SET `badge` = '官方 / Official'
 WHERE (`badge` IS NULL OR `badge` = '') AND `verified` = 1;

-- 验证
-- SHOW COLUMNS FROM blog_authors LIKE 'badge';
-- SHOW COLUMNS FROM blog_authors LIKE 'bio';
-- SHOW COLUMNS FROM blog_authors LIKE 'website';
-- SELECT user_id, status, verified, badge FROM blog_authors;
