-- freedns.my - upgrade for the live chat (support chat with auto-refresh, no JavaScript).
--
-- A signed-in console user opens a private thread with the registry; a visitor who is not signed in may
-- leave a message with a nickname and an address. The operator answers in the back office, and when the
-- other side is not looking at the page the reply is also sent by mail (like the transfer tickets).
--
-- The page refreshes itself with <meta http-equiv="refresh">, so the site stays free of JavaScript.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Table already exists" on a second run is harmless.

CREATE TABLE IF NOT EXISTS chat_threads (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED DEFAULT NULL,
  email           VARCHAR(190) NOT NULL,
  display_name    VARCHAR(190) DEFAULT NULL,
  language        VARCHAR(5)   NOT NULL DEFAULT 'en',
  status          VARCHAR(16)  NOT NULL DEFAULT 'open',
  last_from       VARCHAR(16)  DEFAULT NULL,
  last_message_at DATETIME     DEFAULT NULL,
  unread_staff    INT UNSIGNED NOT NULL DEFAULT 0,
  unread_user     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_chat_user (user_id),
  KEY idx_chat_status (status),
  KEY idx_chat_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id   INT UNSIGNED NOT NULL,
  author      VARCHAR(16)  NOT NULL,
  author_name VARCHAR(190) DEFAULT NULL,
  body        TEXT         NOT NULL,
  created_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_msg_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- thread status: open     - the conversation is running
--                archived - nobody wrote for a week, or the operator closed it
-- author:         user     - the signed-in account
--                 guest    - a visitor who left a message without an account
--                 staff    - the registry owner
-- unread_*        - how many messages the other side has not looked at yet (the chat list and the
--                   console badge are built from these)
