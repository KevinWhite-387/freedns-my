-- ============================================================================
-- freedns.my — 建立 reviews 表（星等評價）
-- 用途：/reviews.php（公開評價頁）與 admin/reviews.php（後台審核）
-- 特性：可重複執行（IF NOT EXISTS），不會動到既有資料
-- 前置：users 表必須已存在（評價會 JOIN users 取 username）
-- 用法：aaPanel → 資料庫 → 匯入此檔（或 phpMyAdmin → 匯入）
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL COMMENT '評價所屬的使用者（users.id）',
  `stars`      TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1~5 星',
  `body`       TEXT NULL COMMENT '評價內容（純文字，不含連結）',
  `status`     VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending / published / rejected',
  `note`       VARCHAR(255) NULL COMMENT '後台拒絕時填寫的理由',
  `decided_by` VARCHAR(64) NULL COMMENT '審核者（後台帳號名稱）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user` (`user_id`),
  KEY `idx_status_updated` (`status`, `updated_at`),
  KEY `idx_status_id` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='freedns.my 星等評價';

-- ---------------------------------------------------------------------------
-- 驗證（匯入後應顯示 1 列、9 個欄位）
-- ---------------------------------------------------------------------------
-- SHOW TABLES LIKE 'reviews';
-- SHOW COLUMNS FROM `reviews`;
-- SELECT COUNT(*) FROM `reviews`;

-- ---------------------------------------------------------------------------
-- 若日後想補欄位，請用 information_schema 守衛（aaPanel 匯入器遇錯會中止整檔）
-- 範例：
-- SET @s = (SELECT IF(COUNT(*)=0,
--   'ALTER TABLE `reviews` ADD COLUMN `hidden` TINYINT(1) NOT NULL DEFAULT 0',
--   'DO 0')
--   FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'hidden');
-- PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
-- ---------------------------------------------------------------------------
