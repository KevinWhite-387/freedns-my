<?php
declare(strict_types=1);

/**
 * freedns.my - the blog that hangs under /blog/.
 *
 * Shares the site's `users` table and session: there is no second account system, and no
 * registration page - people register on the registry and come back here.
 *
 * Rules this file enforces:
 *   * posting needs an approved row in blog_authors (users have none by default)
 *   * the verified flag on blog_authors is the badge shown next to an author's name
 *   * a comment starts as 'pending' and only appears once an operator approves it
 *   * blog_messages has no sender column: only the registry writes messages
 */

function blog_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'blog_posts'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function blog_enabled(): bool
{
    return setting_bool('blog_enabled', true) && blog_ready();
}

function blog_per_page(): int
{
    return max(1, min(50, (int)setting('blog_per_page', '10')));
}

/** Pick the Chinese column when the visitor reads Chinese and it is filled. */
function blog_text(array $row, string $field): string
{
    $zh = function_exists('is_zh') && is_zh();
    if ($zh) {
        $alt = (string)($row[$field . '_zh'] ?? '');
        if (trim($alt) !== '') {
            return $alt;
        }
    }

    return (string)($row[$field] ?? '');
}

function blog_excerpt(array $post, int $length = 220): string
{
    $text = trim((string)blog_text($post, 'excerpt'));
    if ($text === '') {
        $text = trim(strip_tags((string)blog_text($post, 'body')));
    }
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

function blog_date(string $value): string
{
    $ts = strtotime($value);

    return $ts === false ? $value : date('Y-m-d', $ts);
}

/* ------------------------------------------------------------------- posts */

/**
 * @param array{status?:string,category?:int,tag?:string,featured?:bool,q?:string} $filter
 * @return array{posts:array,total:int,pages:int,page:int}
 */
function blog_posts(int $page = 1, array $filter = []): array
{
    $out = ['posts' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
    if (!blog_ready()) {
        return $out;
    }

    $page   = max(1, $page);
    $status = (string)($filter['status'] ?? 'published');

    $where  = ['p.status = ?'];
    $args   = [$status];

    if (!empty($filter['featured'])) {
        $where[] = 'p.featured = 1';
    }
    if (!empty($filter['category'])) {
        $where[] = 'p.category_id = ?';
        $args[]  = (int)$filter['category'];
    }
    if (!empty($filter['tag'])) {
        $where[] = 'EXISTS (SELECT 1 FROM blog_post_tags pt JOIN blog_tags t ON t.id = pt.tag_id
                            WHERE pt.post_id = p.id AND t.slug = ?)';
        $args[]  = (string)$filter['tag'];
    }
    if (!empty($filter['q'])) {
        $where[] = '(p.title LIKE ? OR p.title_zh LIKE ? OR p.body LIKE ? OR p.body_zh LIKE ?)';
        $like    = '%' . (string)$filter['q'] . '%';
        array_push($args, $like, $like, $like, $like);
    }

    $sqlWhere = implode(' AND ', $where);
    $perPage  = blog_per_page();

    try {
        $total = (int)db_value('SELECT COUNT(*) FROM blog_posts p WHERE ' . $sqlWhere, $args, 0);
        $pages = max(1, (int)ceil($total / $perPage));
        $page  = min($page, $pages);

        $rows = db_all(
            'SELECT p.*, u.username AS author_name, c.slug AS category_slug, c.name AS category_name,
                    c.name_zh AS category_name_zh,
                    (SELECT COUNT(*) FROM blog_comments bc WHERE bc.post_id = p.id AND bc.status = \'approved\') AS comment_count
             FROM blog_posts p
             JOIN users u ON u.id = p.author_id
             LEFT JOIN blog_categories c ON c.id = p.category_id
             WHERE ' . $sqlWhere . '
             ORDER BY (p.published_at IS NULL), p.published_at DESC, p.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $args
        );
    } catch (Throwable $e) {
        return $out;
    }

    return ['posts' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function blog_post_by_slug(string $slug): ?array
{
    if (!blog_ready() || trim($slug) === '') {
        return null;
    }
    try {
        $row = db_one(
            'SELECT p.*, u.username AS author_name, c.slug AS category_slug, c.name AS category_name,
                    c.name_zh AS category_name_zh,
                    COALESCE(a.verified, 0) AS author_verified, a.badge AS author_badge, a.bio AS author_bio, a.website AS author_website
             FROM blog_posts p
             JOIN users u ON u.id = p.author_id
             LEFT JOIN blog_categories c ON c.id = p.category_id
             LEFT JOIN blog_authors a ON a.user_id = p.author_id
             WHERE p.slug = ? LIMIT 1',
            [$slug]
        );
    } catch (Throwable $e) {
        return null;
    }

    return $row === null ? null : $row;
}

function blog_post_by_id(int $id): ?array
{
    if (!blog_ready() || $id <= 0) {
        return null;
    }
    try {
        $row = db_one('SELECT * FROM blog_posts WHERE id = ? LIMIT 1', [$id]);
    } catch (Throwable $e) {
        return null;
    }

    return $row === null ? null : $row;
}

function blog_post_view(int $id): void
{
    if (!blog_ready() || $id <= 0) {
        return;
    }
    try {
        db_query('UPDATE blog_posts SET views = views + 1 WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        /* a counter must never break the page */
    }
}

/** Year → month → count, newest first. */
function blog_archive_index(): array
{
    if (!blog_ready()) {
        return [];
    }
    try {
        $rows = db_all(
            "SELECT DATE_FORMAT(published_at, '%Y') AS y, DATE_FORMAT(published_at, '%m') AS m, COUNT(*) AS n
             FROM blog_posts WHERE status = 'published' AND published_at IS NOT NULL
             GROUP BY y, m ORDER BY y DESC, m DESC"
        );
    } catch (Throwable $e) {
        return [];
    }

    return $rows;
}

function blog_categories_all(): array
{
    if (!blog_ready()) {
        return [];
    }
    try {
        return db_all(
            "SELECT c.*, (SELECT COUNT(*) FROM blog_posts p WHERE p.category_id = c.id AND p.status = 'published') AS n
             FROM blog_categories c ORDER BY c.sort_order ASC, c.name ASC"
        );
    } catch (Throwable $e) {
        return [];
    }
}

function blog_tags_all(): array
{
    if (!blog_ready()) {
        return [];
    }
    try {
        return db_all(
            "SELECT t.*, (SELECT COUNT(*) FROM blog_post_tags pt JOIN blog_posts p ON p.id = pt.post_id
                          WHERE pt.tag_id = t.id AND p.status = 'published') AS n
             FROM blog_tags t ORDER BY n DESC, t.name ASC LIMIT 200"
        );
    } catch (Throwable $e) {
        return [];
    }
}

/* ---------------------------------------------------------------- comments */

function blog_comments_approved(int $postId): array
{
    if (!blog_ready() || $postId <= 0) {
        return [];
    }
    try {
        return db_all(
            "SELECT c.*, u.username FROM blog_comments c JOIN users u ON u.id = c.user_id
             WHERE c.post_id = ? AND c.status = 'approved' ORDER BY c.id ASC LIMIT 500",
            [$postId]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array{ok:bool,error:string,pending:bool} */
function blog_comment_add(int $postId, array $user, string $body): array
{
    if (!blog_ready()) {
        return ['ok' => false, 'error' => 'The blog tables are missing.', 'pending' => false];
    }
    $post = blog_post_by_id($postId);
    if ($post === null || (string)$post['status'] !== 'published') {
        return ['ok' => false, 'error' => 'Unknown post.', 'pending' => false];
    }
    if ((int)($post['allow_comments'] ?? 1) !== 1) {
        return ['ok' => false, 'error' => 'Comments are closed for this post.', 'pending' => false];
    }
    $body = trim($body);
    if (mb_strlen($body) < 2) {
        return ['ok' => false, 'error' => 'Write a little more.', 'pending' => false];
    }
    if (mb_strlen($body) > 4000) {
        return ['ok' => false, 'error' => 'That comment is too long.', 'pending' => false];
    }

    $needsReview = setting_bool('blog_comments_need_review', true);
    $status      = $needsReview ? 'pending' : 'approved';

    try {
        db_query(
            'INSERT INTO blog_comments (post_id, user_id, body, status, created_at, created_ip)
             VALUES (?, ?, ?, ?, NOW(), ?)',
            [$postId, (int)$user['id'], $body, $status, client_ip()]
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save the comment.', 'pending' => false];
    }

    return ['ok' => true, 'error' => '', 'pending' => $needsReview];
}

function blog_comments_pending(int $limit = 100): array
{
    if (!blog_ready()) {
        return [];
    }
    try {
        return db_all(
            "SELECT c.*, u.username, p.title AS post_title, p.slug AS post_slug
             FROM blog_comments c JOIN users u ON u.id = c.user_id JOIN blog_posts p ON p.id = c.post_id
             WHERE c.status = 'pending' ORDER BY c.id ASC LIMIT " . max(1, $limit)
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array{ok:bool,error:string} */
function blog_comment_decide(int $id, bool $approve, string $admin, string $note = ''): array
{
    if (!blog_ready()) {
        return ['ok' => false, 'error' => 'The blog tables are missing.'];
    }
    try {
        $stmt = db_query(
            "UPDATE blog_comments SET status = ?, admin_note = ?, decided_by = ?, decided_at = NOW()
             WHERE id = ? AND status = 'pending'",
            [$approve ? 'approved' : 'rejected', $note === '' ? null : $note, $admin, $id]
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    if ($stmt->rowCount() !== 1) {
        return ['ok' => false, 'error' => 'That comment was already decided.'];
    }
    log_action($approve ? 'blog_comment_approved' : 'blog_comment_rejected', 'settings', null, 'comment ' . $id);

    return ['ok' => true, 'error' => ''];
}

/* ----------------------------------------------------------------- authors */

function blog_author_row(int $userId): ?array
{
    if (!blog_ready() || $userId <= 0) {
        return null;
    }
    try {
        $row = db_one('SELECT * FROM blog_authors WHERE user_id = ? LIMIT 1', [$userId]);
    } catch (Throwable $e) {
        return null;
    }

    return $row === null ? null : $row;
}

function blog_is_author(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    $row = blog_author_row((int)$user['id']);

    return $row !== null && (string)$row['status'] === 'approved';
}

function blog_is_verified(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    $row = blog_author_row((int)$user['id']);

    return $row !== null && (int)$row['verified'] === 1;
}

/** @return array{ok:bool,error:string} */
function blog_author_request(int $userId, string $reason): array
{
    if (!blog_ready()) {
        return ['ok' => false, 'error' => 'The blog tables are missing.'];
    }
    $row = blog_author_row($userId);
    if ($row !== null && (string)$row['status'] === 'approved') {
        return ['ok' => false, 'error' => 'You are already an author.'];
    }
    if ($row !== null && (string)$row['status'] === 'pending') {
        return ['ok' => false, 'error' => 'A request is already waiting.'];
    }
    try {
        if ($row === null) {
            db_query('INSERT INTO blog_authors (user_id, status, reason, created_at, updated_at)
                      VALUES (?, \'pending\', ?, NOW(), NOW())', [$userId, $reason === '' ? null : $reason]);
        } else {
            db_query("UPDATE blog_authors SET status = 'pending', reason = ?, updated_at = NOW() WHERE user_id = ?",
                [$reason === '' ? null : $reason, $userId]);
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save the request.'];
    }
    log_action('blog_author_requested', 'user', $userId, '');

    return ['ok' => true, 'error' => ''];
}

function blog_authors_queue(string $status = 'pending', int $limit = 100): array
{
    if (!blog_ready()) {
        return [];
    }
    $status = in_array($status, ['pending', 'approved', 'revoked'], true) ? $status : 'pending';
    try {
        return db_all(
            'SELECT a.*, u.username, u.email,
                    (SELECT COUNT(*) FROM blog_posts p WHERE p.author_id = a.user_id) AS post_count
             FROM blog_authors a JOIN users u ON u.id = a.user_id
             WHERE a.status = ? ORDER BY a.id ASC LIMIT ' . max(1, $limit),
            [$status]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array{ok:bool,error:string} */
function blog_author_decide(int $userId, bool $approve, string $admin, bool $verified = false, string $note = ''): array
{
    if (!blog_ready()) {
        return ['ok' => false, 'error' => 'The blog tables are missing.'];
    }
    try {
        $stmt = db_query(
            "UPDATE blog_authors SET status = ?, verified = ?, admin_note = ?, decided_by = ?,
                decided_at = NOW(), approved_at = IF(? = 'approved', NOW(), approved_at), updated_at = NOW()
             WHERE user_id = ?",
            [$approve ? 'approved' : 'revoked', $verified && $approve ? 1 : 0,
                $note === '' ? null : $note, $admin, $approve ? 'approved' : 'revoked', $userId]
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'error' => 'No author row for that account.'];
    }
    log_action($approve ? 'blog_author_approved' : 'blog_author_revoked', 'user', $userId, 'by ' . $admin);

    return ['ok' => true, 'error' => ''];
}

/* ---------------------------------------------------------------- messages */

function blog_unread_count(int $userId): int
{
    if (!blog_ready() || $userId <= 0) {
        return 0;
    }
    try {
        return (int)db_value('SELECT COUNT(*) FROM blog_messages WHERE user_id = ? AND read_at IS NULL', [$userId], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function blog_messages_for(int $userId, int $limit = 50): array
{
    if (!blog_ready() || $userId <= 0) {
        return [];
    }
    try {
        return db_all('SELECT * FROM blog_messages WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$userId]);
    } catch (Throwable $e) {
        return [];
    }
}

function blog_message_send(int $userId, string $subject, string $body, string $kind = 'system', ?string $link = null, string $by = ''): void
{
    if (!blog_ready() || $userId <= 0) {
        return;
    }
    try {
        db_query(
            'INSERT INTO blog_messages (user_id, subject, body, kind, link, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$userId, $subject, $body, $kind, $link, $by === '' ? null : $by]
        );
    } catch (Throwable $e) {
        /* a missing message must never break the action that caused it */
    }
}

function blog_message_read(int $messageId, int $userId): void
{
    if (!blog_ready()) {
        return;
    }
    try {
        db_query('UPDATE blog_messages SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [$messageId, $userId]);
    } catch (Throwable $e) {
        /* ignore */
    }
}

/* ------------------------------------------------------------------- feeds */

function blog_rss_items(int $limit = 20): array
{
    if (!blog_ready()) {
        return [];
    }
    try {
        return db_all(
            "SELECT p.*, u.username AS author_name FROM blog_posts p JOIN users u ON u.id = p.author_id
             WHERE p.status = 'published' ORDER BY p.published_at DESC LIMIT " . max(1, min(100, $limit))
        );
    } catch (Throwable $e) {
        return [];
    }
}
