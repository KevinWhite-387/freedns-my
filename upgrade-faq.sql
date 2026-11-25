-- freedns.my - FAQ (frequently asked questions).
--
-- Two languages only (en / zh) as agreed: every text column exists twice, and the front page falls
-- back to English when the Chinese one is empty. Categories are optional; an item with no category
-- simply appears under "Other".

CREATE TABLE IF NOT EXISTS faq_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(64)  NOT NULL,
  title_en    VARCHAR(190) NOT NULL DEFAULT '',
  title_zh    VARCHAR(190) NOT NULL DEFAULT '',
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_faq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faq_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED DEFAULT NULL,
  question_en VARCHAR(255) NOT NULL DEFAULT '',
  question_zh VARCHAR(255) NOT NULL DEFAULT '',
  answer_en   MEDIUMTEXT,
  answer_zh   MEDIUMTEXT,
  sort_order  INT NOT NULL DEFAULT 0,
  is_public   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL,
  updated_at  DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_faq_cat (category_id),
  KEY idx_faq_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO faq_categories (slug, title_en, title_zh, sort_order, created_at) VALUES
  ('start',    'Getting started', '新手指南',   10, NOW()),
  ('dns',      'DNS and records', '解析与记录', 20, NOW()),
  ('account',  'Account',         '账号相关',   30, NOW()),
  ('mail',     'Mail service',    '邮件服务',   40, NOW())
ON DUPLICATE KEY UPDATE slug = slug;