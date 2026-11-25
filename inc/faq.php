<?php
declare(strict_types=1);

/**
 * freedns.my - FAQ storage.
 *
 * Two languages, English and Chinese, with English as the fallback: a row may carry only one of the
 * two texts and the front page still shows something sensible. Categories are optional.
 */

function faq_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = db_one("SHOW TABLES LIKE 'faq_items'") !== null;
    } catch (Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/** Pick the right column for the current language, falling back to English. */
function faq_text(string $en, string $zh): string
{
    $zhMode = function_exists('is_zh') && is_zh();
    if ($zhMode && trim($zh) !== '') {
        return $zh;
    }

    return trim($en) !== '' ? $en : $zh;
}

function faq_categories(): array
{
    if (!faq_ready()) {
        return [];
    }

    return db_all('SELECT * FROM faq_categories ORDER BY sort_order ASC, id ASC');
}

/**
 * Items, optionally filtered.
 *
 * @param string $q     keyword, matched against both question and answer in both languages
 * @param string $slug  category slug ('' = all)
 */
function faq_items(string $q = '', string $slug = ''): array
{
    if (!faq_ready()) {
        return [];
    }
    $where  = ['i.is_public = 1'];
    $params = [];

    if ($slug !== '') {
        $where[]  = 'c.slug = ?';
        $params[] = $slug;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(i.question_en LIKE ? OR i.question_zh LIKE ? OR i.answer_en LIKE ? OR i.answer_zh LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    return db_all(
        'SELECT i.*, c.slug AS category_slug, c.title_en AS category_en, c.title_zh AS category_zh
           FROM faq_items i
           LEFT JOIN faq_categories c ON c.id = i.category_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY COALESCE(c.sort_order, 9999) ASC, i.sort_order ASC, i.id ASC',
        $params
    );
}

/** Everything, for the console (public or not). */
function faq_items_all(): array
{
    if (!faq_ready()) {
        return [];
    }

    return db_all(
        'SELECT i.*, c.slug AS category_slug
           FROM faq_items i
           LEFT JOIN faq_categories c ON c.id = i.category_id
          ORDER BY COALESCE(c.sort_order, 9999) ASC, i.sort_order ASC, i.id ASC'
    );
}

function faq_save_category(int $id, string $slug, string $en, string $zh, int $sort): array
{
    if (!faq_ready()) {
        return [false, 'The FAQ tables are missing: import upgrade-faq.sql first.'];
    }
    $slug = strtolower(preg_replace('/[^a-z0-9-]/', '', $slug) ?? '');
    if ($slug === '') {
        return [false, 'The slug may only contain a-z, 0-9 and -.'];
    }
    if ($id > 0) {
        db_query(
            'UPDATE faq_categories SET slug = ?, title_en = ?, title_zh = ?, sort_order = ? WHERE id = ?',
            [$slug, $en, $zh, $sort, $id]
        );

        return [true, ''];
    }
    db_query(
        'INSERT INTO faq_categories (slug, title_en, title_zh, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())',
        [$slug, $en, $zh, $sort]
    );

    return [true, ''];
}

function faq_save_item(int $id, ?int $categoryId, string $qen, string $qzh, string $aen, string $azh, int $sort, bool $public): array
{
    if (!faq_ready()) {
        return [false, 'The FAQ tables are missing.'];
    }
    if (trim($qen) === '' && trim($qzh) === '') {
        return [false, 'A question needs at least one language filled in.'];
    }
    if ($id > 0) {
        db_query(
            'UPDATE faq_items SET category_id = ?, question_en = ?, question_zh = ?, answer_en = ?, answer_zh = ?,
                sort_order = ?, is_public = ?, updated_at = NOW() WHERE id = ?',
            [$categoryId, $qen, $qzh, $aen, $azh, $sort, $public ? 1 : 0, $id]
        );

        return [true, ''];
    }
    db_query(
        'INSERT INTO faq_items (category_id, question_en, question_zh, answer_en, answer_zh, sort_order, is_public, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
        [$categoryId, $qen, $qzh, $aen, $azh, $sort, $public ? 1 : 0]
    );

    return [true, ''];
}

function faq_delete_item(int $id): void
{
    if (faq_ready() && $id > 0) {
        db_query('DELETE FROM faq_items WHERE id = ?', [$id]);
    }
}

function faq_delete_category(int $id): void
{
    if (!faq_ready() || $id <= 0) {
        return;
    }
    db_query('UPDATE faq_items SET category_id = NULL WHERE category_id = ?', [$id]);
    db_query('DELETE FROM faq_categories WHERE id = ?', [$id]);
}
/* ------------------------------------------------------------------ translations */

/** Every translation row, keyed by [item_id][lang]. */
function faq_i18n_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db_all('SELECT item_id, lang, question, answer FROM faq_i18n') as $row) {
            $cache[(int)$row['item_id']][(string)$row['lang']] = [
                'question' => (string)$row['question'],
                'answer'   => (string)$row['answer'],
            ];
        }
    } catch (Throwable $e) {
        $cache = [];
    }

    return $cache;
}

/**
 * Question or answer of one item, in the best available language.
 *
 * Order: the language being viewed, then English, then Chinese, then any row that exists.
 * Returns '' when the item has no text at all.
 */
function faq_item_field(array $item, string $field): string
{
    $field  = $field === 'answer' ? 'answer' : 'question';
    $lang   = function_exists('lang') ? (string)lang() : 'en';
    $map    = faq_i18n_all();
    $id     = (int)($item['id'] ?? 0);

    if (isset($map[$id][$lang][$field]) && trim((string)$map[$id][$lang][$field]) !== '') {
        return (string)$map[$id][$lang][$field];
    }

    $legacyEn = $field === 'question' ? (string)($item['question_en'] ?? '') : (string)($item['answer_en'] ?? '');
    $legacyZh = $field === 'question' ? (string)($item['question_zh'] ?? '') : (string)($item['answer_zh'] ?? '');

    if (isset($map[$id]['en'][$field]) && trim((string)$map[$id]['en'][$field]) !== '') {
        return (string)$map[$id]['en'][$field];
    }
    if (trim($legacyEn) !== '') {
        return $legacyEn;
    }
    if (isset($map[$id]['zh'][$field]) && trim((string)$map[$id]['zh'][$field]) !== '') {
        return (string)$map[$id]['zh'][$field];
    }

    return $legacyZh;
}

/** Save one language of one item. An empty question removes the row. */
function faq_i18n_save(int $itemId, string $lang, string $question, string $answer): void
{
    if ($itemId <= 0) {
        return;
    }
    $lang = preg_replace('/[^a-z-]/', '', strtolower($lang)) ?? '';
    if ($lang === '') {
        return;
    }
    try {
        if (trim($question) === '') {
            db_query('DELETE FROM faq_i18n WHERE item_id = ? AND lang = ?', [$itemId, $lang]);

            return;
        }
        db_query(
            'INSERT INTO faq_i18n (item_id, lang, question, answer) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer)',
            [$itemId, $lang, mb_substr($question, 0, 255), $answer]
        );
    } catch (Throwable $e) {
        // a missing table simply means no translation was stored
    }
}