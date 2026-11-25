-- ============================================================================
-- freedns.my — 开放平台（OAuth 2.0 授权码 + PKCE）
--
-- 第三方平台可以用「用 freedns.my 登录」接入：用户在我们的授权页同意后，对方拿到
-- access token（短期）+ refresh token（长期），再按 scope 读取/写入被授权的数据。
--
-- 四张表：
--   oauth_apps     第三方应用（提交 → 后台人工审核 → 通过后才有 client_id）
--   oauth_codes    一次性授权码（短时效，PKCE 绑定，用完即废）
--   oauth_tokens   access / refresh 令牌（只存哈希，明文只在签发那一次显示）
--   oauth_consents 用户对某应用的授权记录（可撤销；同 scope 二次授权不再打扰用户）
--
-- 特性：可重复执行（IF NOT EXISTS），不含 AFTER 子句（aaPanel 导入器遇错会整档中止）
-- 前置：users 表必须存在
-- 用法：aaPanel → 数据库 → 导入（或 phpMyAdmin）
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) 第三方应用
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oauth_apps` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(80)  NOT NULL COMMENT '应用名（授权页上展示给用户）',
  `owner_user_id`   INT UNSIGNED DEFAULT NULL COMMENT '提交者（users.id），站长手动添加时可为空',
  `contact_email`   VARCHAR(190) NOT NULL COMMENT '联系邮箱',
  `homepage`        VARCHAR(190) DEFAULT NULL COMMENT '应用主页',
  `description`     TEXT         DEFAULT NULL COMMENT '用途说明（审核用）',
  `redirect_uris`   TEXT         DEFAULT NULL COMMENT '回调地址白名单，一行一个，必须精确匹配',
  `scopes`          VARCHAR(255) NOT NULL DEFAULT '' COMMENT '允许的 scope，空格分隔',
  `status`          VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending / approved / rejected / revoked',
  `is_public`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=公共客户端（PKCE，无 secret）',
  `client_id`       CHAR(32)     NOT NULL,
  `client_secret_hash` CHAR(64)  DEFAULT NULL COMMENT 'sha256(secret)；公共客户端为空',
  `secret_hint`     VARCHAR(12)  DEFAULT NULL COMMENT 'secret 末 6 位，便于辨认',
  `review_note`     VARCHAR(255) DEFAULT NULL COMMENT '拒绝理由 / 审核备注',
  `decided_by`      VARCHAR(64)  DEFAULT NULL COMMENT '审核者（后台账号名）',
  `requests`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计请求数',
  `last_used_at`    DATETIME     DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL,
  `decided_at`      DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_owner` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开放平台：第三方应用';

-- ---------------------------------------------------------------------------
-- 2) 一次性授权码（authorization code）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oauth_codes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id`        INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `code_hash`     CHAR(64)     NOT NULL COMMENT 'sha256(code)',
  `redirect_uri`  VARCHAR(190) NOT NULL COMMENT '授权时用的回调地址，换令牌时必须一致',
  `scopes`        VARCHAR(255) NOT NULL DEFAULT '',
  `code_challenge` VARCHAR(128) DEFAULT NULL COMMENT 'PKCE',
  `code_challenge_method` VARCHAR(8) DEFAULT NULL COMMENT 'S256 / plain',
  `ip`            VARCHAR(45)  DEFAULT NULL,
  `expires_at`    DATETIME     NOT NULL COMMENT '通常 10 分钟',
  `used_at`       DATETIME     DEFAULT NULL COMMENT '已兑换过（只能用一次）',
  `created_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code_hash` (`code_hash`),
  KEY `idx_app_user` (`app_id`, `user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开放平台：一次性授权码';

-- ---------------------------------------------------------------------------
-- 3) 访问令牌 / 刷新令牌
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oauth_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id`       INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `kind`         VARCHAR(8)   NOT NULL DEFAULT 'access' COMMENT 'access / refresh',
  `token_hash`   CHAR(64)     NOT NULL COMMENT 'sha256(token)',
  `token_hint`   VARCHAR(12)  NOT NULL COMMENT '末 6 位，便于辨认',
  `scopes`       VARCHAR(255) NOT NULL DEFAULT '',
  `ip`           VARCHAR(45)  DEFAULT NULL,
  `expires_at`   DATETIME     NOT NULL,
  `revoked_at`   DATETIME     DEFAULT NULL,
  `last_used_at` DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_app_user` (`app_id`, `user_id`),
  KEY `idx_kind` (`kind`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开放平台：令牌';

-- ---------------------------------------------------------------------------
-- 4) 用户授权记录（可撤销；同 scope 再次授权不再弹确认页）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oauth_consents` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id`     INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `scopes`     VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL,
  `updated_at` DATETIME     NOT NULL,
  `revoked_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_app_user` (`app_id`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开放平台：用户授权';

-- ---------------------------------------------------------------------------
-- 验证（导入后应各显示 1 行）
-- ---------------------------------------------------------------------------
-- SHOW TABLES LIKE 'oauth_%';
-- SHOW COLUMNS FROM `oauth_apps`;
-- SELECT COUNT(*) FROM `oauth_apps`;

-- ---------------------------------------------------------------------------
-- 补列范例（日后扩字段用 information_schema 守卫，避免导入器中途报错）
-- SET @s = (SELECT IF(COUNT(*)=0,
--   'ALTER TABLE `oauth_apps` ADD COLUMN `logo_url` VARCHAR(190) DEFAULT NULL',
--   'DO 0')
--   FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oauth_apps' AND COLUMN_NAME = 'logo_url');
-- PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
-- ---------------------------------------------------------------------------
