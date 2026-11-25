<?php
declare(strict_types=1);

/**
 * freedns.my - ratings (1 to 5 stars) of the registry itself.
 *
 * One vote per account, changeable at any time. A holder writes down his score, optionally says why, and that
 * is the whole model - nothing is bought, no vote is weighted, and a review is never removed because it is
 * unkind.
 *
 * Moderation is automatic first, human second:
 *   * a review that looks like advertising (it contains a link, or something that looks like a domain name) or
 *     that smells of gambling, adult content or drugs is marked for review and NOT published yet;
 *   * everything else appears at once;
 *   * either way the writer is told his review was received, because that is true - telling him "it may be
 *     published after a check" would only teach people how to get past the filter.
 *
 * The stars are characters (★★★★☆), not pictures: this site has no images and no JavaScript.
 */

/** Is the table there? Everything degrades to "no reviews yet" when it is not. */
function reviews_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    if (!function_exists('db_one')) {
        return $ready;
    }
    try {
        $ready = db_one("SHOW TABLES LIKE 'reviews'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

/** ★★★☆☆ as text - the only way to draw a score without pictures or scripts. */
function review_stars(int $stars): string
{
    $stars = max(0, min(5, $stars));

    return str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
}

/** How a written review is judged before anybody reads it. */
function review_moderation(string $body): array
{
    $text = mb_strtolower($body, 'UTF-8');

    // Advertising: a link, a protocol, or something that reads like a domain or a contact handle.
    $linkish = [
        '#https?://#i', '#\bwww\.[a-z0-9-]+#i', '#\[url#i',
        '#\b[a-z0-9][a-z0-9-]{1,62}\.(com|net|org|cn|io|me|xyz|top|info|biz|ru|cc|tv|shop|site|online)\b#i',
        '#\b(加|扫|联系)?\s*(微信|weixin|wechat|qq群|telegram|飞机|whatsapp)\b#iu',
    ];
    foreach ($linkish as $pattern) {
        if (preg_match($pattern, $text) === 1) {
            return ['pending', 'looks like advertising (a link or a contact handle)'];
        }
    }

    // The three things no registry wants to be associated with.
    $banned = [
        '赌博', '博彩', '彩票代', '六合彩', '下注', '赌场', '棋牌室', '真人荷官',
        '色情', '淫', '裸聊', '约炮', '援交', '成人视频', '福利姬', 'onlyfans',
        '毒品', '冰毒', '大麻', '摇头丸', '枪支', '迷药', '代开票', '洗钱', '跑分',
        'casino', 'betting', 'porn', 'escort', 'viagra', 'cannabis',
    ];
    foreach ($banned as $word) {
        if (mb_strpos($text, $word) !== false) {
            return ['pending', 'mentions something the site does not want to publish (' . $word . ')'];
        }
    }

    // Shouting in capitals with a pile of exclamation marks is usually the same advertisement in disguise.
    if (preg_match('/[!！]{4,}/u', $body) === 1) {
        return ['pending', 'reads like an advertisement'];
    }

    return ['published', ''];
}

/**
 * Saves (or replaces) the score of one account.
 *
 * @return array{ok: bool, error: string, status: string}
 */
function review_save(array $user, int $stars, string $body): array
{
    $zh = function_exists('lang') && is_zh();

    if (!reviews_ready()) {
        return ['ok' => false, 'error' => $zh ? '评价功能暂时未开放。' : 'Rating is not open at the moment.',
                'status' => ''];
    }
    if ($stars < 1 || $stars > 5) {
        return ['ok' => false, 'error' => $zh ? '请选择 1 到 5 星。' : 'Please pick between one and five stars.',
                'status' => ''];
    }
    $body = trim($body);
    if (mb_strlen($body) > 500) {
        $body = mb_substr($body, 0, 500);
    }

    [$status, $why] = review_moderation($body);

    try {
        $existing = db_one('SELECT id FROM reviews WHERE user_id = ?', [(int)$user['id']]);
        if ($existing === null) {
            db_query(
                'INSERT INTO reviews (user_id, stars, body, status, note, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                [(int)$user['id'], $stars, $body === '' ? null : $body, $status,
                 $why === '' ? null : $why]
            );
            $id = (int)db()->lastInsertId();
        } else {
            db_query(
                'UPDATE reviews SET stars = ?, body = ?, status = ?, note = ?, updated_at = NOW() WHERE id = ?',
                [$stars, $body === '' ? null : $body, $status, $why === '' ? null : $why, (int)$existing['id']]
            );
            $id = (int)$existing['id'];
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $zh ? '保存失败，请稍后再试。' : 'Saving failed, please try again.',
                'status' => ''];
    }

    log_action('review_saved', 'user', (int)$user['id'],
        'review #' . $id . ' set to ' . $stars . ' star(s), state: ' . $status
        . ($why === '' ? '' : ' (' . $why . ')'));

    return ['ok' => true, 'error' => '', 'status' => $status];
}

/** Average, count and the 1..5 distribution, over the published reviews only. */
function review_stats(): array
{
    $out = ['count' => 0, 'average' => 0.0, 'dist' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
    if (!reviews_ready()) {
        return $out;
    }
    try {
        $rows = db_all("SELECT stars, COUNT(*) AS n FROM reviews WHERE status = 'published' GROUP BY stars");
        foreach ($rows as $row) {
            $stars = max(1, min(5, (int)$row['stars']));
            $out['dist'][$stars] = (int)$row['n'];
            $out['count'] += (int)$row['n'];
        }
        if ($out['count'] > 0) {
            $sum = 0;
            foreach ($out['dist'] as $stars => $n) {
                $sum += $stars * $n;
            }
            $out['average'] = round($sum / $out['count'], 2);
        }
    } catch (Throwable $e) {
        // leave the zeroes
    }

    return $out;
}

/** Published reviews, newest first, and the caller's own one whatever its state. */
function review_list(int $limit = 30): array
{
    if (!reviews_ready()) {
        return [];
    }
    try {
        return db_all(
            "SELECT r.*, u.username FROM reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.status = 'published'
             ORDER BY r.updated_at DESC, r.id DESC LIMIT " . max(1, min(200, $limit))
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** The review of one account, in any state. */
function review_mine(int $userId): ?array
{
    if (!reviews_ready() || $userId <= 0) {
        return null;
    }
    try {
        return db_one('SELECT * FROM reviews WHERE user_id = ?', [$userId]);
    } catch (Throwable $e) {
        return null;
    }
}

/** A name to show next to a review: the account name, or "anonymous" when it asks to be. */
function review_author(array $row): string
{
    $name = trim((string)($row['username'] ?? ''));
    if ($name === '') {
        return function_exists('lang') && is_zh() ? '一位用户' : 'a user';
    }
    // "anonymous" written at the start of the text is honoured: the name is then not shown.
    if (preg_match('/^\s*(匿名|anonymous)/iu', (string)($row['body'] ?? '')) === 1) {
        return function_exists('lang') && is_zh() ? '匿名用户' : 'anonymous';
    }

    return $name;
}
