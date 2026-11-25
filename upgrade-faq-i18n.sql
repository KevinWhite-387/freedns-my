-- freedns.my - FAQ translations, one row per language.
--
-- Rather than adding twelve more columns for the other four languages, every translation is a row.
-- Lookup order on the public page: the language being viewed, then English, then Chinese. A row
-- that exists in none of them is simply not shown, so a half-translated FAQ degrades quietly.

CREATE TABLE IF NOT EXISTS faq_i18n (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id   INT UNSIGNED NOT NULL,
  lang      VARCHAR(8)  NOT NULL,
  question  VARCHAR(255) NOT NULL DEFAULT '',
  answer    MEDIUMTEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_faq_i18n (item_id, lang),
  KEY idx_faq_i18n_lang (lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Traditional Chinese for the ten starter questions (matched by their English wording).
INSERT INTO faq_i18n (item_id, lang, question, answer)
SELECT i.id, 'zh-hant', v.q, v.a
FROM faq_items i
JOIN (
  SELECT 'How do I request a name?' AS qen, '怎麼申請一個網域？' AS q,
         '在申請頁選一個後綴，填一個標籤（不要帶點），填好記錄後提交。提交後會給一個申請編號，可以用它查詢進度。' AS a
  UNION ALL SELECT 'How long does approval take?', '審核要多久？',
         '一般一天內。指向可用網站的申請會更快；如果記錄指不到任何東西，可能會先讓你改。'
  UNION ALL SELECT 'Is it really free?', '真的免費嗎？',
         '真的。沒有費用、不用信用卡、也沒有試用期。後綴免費提供，每個後綴預設每人一個，操作員可以額外批准。'
  UNION ALL SELECT 'How do I add or change records?', '怎麼新增或修改解析？',
         '打開該網域的管理頁，在那裡改記錄。網域本身（@）至少要有一條 A、AAAA 或 CNAME。'
  UNION ALL SELECT 'How long do changes take to work?', '改了要多久生效？',
         '一分鐘內會推送到 DNS 服務商。別處的暫存可能還留著舊值，直到 TTL 過期。'
  UNION ALL SELECT 'Why does my name not resolve?', '為什麼不解析？',
         '確認網域已審核通過（狀態 active）、@ 上至少有一條 A/AAAA/CNAME，並且目標主機是通的。'
  UNION ALL SELECT 'I lost the e-mail I registered with.', '我註冊用的信箱沒了。',
         '用任意信箱寫信給我們，帶上申請編號。確認歸屬後可以把網域轉到新地址。'
  UNION ALL SELECT 'How do I delete a name?', '怎麼刪除網域？',
         '打開管理頁選擇刪除。記錄會從 DNS 服務商移除；免費子網域一旦註冊就無法釋放。'
  UNION ALL SELECT 'Can I receive mail on my name?', '能用我的網域收信嗎？',
         '只有走 Cloudflare 郵件路由的後綴支援轉發。只能收信轉發，不能發信。'
  UNION ALL SELECT 'Why was my forwarding refused?', '為什麼我的轉發被拒了？',
         '免費郵件路由需要目標信箱在服務商那邊點確認；而且只能轉發你自己網域下的地址。'
) AS v ON v.qen = i.question_en
ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer);