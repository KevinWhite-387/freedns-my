-- ============================================================================
-- freedns.my — Telegram 机器人
--
-- 设计要点（对应已确认的方案）：
--   * 只允许私聊（private chat）✓ 群里一律不响应 ✓
--   * 账号绑定用一次性口令 ✓ TG 里永远不出现密码 ✓
--   * 权限 = 网页角色为主 ＋ 可选的 TG 白名单（tg_whitelist）✓ 每次动作实时查库 ✓
--   * 所有特权动作写审计（tg_audit ✓ 同时会写进网站 log_action ✓）
--   * 主动通知靠定时任务读 tg_outbox ✓（webhook 不能主动推 ✓）
--
-- 五张表：
--   tg_links     TG 账号 ↔ 网站账号（一次性口令验证通过后才有行）
--   tg_codes     一次性绑定口令（10 分钟失效 ✓ 用一次即废 ✓）
--   tg_whitelist 可选的 TG 白名单（给「只做 TG 审核」的人 ✓ 站长在后台维护）
--   tg_state     多步流程的上下文（例如「拒绝 → 等理由」✓ 带过期时间 ✓）
--   tg_audit     TG 侧动作流水（谁 ✓ 在哪个聊天 ✓ 做了什么 ✓ 结果 ✓）
--   tg_outbox    待推送通知队列（由 tg-cron.php 消费 ✓）
--
-- 特性：可重复执行 ✓ 不含 AFTER 子句 ✓（aaPanel 导入器遇错会整档中止）
-- 前置：users 表必须存在
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) 绑定关系
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tg_links` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tg_id`         BIGINT       NOT NULL COMMENT 'Telegram 用户数字 id',
  `tg_username`   VARCHAR(64)  DEFAULT NULL COMMENT '绑定时的 @用户名，仅作显示',
  `tg_first_name` VARCHAR(128) DEFAULT NULL,
  `user_id`       INT UNSIGNED NOT NULL COMMENT 'users.id',
  `verified_at`   DATETIME     NOT NULL COMMENT '口令验证通过的时间',
  `last_seen_at`  DATETIME     DEFAULT NULL,
  `revoked_at`    DATETIME     DEFAULT NULL COMMENT '解除绑定',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tg` (`tg_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_live` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram：账号绑定';

-- ---------------------------------------------------------------------------
-- 2) 一次性绑定口令
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tg_codes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `code_hash`  CHAR(64)     NOT NULL COMMENT 'sha256(口令)；口令本身只在控制台显示一次',
  `code_hint`  VARCHAR(8)   NOT NULL COMMENT '末 4 位，便于辨认',
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `expires_at` DATETIME     NOT NULL COMMENT '默认 10 分钟',
  `used_at`    DATETIME     DEFAULT NULL,
  `used_by_tg` BIGINT       DEFAULT NULL COMMENT '被哪个 TG 账号用掉',
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code_hash` (`code_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram：一次性绑定口令';

-- ---------------------------------------------------------------------------
-- 3) 可选的 TG 白名单（站长在后台维护；与网页角色取并集）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tg_whitelist` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tg_id`      BIGINT       NOT NULL COMMENT 'Telegram 用户数字 id',
  `role`       VARCHAR(16)  NOT NULL DEFAULT 'reviewer' COMMENT 'reviewer / admin / owner',
  `label`      VARCHAR(64)  DEFAULT NULL COMMENT '备注：这个人是谁',
  `added_by`   VARCHAR(64)  DEFAULT NULL,
  `created_at` DATETIME     NOT NULL,
  `revoked_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tg` (`tg_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram：权限白名单';

-- ---------------------------------------------------------------------------
-- 4) 多步流程上下文（「拒绝 → 请填理由」这类）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tg_state` (
  `tg_id`      BIGINT       NOT NULL,
  `flow`       VARCHAR(32)  NOT NULL COMMENT '例如 reject_request / maint_window',
  `payload`    TEXT         DEFAULT NULL COMMENT 'JSON：上一步选中的对象',
  `step`       VARCHAR(32)  DEFAULT NULL,
  `expires_at` DATETIME     NOT NULL,
  `updated_at` DATETIME     NOT NULL,
  PRIMARY KEY (`tg_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram：流程上下文';

-- ---------------------------------------------------------------------------
-- 5) TG 侧审计（与网站 log_action 并行，便于在 TG 里回看）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tg_audit` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tg_id`      BIGINT       NOT NULL,
  `user_id`    INT UNSIGNED DEFAULT NULL COMMENT '当时绑定的网站账号',
  `role`       VARCHAR(16)  DEFAULT NULL COMMENT '当时判定的角色',
  `action`     VARCHAR(48)  NOT NULL COMMENT '例如 queue_approve / hold_on / link_ok',
  `target`     VARCHAR(190) DEFAULT NULL COMMENT '对象：域名 / 申请号 / 设置键',
  `result`     VARCHAR(16)  NOT NULL DEFAULT 'ok' COMMENT 'ok / denied / failed',
  `detail`     VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tg` (`tg_id`, `id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram：动作审计';

-- ---------------------------------------------------------------------------
-- 6) 通知队列（tg-cron.php 消费）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tg_outbox` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tg_id`      BIGINT       NOT NULL COMMENT '收件人 Telegram id',
  `kind`       VARCHAR(32)  NOT NULL COMMENT 'new_request / review_done / maint_change …',
  `text`       TEXT         NOT NULL COMMENT 'HTML 片段（已转义）',
  `sent_at`    DATETIME     DEFAULT NULL,
  `tries`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` VARCHAR(190) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`sent_at`, `id`),
  KEY `idx_tg` (`tg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram：通知队列';

-- ---------------------------------------------------------------------------
-- 验证（导入后应各显示 1 行，共 6 张表）
-- ---------------------------------------------------------------------------
-- SHOW TABLES LIKE 'tg\_%';
-- SHOW COLUMNS FROM `tg_links`;
-- SELECT COUNT(*) FROM `tg_links`;

-- ---------------------------------------------------------------------------
-- 补列范例（日后扩字段用 information_schema 守卫，避免导入器中途报错）
-- SET @s = (SELECT IF(COUNT(*)=0,
--   'ALTER TABLE `tg_links` ADD COLUMN `notify` TINYINT(1) NOT NULL DEFAULT 1',
--   'DO 0')
--   FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tg_links' AND COLUMN_NAME = 'notify');
-- PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
-- ---------------------------------------------------------------------------
