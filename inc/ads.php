<?php
declare(strict_types=1);

/**
 * freedns.my - advertising slots.
 *
 * Four places, all filled by hand in the back office (no third-party script, no tracking, no iframe - the site
 * stays free of outside code on purpose):
 *
 *   side_left / side_right   the two rails beside the content, shown only on a wide screen
 *   request_bottom           under the application form
 *   console_middle           in the middle of the console home page
 *
 * Each slot has three settings: ad_<slot>_title, ad_<slot>_url and ad_<slot>_note. A Chinese variant of the
 * title and the note is used when present (ad_<slot>_title_zh / ad_<slot>_note_zh), otherwise the same text is
 * used for both languages.
 *
 * WHEN A SLOT IS EMPTY it does not disappear: it shows the hiring notice instead, because an empty box beside
 * the content looks broken. That notice points at jobs.php, whose text is editable as well.
 */

/** The slots, with the label the back office shows and where they appear. */
/**
 * Master switch for advertising.
 *
 * Turned off by request: the registry no longer shows advertising anywhere. Every entry point
 * below returns an empty string while this is off, so the pages keep their layout and no caller
 * has to be edited. Setting ads_enabled to 1 brings the whole feature back.
 */
function ads_enabled(): bool
{
    return function_exists('setting_bool') && setting_bool('ads_enabled', false);
}

function ad_slots(): array
{
    return [
        // w / h are the box an advertisement occupies, in CSS pixels. The registry fixes them per
        // position so every advertiser knows before paying what shape his text or picture must fit.
        'side_left'      => ['Left rail (wide screens only)', 'left', '左侧固定栏（仅宽屏显示）', 'w' => 160, 'h' => 600],
        'side_right'     => ['Right rail (wide screens only)', 'right', '右侧固定栏（仅宽屏显示）', 'w' => 160, 'h' => 600],
        'request_bottom' => ['Under the application form', 'bottom', '申请表单下方', 'w' => 728, 'h' => 90],
        'console_middle' => ['Middle of the console home page', 'middle', '控制台首页中部', 'w' => 300, 'h' => 250],
        'inline'         => ['Inside the page content (the exact spot differs per page)', 'inline',
                             '页面正文内（位置按页面固定变化）', 'w' => 600, 'h' => 160],
    ];
}

/**
 * What a human calls this slot. The key itself (side_left, request_bottom) is an internal name: it belongs in
 * <code> inside the back office, never in front of somebody choosing where to put their advertisement.
 */
/** The fixed box of one position: ['w' => 160, 'h' => 600]. */
function ad_size(string $slot): array
{
    $slots = ad_slots();
    $info  = $slots[$slot] ?? [];

    return ['w' => (int)($info['w'] ?? 300), 'h' => (int)($info['h'] ?? 250)];
}

/**
 * The body of a text advertisement: several lines, plain text, at most 300 characters.
 *
 * Text ads are the cheap tier: no picture to host, no design to approve - just a title that links
 * and a short paragraph. Stored in the same settings row as the rest of the slot.
 */
function ad_body(string $slot): string
{
    $field = (function_exists('lang') && is_zh()) ? 'ad_' . $slot . '_body_zh' : 'ad_' . $slot . '_body';
    $body  = trim((string)setting($field, ''));
    if ($body === '' && function_exists('lang') && is_zh()) {
        $body = trim((string)setting('ad_' . $slot . '_body', ''));
    }

    return mb_substr($body, 0, 300);
}

function ad_slot_label(string $slot): string
{
    $slots = ad_slots();
    if (!array_key_exists($slot, $slots)) {
        return $slot;
    }
    $info = $slots[$slot];

    return (function_exists('lang') && is_zh()) ? $info[2] : $info[0];
}

/**
 * The "inline" advertisement: one per page, in a spot that is fixed for that page.
 *
 * The position is not random per request - a page that moves its advertisement every time you reload it is
 * unreadable, and an advertiser could never say where his own ad is. Instead the address of the page decides:
 * crc32() of the script name picks one of three anchors (after the first paragraph, before the middle heading,
 * or at the end of the content), so /doc.php always puts it in the same place and /domains.php always in its
 * own, while the site as a whole does not look like it has one fixed advertising hole.
 *
 * It prints NOTHING when the slot is not sold: the hiring notice belongs in the rails, not in the middle of an
 * article. And it prints nothing when the visitor asked for no advertising, when the page already carries one of
 * the other slots, or inside the back office.
 */
function ad_inline_html(): string
{
    if (!ads_enabled()) { return ''; }
    if (!empty($_SESSION['fd_hide_ads']) || !ad_filled('inline')) {
        return '';
    }

    $zh    = function_exists('lang') && is_zh();
    $title = ad_title('inline');
    $url   = ad_url('inline');
    $note  = ad_note('inline');

    $out  = '<div class="ad ad-box ad-inline">' . "\n";
    $out .= '  <span class="ad-tag">' . h($zh ? '广告' : 'Ad') . "</span>\n";
    if ($url !== '') {
        $out .= '  <a href="' . h(function_exists('url')
            ? url('ad-go.php?s=inline') : 'ad-go.php?s=inline')
            . '" rel="nofollow noopener sponsored"><strong>' . h($title) . '</strong></a>' . "\n";
    } else {
        $out .= '  <strong>' . h($title) . "</strong>\n";
    }
    if ($note !== '') {
        $out .= '  <span class="ad-note">' . h($note) . "</span>\n";
    }
    $out .= "</div>\n";

    return $out;
}

/** Where this page puts its inline advertisement: 0 = after the first block, 1 = middle, 2 = at the end. */
function ad_inline_anchor(): int
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $script = str_replace('\\', '/', $script);
    // /zh/doc.php and /doc.php are the same page: the language wrapper must not move the advertisement.
    $script = preg_replace('#^/(zh|zh-hant|ja|ko|ms)/#', '/', $script) ?? $script;

    return (int)(crc32($script) % 3);
}

/** Put the advertisement into already-rendered page HTML. Anything it cannot do safely, it does not do. */
function ad_inline_inject(string $html): string
{
    if (!ads_enabled()) { return $html; }
    if ($html === '' || trim($html) === '') {
        return $html;
    }
    if (function_exists('is_admin_area') && is_admin_area()) {
        return $html;      // the back office is the operator's tool, not an advertising surface
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/(advertise|ad-go|error|hold|install|selftest)\.php$#', $script) === 1) {
        return $html;      // selling page, click counter, error and maintenance pages: no advertising in them
    }
    if (preg_match('#/request\.php$#', $script) === 1) {
        return $html;      // that page already carries its own slot (request_bottom)
    }

    // One advertisement per page: if any of the other slots rendered, this one keeps quiet.
    if (stripos($html, 'class="ad ') !== false || stripos($html, 'ad-rails') !== false) {
        return $html;
    }

    $ad = ad_inline_html();
    if ($ad === '') {
        return $html;
    }

    if (ad_inline_anchor() === 0) {
        // After the first paragraph, or after the first table when the page opens with one.
        foreach (['</p>', '</table>', '</ul>'] as $close) {
            $at = stripos($html, $close);
            if ($at !== false) {
                return substr($html, 0, $at + strlen($close)) . "\n" . $ad . substr($html, $at + strlen($close));
            }
        }

        return $html . $ad;
    }

    if (ad_inline_anchor() === 1) {
        // Before the middle heading of the page, so it lands between two sections instead of inside one.
        if (preg_match_all('#<h2[ >]#i', $html, $m, PREG_OFFSET_CAPTURE) && count($m[0]) >= 2) {
            $mid = $m[0][(int)floor(count($m[0]) / 2)][1];

            return substr($html, 0, $mid) . $ad . substr($html, $mid);
        }

        return $html . $ad;
    }

    // At the end of the content: just above the footer, without pushing the footer itself down.
    $at = strripos($html, '</p>');
    if ($at === false) {
        $at = strripos($html, '</table>');
    }
    if ($at === false) {
        return $html . $ad;
    }
    $end = strpos($html, '>', $at);

    return substr($html, 0, $end + 1) . "\n" . $ad . substr($html, $end + 1);
}

/** Is this slot filled in? */
function ad_filled(string $slot): bool
{
    return trim((string)setting('ad_' . $slot . '_title', '')) !== ''
        || trim((string)setting('ad_' . $slot . '_url', '')) !== '';
}

function ad_title(string $slot): string
{
    $zh = function_exists('lang') && is_zh();
    if ($zh) {
        $zhText = trim((string)setting('ad_' . $slot . '_title_zh', ''));
        if ($zhText !== '') {
            return $zhText;
        }
    }

    return trim((string)setting('ad_' . $slot . '_title', ''));
}

function ad_note(string $slot): string
{
    $zh = function_exists('lang') && is_zh();
    if ($zh) {
        $zhText = trim((string)setting('ad_' . $slot . '_note_zh', ''));
        if ($zhText !== '') {
            return $zhText;
        }
    }

    return trim((string)setting('ad_' . $slot . '_note', ''));
}

function ad_url(string $slot): string
{
    $url = trim((string)setting('ad_' . $slot . '_url', ''));

    // Only http(s) links are ever printed, so a mistyped value cannot turn into javascript: or data:.
    return preg_match('#^https?://#i', $url) === 1 ? $url : '';
}

/** The hiring notice, which stands in for an empty slot and has its own place under the form. */
function ad_hiring_text(): string
{
    $zh = function_exists('lang') && is_zh();
    $custom = trim((string)setting_lang('hiring_text'));
    if ($custom !== '') {
        return $custom;
    }

    return $zh ? '我们在招人 —— 点这里看看' : 'We are hiring - take a look';
}

function ad_hiring_url(): string
{
    return function_exists('url') ? url('jobs.php') : 'jobs.php';
}

/**
 * One slot, rendered.
 *
 * @param string $slot  one of ad_slots()
 * @param string $style 'box' (a box in the flow) or 'rail' (the fixed side rail)
 */
function ad_render(string $slot, string $style = 'box'): string
{
    if (!ads_enabled()) { return ''; }
    // A signed-in holder may switch advertising off for himself (account/ads.php). Visitors cannot: their choice
    // could not be remembered without a cookie, and this site does not set one just to hide an advertisement.
    if (!empty($_SESSION['fd_hide_ads'])) {
        return '';
    }

    $zh      = function_exists('lang') && is_zh();
    $title   = ad_title($slot);
    $note    = ad_note($slot);
    $url     = ad_url($slot);
    $out     = '';

    if (!$adFilled = ad_filled($slot)) {
        // Nothing sold for this place: show the hiring notice instead of an empty box.
        $title = ad_hiring_text();
        $url   = ad_hiring_url();
        $note  = $zh ? '这一位置对外招租，也欢迎自荐。' : 'This place is available, and so are we.';
    }

    $class = $style === 'rail' ? 'ad ad-rail ad-' . $slot : 'ad ad-box ad-' . $slot;
    $image = ad_image($slot);
    $size  = ad_size($slot);
    $body  = $adFilled ? ad_body($slot) : '';

    /* Every position has a fixed box (see ad_slots), so an advertiser knows before paying what shape
     * his text or picture has to fit. max-width keeps it inside a narrow screen; the minimum height
     * keeps a text-only advertisement from collapsing into the hiring notice below it. */
    $styleBox = 'width:' . $size['w'] . 'px;max-width:100%;overflow:hidden;';

    $out  .= '<div class="' . h($class) . '" style="' . h($styleBox) . '">' . "\n";
    $out  .= '  <span class="ad-tag">' . h($adFilled ? ($zh ? '广告' : 'Ad') : ($zh ? '招募' : 'Notice')) . "</span>\n";

    // The picture is hosted by the advertiser; it is decorative, so it carries the title as its alt text and is
    // loaded lazily. Width is capped by the stylesheet so a huge file cannot break the layout.
    if ($image !== '' && $adFilled) {
        $out .= '  <a href="' . h(function_exists('url')
            ? url('ad-go.php?s=' . rawurlencode($slot)) : ('ad-go.php?s=' . $slot)) . '" rel="nofollow noopener sponsored">'
            . '<img src="' . h($image) . '" alt="' . h($title) . '" loading="lazy" class="ad-img" style="max-width:100%;max-height:200px;height:auto;"></a>' . "\n";
    }

    if ($url !== '') {
        /*
         * A FILLED slot goes through the counter first, so the advertiser can be told how many clicks his link
         * got. The hiring notice, which stands in for an empty slot, must NOT: the counter only knows the
         * destination stored for a sold slot and would send the visitor home. It links straight to jobs.php.
         */
        if ($adFilled) {
            $href = function_exists('url') ? url('ad-go.php?s=' . rawurlencode($slot)) : ('ad-go.php?s=' . $slot);
        } else {
            $href = $url;
        }
        $out .= '  <a href="' . h($href) . '" rel="nofollow noopener sponsored">' . h($title) . "</a>\n";
    } else {
        $out .= '  <strong>' . h($title) . "</strong>\n";
    }
    /* The body of a text advertisement: several lines of plain text under the title. */
    if ($body !== '') {
        $out .= '  <div class="ad-body" style="margin:.3em 0 0 0">' . nl2br(h($body)) . "</div>\n";
    }
    if ($note !== '') {
        $out .= '  <span class="ad-note">' . h($note) . "</span>\n";
    }
    $out .= "</div>\n";

    return $out;
}

/**
 * The two rails beside the content.
 *
 * Fixed to the sides, and only drawn on a wide screen: the stylesheet hides them below 1400px, so a normal
 * window and every phone simply do not have them - which is what "desktop only" means here.
 */
function ad_rails(): string
{
    if (!ads_enabled()) { return ''; }
    if (function_exists('is_admin_area') && is_admin_area()) {
        return '';
    }

    $out = '<style>
      .ad-rail { display: none; }
      @media (min-width: 1400px) {
        .ad-rail {
          display: block; position: fixed; top: 120px; width: 170px;
          padding: 6px; border: 1px solid #cccccc; background: #fbfbfb;
          font-size: 12px; line-height: 1.5; z-index: 5;
        }
        .ad-side_left  { left: 12px; }
        .ad-side_right { right: 12px; }
      }
      .ad .ad-tag { display: block; font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: #888888; }
      .ad .ad-note { display: block; color: #666666; margin-top: 3px; }
      .ad .ad-img { max-width: 100%; height: auto; display: block; margin: 4px 0; border: 0; }
      .ad-box { border: 1px solid #cccccc; background: #fbfbfb; padding: 8px; margin: 18px 0; font-size: 13px; }
    </style>' . "\n";

    $out .= '<div class="ad-rails">' . "\n";
    $out .= ad_render('side_left', 'rail');
    $out .= ad_render('side_right', 'rail');
    $out .= "</div>\n";

    return $out;
}

/** How many days before the end an advertiser is reminded. 0 = no reminders. */
function ad_remind_days(): int
{
    return max(0, min(90, (int)setting('ad_remind_days', '3')));
}

/**
 * Reminds advertisers (and the registry) that their advertisement is about to run out.
 *
 * Called from the same cron as the publishing, so it costs one query when there is nothing to do. Each order is
 * reminded once: the moment is written to reminded_at, which is why the column exists.
 *
 * @return int how many reminders went out
 */
function ad_remind(): int
{
    $days = ad_remind_days();
    if ($days <= 0 || !function_exists('db_all')) {
        return 0;
    }
    try {
        $rows = db_all(
            "SELECT * FROM ad_orders
             WHERE status = 'active' AND end_at IS NOT NULL
               AND reminded_at IS NULL
               AND end_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
             LIMIT 50",
            [$days]
        );
    } catch (Throwable $e) {
        return 0;                       // column or table not there yet: stay quiet
    }

    $sent = 0;
    foreach ($rows as $order) {
        $to = (string)$order['contact_email'];
        $slot = (string)$order['slot'];
        if (valid_email_addr($to) && mail_pref_allows($to, 'account')) {
            $previous = lang_switch('en');
            send_mail($to, 'Your advertisement runs out soon (# ' . (int)$order['id'] . ')', implode("\n", [
                t('mail.dear', (string)$order['contact_name']), '',
                'Your advertisement in the slot "' . $slot . '" ends on ' . (string)$order['end_at'] . '.',
                'Reply to this message if you would like it to keep running.',
                '',
                rtrim(app_base_url(), '/') . '/advertise.php',
            ]));
            lang_switch($previous);
            $sent++;
        }

        // The registry hears about it too: it is the registry that renews the order.
        $admin = trim((string)setting('notify_admin_email', ''));
        if ($admin !== '') {
            $previous = lang_switch('en');
            send_mail($admin, 'Advertising order #' . (int)$order['id'] . ' ends soon', implode("\n", [
                t('mail.dear', $admin), '',
                'Slot:  ' . $slot,
                'Ends:  ' . (string)$order['end_at'],
                'Title: ' . (string)$order['title'],
                'Buyer: ' . (string)$order['contact_name'] . ' <' . $to . '>',
                '',
                rtrim(app_base_url(), '/') . '/admin/ads.php',
            ]));
            lang_switch($previous);
        }

        db_query('UPDATE ad_orders SET reminded_at = NOW() WHERE id = ?', [(int)$order['id']]);
        log_action('ad_reminded', 'settings', null, 'order #' . (int)$order['id'] . ' ends '
            . (string)$order['end_at']);
    }

    return $sent;
}
function ad_request_bottom(): string
{
    if (!ads_enabled()) { return ''; }
    return ad_render('request_bottom');
}

/** The slot that goes in the middle of the console home page. */
function ad_console_middle(): string
{
    if (!ads_enabled()) { return ''; }
    return ad_render('console_middle');
}

/**
 * The picture of a slot, if the advertiser gave one. It is hosted on their own server - the registry never
 * stores images - so only the address is kept and only http(s) addresses are ever printed.
 */
function ad_image(string $slot): string
{
    $url = trim((string)setting('ad_' . $slot . '_image', ''));

    return preg_match('#^https?://#i', $url) === 1 ? $url : '';
}

/* ------------------------------------------------------------------ *
 * Paid orders: putting them up and taking them down again             *
 * ------------------------------------------------------------------ */

/**
 * Walks the paid orders and keeps the slots in step with them.
 *
 *   approved + paid, start reached   -> its wording goes into the slot, the order becomes active
 *   active, end passed               -> the slot is emptied (so the hiring notice shows again), expired
 *
 * Meant to be called from cron, so an advertisement appears and disappears on time even when nobody visits the
 * site; calling it more often than necessary costs nothing, and it is safe to call twice in a row.
 *
 * @return array{up: int, down: int, errors: array<int, string>}
 */
function ad_sync_orders(): array
{
    $out = ['up' => 0, 'down' => 0, 'errors' => []];
    if (!function_exists('db_all') || !function_exists('ad_slots')) {
        return $out;
    }

    try {
        db_one("SHOW TABLES LIKE 'ad_orders'");
    } catch (Throwable $e) {
        return $out;                       // the table is not imported yet: nothing to do, nothing to say
    }

    // ---------------------------------------------------------------- take finished ones down
    $live = db_all("SELECT * FROM ad_orders WHERE status = 'active' AND end_at IS NOT NULL AND end_at < NOW()");
    foreach ($live as $order) {
        $slot = (string)$order['slot'];
        if (!array_key_exists($slot, ad_slots())) {
            continue;
        }
        // Only clear the slot when it really holds this order's wording - an advertisement that was typed in by
        // hand in the back office must not be wiped by an unrelated expiry.
        if (trim((string)setting('ad_' . $slot . '_title', '')) === trim((string)$order['title'])
            && trim((string)setting('ad_' . $slot . '_url', '')) === trim((string)$order['url'])) {
            set_setting('ad_' . $slot . '_title', '');
            set_setting('ad_' . $slot . '_url', '');
            set_setting('ad_' . $slot . '_note', '');
            set_setting('ad_' . $slot . '_image', '');
        }
        db_query("UPDATE ad_orders SET status = 'expired' WHERE id = ?", [(int)$order['id']]);
        log_action('ad_expired', 'settings', null, 'order #' . (int)$order['id'] . ' (' . $slot . ') came down');
        $out['down']++;
    }

    // ---------------------------------------------------------------- put paid ones up
    $ready = db_all(
        "SELECT * FROM ad_orders
         WHERE status = 'paid' AND (start_at IS NULL OR start_at <= NOW())
           AND (end_at IS NULL OR end_at > NOW())
         ORDER BY id ASC LIMIT 50"
    );
    foreach ($ready as $order) {
        $slot = (string)$order['slot'];
        if (!array_key_exists($slot, ad_slots())) {
            continue;
        }
        $start = trim((string)($order['start_at'] ?? '')) === '' ? date('Y-m-d H:i:s') : (string)$order['start_at'];
        $months = max(1, (int)$order['months']);
        $end   = trim((string)($order['end_at'] ?? ''));
        if ($end === '') {
            $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $months . ' month'));
        }

        set_setting('ad_' . $slot . '_title', (string)$order['title']);
        set_setting('ad_' . $slot . '_url', (string)$order['url']);
        set_setting('ad_' . $slot . '_note', (string)($order['note'] ?? ''));
        set_setting('ad_' . $slot . '_image', (string)($order['image_url'] ?? ''));

        db_query("UPDATE ad_orders SET status = 'active', start_at = ?, end_at = ? WHERE id = ?",
            [$start, $end, (int)$order['id']]);
        log_action('ad_published', 'settings', null,
            'order #' . (int)$order['id'] . ' (' . $slot . ') runs until ' . $end);
        $out['up']++;
    }

    return $out;
}
