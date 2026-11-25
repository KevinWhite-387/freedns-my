-- ============================================================================
-- FreeDns 博客（挂在主站 /blog/ 下）
--
-- 与主站共用 users 表与登录会话 ✓ 不建新账号体系 ✓
-- 用户默认不能发帖 ✓ 要 blog_authors 里有 approved 的行 ✓
-- 官方认证徽章 = blog_authors.verified = 1 ✓ 由站长勾 ✓
-- 站内信只用于系统通知（审核结果 / 官方公告）✓ 不做用户互发 ✓
-- 评论需要审核 ✓（设置可关）
--
-- 幂等 ✓ 可重复执行 ✓ 所有建表都带 IF NOT EXISTS ✓ 不修改已有表 ✗
-- ============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------ 文章
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`          VARCHAR(190) NOT NULL,
  `title`         VARCHAR(190) NOT NULL,
  `title_zh`      VARCHAR(190) DEFAULT NULL,
  `excerpt`       TEXT,
  `excerpt_zh`    TEXT,
  `body`          MEDIUMTEXT NOT NULL,
  `body_zh`       MEDIUMTEXT,
  `cover`         VARCHAR(255) DEFAULT NULL,
  `category_id`   INT UNSIGNED DEFAULT NULL,
  `author_id`     INT UNSIGNED NOT NULL,
  `status`        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `featured`      TINYINT(1) NOT NULL DEFAULT 0,
  `allow_comments` TINYINT(1) NOT NULL DEFAULT 1,
  `views`         INT UNSIGNED NOT NULL DEFAULT 0,
  `published_at`  DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL,
  `updated_at`    DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_slug` (`slug`),
  KEY `idx_blog_status` (`status`, `published_at`),
  KEY `idx_blog_featured` (`featured`, `published_at`),
  KEY `idx_blog_author` (`author_id`),
  KEY `idx_blog_category` (`category_id`),
  CONSTRAINT `fk_blog_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- 分类
CREATE TABLE IF NOT EXISTS `blog_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(190) NOT NULL,
  `name`       VARCHAR(190) NOT NULL,
  `name_zh`    VARCHAR(190) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ 标签
CREATE TABLE IF NOT EXISTS `blog_tags` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(190) NOT NULL,
  `name`       VARCHAR(190) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_tag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_post_tags` (
  `post_id` INT UNSIGNED NOT NULL,
  `tag_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `tag_id`),
  KEY `idx_blog_pt_tag` (`tag_id`),
  CONSTRAINT `fk_blog_pt_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_pt_tag` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ 评论
CREATE TABLE IF NOT EXISTS `blog_comments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `body`       TEXT NOT NULL,
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `admin_note` TEXT,
  `decided_by` VARCHAR(32) DEFAULT NULL,
  `decided_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `created_ip` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_blog_com_status` (`status`, `created_at`),
  KEY `idx_blog_com_post` (`post_id`, `status`),
  KEY `idx_blog_com_user` (`user_id`),
  CONSTRAINT `fk_blog_com_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_com_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- 作者 / 认证
CREATE TABLE IF NOT EXISTS `blog_authors` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `status`      ENUM('pending','approved','revoked') NOT NULL DEFAULT 'pending',
  `verified`    TINYINT(1) NOT NULL DEFAULT 0,
  `reason`      TEXT,
  `admin_note`  TEXT,
  `decided_by`  VARCHAR(32) DEFAULT NULL,
  `decided_at`  DATETIME DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_author_user` (`user_id`),
  KEY `idx_blog_author_status` (`status`),
  CONSTRAINT `fk_blog_author_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 站内信
CREATE TABLE IF NOT EXISTS `blog_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `subject`    VARCHAR(190) NOT NULL,
  `body`       TEXT NOT NULL,
  `kind`       VARCHAR(32) NOT NULL DEFAULT 'system',
  `link`       VARCHAR(255) DEFAULT NULL,
  `read_at`    DATETIME DEFAULT NULL,
  `created_by` VARCHAR(32) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_blog_msg_user` (`user_id`, `read_at`),
  CONSTRAINT `fk_blog_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 设置项
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('blog_enabled',              '1', NOW()),
  ('blog_title',                'FreeDns Blog', NOW()),
  ('blog_per_page',             '10', NOW()),
  ('blog_comments_need_review', '1', NOW()),
  ('blog_rss_items',            '20', NOW()),
  ('blog_author_requests_open', '1', NOW())
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- 默认分类（存在就不动 ✓）
INSERT IGNORE INTO `blog_categories` (`slug`, `name`, `name_zh`, `sort_order`, `created_at`) VALUES
  ('announcements', 'Announcements', '公告',     10, NOW()),
  ('tutorials',     'Tutorials',     '教程',     20, NOW()),
  ('changelog',     'Changelog',     '更新日志', 30, NOW());

-- 验证
-- SHOW TABLES LIKE 'blog_%';
-- SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'blog_%';
-- SELECT * FROM blog_categories;
