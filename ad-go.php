<?php
declare(strict_types=1);

/**
 * freedns.my - advertising: the click counter.
 *
 * An advertisement points here, and this page sends the visitor straight on to the real destination. Nothing
 * is kept about the visitor - no address, no cookie, no user agent - only a number is added to the order that
 * currently holds that slot, so the registry can tell an advertiser how often his link was followed.
 *
 * No JavaScript is involved, which is also why only clicks can be counted: impressions would need a script on
 * every page, and this site does not run third-party code.
 *
 * Usage: /ad-go.php?s=<slot>      (the slot the advertisement is shown in)
 */

require __DIR__ . '/inc/bootstrap.php';

$slot = strtolower(trim((string)($_GET['s'] ?? '')));
$slots = function_exists('ad_slots') ? ad_slots() : [];

// Where to go: the destination stored for that slot. Only http(s) is ever used.
$target = '';
if (array_key_exists($slot, $slots)) {
    $url = trim((string)setting('ad_' . $slot . '_url', ''));
    if (preg_match('#^https?://#i', $url) === 1) {
        $target = $url;
    }
}

// Count it, quietly: a failure here must never stop the visitor from reaching the advertisement.
if ($target !== '') {
    try {
        $order = db_one(
            "SELECT id FROM ad_orders WHERE slot = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$slot]
        );
        if ($order !== null) {
            db_query('UPDATE ad_orders SET clicks = clicks + 1 WHERE id = ?', [(int)$order['id']]);
        }
    } catch (Throwable $e) {
        // table or column missing, or the database is down: the click simply is not counted
    }
}

if ($target === '') {
    $target = function_exists('url') ? url('index.php') : '/';
}

if (!headers_sent()) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('Location: ' . $target, true, 302);
}

// A link for anybody whose browser does not follow the redirect.
echo '<!doctype html><meta charset="utf-8"><title>redirect</title>';
echo '<p><a href="' . h($target) . '" rel="nofollow noopener">' . h($target) . '</a></p>';
