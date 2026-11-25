<?php
declare(strict_types=1);

/**
 * freedns.my - support chat.
 *
 * A signed-in console account gets a private thread; a visitor may leave a message with a nickname and an
 * address. The page refreshes itself with <meta http-equiv="refresh">, so the site keeps working without
 * JavaScript, and when the other side is not on the page the message is also sent by mail.
 *
 * Rules: messages are limited in length and in frequency, threads nobody touched for a week are archived,
 * the holder can export his messages but never delete them.
 */

const FD_CHAT_MAX_LENGTH = 2000;
const FD_CHAT_MIN_SECONDS = 5;
const FD_CHAT_ARCHIVE_DAYS = 7;
const FD_CHAT_KEEP_MESSAGES = 300;

function chat_table_ready(): bool
{
    if (isset($GLOBALS['FD_CHAT_TABLE'])) {
        return (bool)$GLOBALS['FD_CHAT_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'chat\\_threads'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_CHAT_TABLE'] = $ready;

    return $ready;
}

function chat_enabled(): bool
{
    return setting_bool('chat_enabled', true) && chat_table_ready();
}

function chat_thread_by_id(int $id): ?array
{
    if (!chat_table_ready() || $id <= 0) {
        return null;
    }

    return db_one('SELECT * FROM chat_threads WHERE id = ?', [$id]);
}

function chat_messages(int $threadId, int $limit = FD_CHAT_KEEP_MESSAGES): array
{
    if (!chat_table_ready()) {
        return [];
    }

    $rows = db_all(
        'SELECT * FROM chat_messages WHERE thread_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit),
        [$threadId]
    );

    return array_reverse($rows);
}

/** The thread of one account, creating it when needed. */
function chat_thread_for_user(array $user, bool $create = true): ?array
{
    if (!chat_table_ready()) {
        return null;
    }

    $thread = db_one('SELECT * FROM chat_threads WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int)$user['id']]);
    if ($thread !== null || !$create) {
        return $thread;
    }

    db_query(
        'INSERT INTO chat_threads (user_id, email, display_name, language, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, \'open\', NOW(), NOW())',
        [(int)$user['id'], (string)$user['email'], (string)$user['username'], lang()]
    );

    return chat_thread_by_id((int)db()->lastInsertId());
}

/** The thread of a visitor, identified by e-mail address. */
function chat_thread_for_guest(string $email, string $name, bool $create = true): ?array
{
    if (!chat_table_ready()) {
        return null;
    }

    $thread = db_one(
        "SELECT * FROM chat_threads WHERE user_id IS NULL AND email = ? ORDER BY id DESC LIMIT 1",
        [strtolower(trim($email))]
    );
    if ($thread !== null || !$create) {
        return $thread;
    }

    db_query(
        'INSERT INTO chat_threads (user_id, email, display_name, language, status, created_at, updated_at)
         VALUES (NULL, ?, ?, ?, \'open\', NOW(), NOW())',
        [strtolower(trim($email)), $name, lang()]
    );

    return chat_thread_by_id((int)db()->lastInsertId());
}

/** Threads of the back office, unread first. */
function chat_threads_for_staff(): array
{
    if (!chat_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT * FROM chat_threads ORDER BY (unread_staff > 0) DESC, COALESCE(last_message_at, created_at) DESC
         LIMIT 200'
    );
}

function chat_unread_for_staff(): int
{
    if (!chat_table_ready()) {
        return 0;
    }

    return (int)db_value('SELECT COALESCE(SUM(unread_staff), 0) FROM chat_threads', [], 0);
}

/** Marks the messages of the other side as seen. */
function chat_mark_seen(array $thread, bool $byStaff): void
{
    if (!chat_table_ready()) {
        return;
    }

    if ($byStaff) {
        if ((int)$thread['unread_staff'] > 0) {
            db_query('UPDATE chat_threads SET unread_staff = 0 WHERE id = ?', [(int)$thread['id']]);
        }

        return;
    }
    if ((int)$thread['unread_user'] > 0) {
        db_query('UPDATE chat_threads SET unread_user = 0 WHERE id = ?', [(int)$thread['id']]);
    }
}

/**
 * Adds one message.
 *
 * @return array{ok: bool, error: string}
 */
function chat_post(array $thread, string $author, string $authorName, string $body, bool $notifyByMail): array
{
    $body = trim($body);
    if ($body === '') {
        return ['ok' => false, 'error' => t('chat.empty')];
    }
    $length = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
    if ($length > FD_CHAT_MAX_LENGTH) {
        return ['ok' => false, 'error' => t('chat.too_long', (string)FD_CHAT_MAX_LENGTH)];
    }

    if ($author !== 'staff') {
        $last = db_one(
            'SELECT created_at FROM chat_messages WHERE thread_id = ? AND author <> \'staff\'
             ORDER BY id DESC LIMIT 1',
            [(int)$thread['id']]
        );
        if ($last !== null) {
            $stamp = strtotime((string)$last['created_at']);
            if ($stamp !== false && (time() - $stamp) < FD_CHAT_MIN_SECONDS) {
                return ['ok' => false, 'error' => t('chat.too_fast')];
            }
        }
    }

    db_query(
        'INSERT INTO chat_messages (thread_id, author, author_name, body, created_at) VALUES (?, ?, ?, ?, NOW())',
        [(int)$thread['id'], $author, $authorName, $body]
    );

    if ($author === 'staff') {
        db_query(
            "UPDATE chat_threads SET status = 'open', last_from = 'staff', last_message_at = NOW(),
             unread_user = unread_user + 1, updated_at = NOW() WHERE id = ?",
            [(int)$thread['id']]
        );
    } else {
        db_query(
            "UPDATE chat_threads SET status = 'open', last_from = ?, last_message_at = NOW(),
             unread_staff = unread_staff + 1, updated_at = NOW() WHERE id = ?",
            [$author, (int)$thread['id']]
        );
    }

    log_action('chat_message', 'chat', (int)$thread['id'],
        $author . ' wrote in thread ' . (int)$thread['id'] . ' (' . (string)$thread['email'] . ')');

    if ($notifyByMail) {
        if ($author === 'staff') {
            if (mail_pref_allows((string)$thread['email'], 'chat')) {
                chat_mail(
                    (string)$thread['email'],
                    (string)$thread['language'],
                    'mail.chat_reply_subject',
                    t('mail.chat_reply_body', $body)
                );
            }
        } else {
            $admin = trim((string)setting('notify_admin_email', ''));
            if ($admin !== '') {
                chat_mail(
                    $admin,
                    'en',
                    'mail.chat_new_subject',
                    t('mail.chat_new_body', (string)($thread['display_name'] ?? ''), (string)$thread['email'], $body)
                );
            }
        }
    }

    return ['ok' => true, 'error' => ''];
}

function chat_mail(string $to, string $language, string $subjectKey, string $body): bool
{
    $previous = lang_switch($language === 'zh' ? 'zh' : 'en');
    $subject  = t($subjectKey);
    $lines    = [t('mail.dear', $to), '', $body, '', url('chat.php'), '', setting('site_title', 'freedns.my')];
    lang_switch($previous);

    return send_mail($to, $subject, implode("\n", $lines));
}

/** Archives the conversations nobody touched for a week. */
function chat_sweep(): int
{
    if (!chat_table_ready()) {
        return 0;
    }

    $cutoff = date('Y-m-d H:i:s', time() - FD_CHAT_ARCHIVE_DAYS * 86400);
    $rows   = db_all(
        "SELECT id FROM chat_threads WHERE status = 'open'
         AND COALESCE(last_message_at, created_at) < ? LIMIT 200",
        [$cutoff]
    );
    foreach ($rows as $row) {
        db_query("UPDATE chat_threads SET status = 'archived', updated_at = NOW() WHERE id = ?", [(int)$row['id']]);
    }

    return count($rows);
}

function chat_set_status(int $threadId, string $status): void
{
    if (!in_array($status, ['open', 'archived'], true)) {
        return;
    }
    db_query('UPDATE chat_threads SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $threadId]);
    log_action('chat_' . $status, 'chat', $threadId, 'Thread ' . $threadId . ' set to ' . $status);
}

/** Everything one thread holds, as plain text for the holder to keep. */
function chat_export(array $thread): string
{
    $lines = [
        t('chat.export_title', (string)$thread['id'], (string)$thread['email']),
        '----------------------------------------',
    ];
    foreach (chat_messages((int)$thread['id']) as $message) {
        $who = (string)$message['author'] === 'staff'
            ? t('chat.from_staff')
            : t('chat.from_me');
        $lines[] = '[' . (string)$message['created_at'] . '] ' . $who . ':';
        $lines[] = (string)$message['body'];
        $lines[] = '';
    }

    return implode("\n", $lines);
}

function chat_status_text(string $status): string
{
    return t('chat.status_' . $status);
}

function chat_author_text(string $author, string $name = ''): string
{
    if ($author === 'staff') {
        return t('chat.from_staff');
    }
    if ($author === 'guest') {
        return $name === '' ? t('chat.from_guest') : $name . ' (' . t('chat.from_guest') . ')';
    }

    return $name === '' ? t('chat.from_user') : $name;
}
