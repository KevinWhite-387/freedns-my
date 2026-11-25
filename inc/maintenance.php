<?php
declare(strict_types=1);

/**
 * freedns.my - maintenance mode.
 *
 * While it is on, the things that CREATE something are closed and everything else keeps working:
 *
 *   closed    registering an account, applying for a name, creating or applying for a mailbox,
 *             adding a forwarding address
 *   working   signing in, changing records of a name you already hold, using existing mailboxes,
 *             changing their password, the console, the back office
 *
 * The wording shown to a visitor is editable in the back office (Back office -> Maintenance), together with the
 * window and the reason: the text, the window and the reason are separate settings so the page can say
 * "routine maintenance from 02:00 to 04:00 because ..." without anybody editing code.
 *
 * How the blocking works: this file is loaded last by the bootstrap, so the moment it is included it looks at
 * the request and, if it is one of the creating actions, renders the notice and stops. That keeps the rule in
 * one place instead of scattering it over every page - and it means a hand-made POST is refused as well.
 */

/**
 * Is maintenance in force right now?
 *
 * Two ways in, and either one is enough:
 *   * the manual switch (maintenance_on) - "start now, I will stop it myself";
 *   * a planned window (maintenance_start_at / maintenance_end_at, "YYYY-MM-DD HH:MM") - it comes on by itself
 *     and, just as importantly, goes off by itself, so nobody has to be awake at four in the morning.
 */
function maintenance_active(): bool
{
    if (setting_bool('maintenance_on', false)) {
        return true;
    }

    $start = strtotime(trim((string)setting('maintenance_start_at', '')));
    $end   = strtotime(trim((string)setting('maintenance_end_at', '')));
    if ($start === false && $end === false) {
        return false;
    }

    $now = time();
    if ($start !== false && $now < $start) {
        return false;           // not yet
    }
    if ($end !== false && $now > $end) {
        return false;           // over
    }

    return true;
}

/** Where the current state comes from, for the back office to show. */
function maintenance_source(): string
{
    if (setting_bool('maintenance_on', false)) {
        return 'manual';
    }

    return maintenance_active() ? 'planned' : 'off';
}

/** The window, as typed in the back office ("from" and "to" are free text on purpose). */
function maintenance_window(): string
{
    $from   = trim((string)setting('maintenance_from', ''));
    $to     = trim((string)setting('maintenance_to', ''));
    $zh     = function_exists('lang') && is_zh();
    if ($from === '' && $to === '') {
        return '';
    }
    if ($from !== '' && $to !== '') {
        return $zh ? ($from . ' 至 ' . $to) : ($from . ' to ' . $to);
    }

    return $from !== '' ? $from : $to;
}

function maintenance_reason(): string
{
    return trim((string)setting('maintenance_reason', ''));
}

/**
 * The text a visitor reads. Editable in the back office; when it is left empty a sensible sentence is built
 * from the window and the reason, so the page is never blank.
 */
function maintenance_note(): string
{
    $custom = trim((string)setting_lang('maintenance_text'));
    if ($custom !== '') {
        return $custom;
    }

    $zh     = function_exists('lang') && is_zh();
    $window = maintenance_window();
    $reason = maintenance_reason();

    $lines = [];
    $lines[] = $zh ? '当前系统正在进行例行维护。' : 'The system is undergoing routine maintenance.';
    if ($window !== '') {
        $lines[] = $zh ? '维护时间：' . $window : 'Maintenance window: ' . $window;
    }
    if ($reason !== '') {
        $lines[] = $zh ? '维护原因：' . $reason : 'Reason: ' . $reason;
    }
    $lines[] = $zh
        ? '维护期间：新增域名、新增邮箱、注册账户暂不可用；已有账户可继续登录、修改已有域名的解析、使用已有邮箱。'
        : 'During this window: registering an account, adding a name and adding a mailbox are unavailable; '
          . 'existing accounts can still sign in, change the records of a name they hold, and use their mailboxes.';

    return implode("\n", $lines);
}

/** What is being asked for, in one word - used in the notice and in the log. */
function maintenance_what(): string
{
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $action = strtolower((string)($_POST['action'] ?? $_GET['action'] ?? ''));
    $zh     = function_exists('lang') && is_zh();

    if ($script === 'register.php') {
        return $zh ? '注册账户' : 'registering an account';
    }
    if ($script === 'request.php') {
        return $zh ? '新增域名' : 'adding a name';
    }
    if ($script === 'email.php' || $script === 'forward.php') {
        if (in_array($action, ['create', 'apply', 'add'], true)) {
            return $script === 'email.php'
                ? ($zh ? '新增邮箱' : 'adding a mailbox')
                : ($zh ? '新增转发地址' : 'adding a forwarding address');
        }
    }

    return $zh ? '该操作' : 'that action';
}

/** Is this request one that creates something? */
function maintenance_blocks_this_request(): bool
{
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $action = strtolower((string)($_POST['action'] ?? $_GET['action'] ?? ''));

    if (in_array($script, ['register.php', 'request.php'], true)) {
        return true;
    }
    if (in_array($script, ['email.php', 'forward.php'], true)
        && in_array($action, ['create', 'apply', 'add'], true)) {
        return true;
    }

    return false;
}

/** The notice itself: the shipping layout when it is available, a plain page when it is not. */
function maintenance_page(): void
{
    $zh   = function_exists('lang') && is_zh();
    $text = maintenance_note();

    if (!headers_sent()) {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Retry-After: 600');
        header('X-Robots-Tag: noindex');
    }

    if (function_exists('page_header')) {
        page_header($zh ? '维护中' : 'Under maintenance');
        echo '<div class="msg error"><strong>' . h($zh ? '维护中' : 'Under maintenance') . "</strong><br>\n";
        echo nl2br(h($text));
        echo "</div>\n";
        echo '<p class="hint">' . h($zh
            ? '已有账户可继续登录，并修改已有域名的解析、使用已有邮箱。'
            : 'Existing accounts can still sign in, change the records of names they hold, and use their mailboxes.') . "</p>\n";
        if (function_exists('page_footer')) {
            page_footer();
        }

        return;
    }

    echo "<!doctype html><meta charset=\"utf-8\"><title>maintenance</title>";
    echo '<h1>' . h($zh ? '维护中' : 'Under maintenance') . '</h1><p>' . nl2br(h($text)) . '</p>';
}

/* ------------------------------------------------------------------ the guard runs on load
 *
 * Being included last by the bootstrap, this is the moment to look at the request. An administrator is never
 * blocked - he is the one who has to be able to switch maintenance off, and to test whether the site works.
 */
if (maintenance_active() && maintenance_blocks_this_request()
    && !(function_exists('is_admin') && is_admin())) {
    if (function_exists('log_action')) {
        log_action('maintenance_block', 'settings', null,
            'refused: ' . maintenance_what() . ' (maintenance mode)');
    }
    maintenance_page();
    exit;
}

/* ------------------------------------------------------------------ notice when it begins and ends
 *
 * The state is remembered, so a change can be noticed: when maintenance ends, a line is put at the top of the
 * changelog page (which the registry edits from the back office anyway) and the audit log gets a note either
 * way. Everything here is wrapped up, because this file is loaded on every request and a changelog line must
 * never be able to break a page.
 */
if (function_exists('setting') && function_exists('set_setting')) {
    try {
        $fd_now  = maintenance_active() ? '1' : '0';
        $fd_last = (string)setting('maintenance_last_state', '');

        if ($fd_last === '') {
            set_setting('maintenance_last_state', $fd_now);      // first run after the feature arrived
        } elseif ($fd_last !== $fd_now) {
            set_setting('maintenance_last_state', $fd_now);
            $fd_zh     = function_exists('lang') && is_zh();
            $fd_stamp  = date('Y-m-d H:i');
            $fd_reason = maintenance_reason();

            if ($fd_now === '1') {
                if (function_exists('log_action')) {
                    log_action('maintenance_started', 'settings', null,
                        'maintenance mode began' . ($fd_reason === '' ? '' : ' (' . $fd_reason . ')'));
                }
            } else {
                $fd_line = $fd_zh
                    ? $fd_stamp . ' 例行维护已完成。' . ($fd_reason === '' ? '' : '（原因：' . $fd_reason . '）')
                    : $fd_stamp . ' Routine maintenance finished.'
                      . ($fd_reason === '' ? '' : ' (reason: ' . $fd_reason . ')');

                foreach (['changelog', 'changelog_zh'] as $fd_key) {
                    $fd_old = (string)setting($fd_key, '');
                    // Newest first, which is how the changelog page is written.
                    set_setting($fd_key, $fd_line . ($fd_old === '' ? '' : "\n" . $fd_old));
                }
                if (function_exists('log_action')) {
                    log_action('maintenance_finished', 'settings', null, $fd_line);
                }
            }
        }
    } catch (Throwable $e) {
        // A changelog line is never worth a broken page.
    }
}
