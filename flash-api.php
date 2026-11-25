<?php
declare(strict_types=1);

/**
 * freedns.my - the data behind the Flash interface.
 *
 * assets/ui.swf loads this file and draws whatever comes back. The format is the smallest thing
 * that can work: one record per line, "PREFIX:value". No XML, no JSON, nothing the Flash side
 * has to parse carefully - a screen that fails to load shows one line rather than a broken frame.
 *
 *   TITLE:<heading>          the line at the top
 *   NAV:<screen>|<label>     one clickable word, repeated
 *   LINE:<text>              one line of body text
 *   BLANK:                   an empty line
 *   STATUS:<text>            the small line at the bottom
 *
 * Every word is localised by the registry itself (lang()), so the Flash interface speaks the same
 * six languages as the HTML one without a second translation table.
 */

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// The Flash shell passes the chosen language as ?lang=, because a .swf cannot set a cookie
// through a headerless GET. Setting FD_FORCE_LANG is the same switch /zh/ uses for the HTML
// pages, so both interfaces read the identical catalogue.
$flashWanted = strtolower(trim((string)($_GET['lang'] ?? '')));
if ($flashWanted !== '' && array_key_exists($flashWanted, fd_languages())) {
    $GLOBALS['FD_FORCE_LANG'] = $flashWanted;
}

$zh     = is_zh();
$screen = (string)($_GET['screen'] ?? 'home');

/** The six screens the Flash shell can show. Kept short on purpose: see flash/README.md. */
function flash_nav(bool $zh): array
{
    return [
        'home'    => $zh ? '首页'     : 'Home',
        'request' => $zh ? '申请域名' : 'Request',
        'status'  => $zh ? '查询申请' : 'Status',
        'manage'  => $zh ? '管理记录' : 'Records',
        'doc'     => $zh ? '文档'     : 'Docs',
        'account' => $zh ? '登录'     : 'Sign in',
        'admin'   => $zh ? '后台'     : 'Admin',
    ];
}

$nav = flash_nav($zh);

// Language row: the six the registry speaks, with the current one marked.
$flashLangs = [
    'en'      => 'English',
    'zh'      => '简体中文',
    'zh-hant' => '繁體中文',
    'ja'      => '日本語',
    'ko'      => '한국어',
    'ms'      => 'Bahasa',
];
foreach ($flashLangs as $code => $label) {
    echo 'LANG:' . $code . '|' . $label . "\n";
}
echo 'CURLANG:' . lang() . "\n";

echo 'NAV:' . implode('|', ['home', $nav['home']]) . "\n";
foreach ($nav as $key => $label) {
    if ($key === 'home') {
        continue;
    }
    echo 'NAV:' . $key . '|' . $label . "\n";
}

switch ($screen) {
    case 'request':
        echo 'TITLE:' . ($zh ? '申请域名' : 'Request a domain') . "\n";

        // ---- submission ------------------------------------------------------------------
        // The form posts back here with action=submit. Validation is NOT re-implemented: it goes
        // through the same helpers request.php uses (valid_label, zone_by_name, collect_records_from_post,
        // generate_request_code, replace_records), so the Flash form cannot accept anything the HTML
        // form would reject, and both paths produce identical rows.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'submit') {
            $zoneName = strtolower(trim((string)($_POST['zone'] ?? '')));
            $labelIn  = trim((string)($_POST['label'] ?? ''));
            $typeIn   = strtoupper(trim((string)($_POST['type'] ?? 'A')));
            $valueIn  = trim((string)($_POST['value'] ?? ''));
            $emailIn  = trim((string)($_POST['email'] ?? ''));
            $errors   = [];

            $zoneRow = $zoneName === '' ? null : zone_by_name($zoneName);
            if ($zoneRow === null) {
                $errors[] = $zh ? '后缀不存在。' : 'That suffix does not exist.';
            } elseif ((int)$zoneRow['is_open'] !== 1) {
                $errors[] = $zh ? '该后缀目前不接受申请。' : 'That suffix is not accepting requests.';
            }
            if (!setting_bool('requests_open', true)) {
                $errors[] = $zh ? '申请表单目前关闭。' : 'The request form is closed.';
            }

            $label = function_exists('normalize_label') ? normalize_label($labelIn, $zoneName) : $labelIn;
            if ($label === '') {
                $errors[] = $zh ? '请填写域名。' : 'Please enter a name.';
            } else {
                if (strlen($label) < 2 || strlen($label) > 63) {
                    $errors[] = $zh ? '域名长度需在 2–63 之间。' : 'The name must be 2 to 63 characters.';
                } elseif (function_exists('valid_label') && !valid_label($label)) {
                    $errors[] = $zh ? '域名含有不允许的字符。' : 'The name contains characters that are not allowed.';
                } elseif (function_exists('reserved_labels') && in_array($label, reserved_labels(), true)) {
                    $errors[] = $zh ? '这是保留名称，不能申请。' : 'That name is reserved.';
                }
            }

            if (!function_exists('valid_email_addr') || !valid_email_addr($emailIn)) {
                $errors[] = $zh ? '邮箱地址不正确。' : 'That e-mail address is not valid.';
            }

            $name = ($label === '' || $zoneName === '') ? '' : $label . '.' . $zoneName;

            // One record, described the way request.php describes four: the same collector, so the
            // trimming, the per-type validation and the error wording all match.
            $_POST['sub']   = [''];
            $_POST['type']  = [$typeIn];
            $_POST['value'] = [$valueIn];
            $collected = [];
            try {
                $collected = collect_records_from_post($name, $zoneName, 4, record_types_for_ui($zoneName, null));
            } catch (Throwable $e) {
                $collected = ['records' => [], 'errors' => [$zh ? '记录格式不正确。' : 'The record is not valid.']];
            }
            foreach ((array)($collected['errors'] ?? []) as $message) {
                $errors[] = (string)$message;
            }
            $records = (array)($collected['records'] ?? []);

            if ($errors === [] && $records !== []) {
                $code = generate_request_code();
                try {
                    $pdo = db();
                    $pdo->beginTransaction();

                    $existing = db_one('SELECT id FROM domains WHERE name = ?', [$name]);
                    if ($existing !== null) {
                        $domainId = (int)$existing['id'];
                        if (function_exists('drop_pending_changes')) {
                            drop_pending_changes($domainId);
                        }
                        db_query(
                            "UPDATE domains SET name = ?, label = ?, zone = ?, language = ?, reserved = 0,
                                proxied = 0, status = 'pending', request_code = ?, registrant_email = ?,
                                created_ip = ?, created_at = NOW(), updated_at = NOW()
                             WHERE id = ?",
                            [$name, $label, $zoneName, lang(), $code, $emailIn, client_ip(), $domainId]
                        );
                    } else {
                        db_query(
                            "INSERT INTO domains (name, label, zone, language, reserved, proxied, status,
                                request_code, registrant_email, created_ip, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 0, 0, 'pending', ?, ?, ?, NOW(), NOW())",
                            [$name, $label, $zoneName, lang(), $code, $emailIn, client_ip()]
                        );
                        $domainId = (int)$pdo->lastInsertId();
                    }

                    replace_records($domainId, $records);
                    $pdo->commit();

                    $created = domain_by_id($domainId);
                    if ($created !== null && function_exists('log_action')) {
                        log_action('request_submitted', 'domain', $domainId, 'Flash request from ' . client_ip());
                    }
                    if ($created !== null && function_exists('notify_admin_new_request')) {
                        notify_admin_new_request($created, domain_records($domainId));
                    }

                    echo 'RESULT:ok|' . ($zh
                        ? '已提交，申请编号 ' . $code . ' —— 请保存，它是以后管理记录的唯一凭据。'
                        : 'Submitted, request code ' . $code . ' - keep it, it is the only way to manage the records later.') . "\n";
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    echo 'RESULT:err|' . ($zh ? '保存失败，请稍后再试。' : 'Could not save, please try again later.') . "\n";
                }
            } else {
                echo 'RESULT:err|' . implode(' / ', array_slice($errors, 0, 3)) . "\n";
            }
        }

        // ---- the form itself --------------------------------------------------------------
        echo "FORM:1\n";
        echo 'LINE:' . ($zh
            ? '申请由人工审核，通常几天内处理。免费，续期同样免费。'
            : 'Requests are reviewed by hand, normally within a few days. Free, renewals included.') . "\n";
        echo "BLANK:\n";

        $open = [];
        try {
            foreach (zone_list(true) as $zoneRow) {
                $open[] = (string)$zoneRow['name'];
            }
        } catch (Throwable $e) {
            $open = [];
        }
        echo 'ZONES:' . implode(',', $open) . "\n";

        $types = [];
        try {
            foreach (record_types_for_ui((string)($open[0] ?? ''), null) as $typeCode => $typeLabel) {
                $types[] = (string)(is_array($typeLabel) ? $typeCode : $typeLabel);
            }
        } catch (Throwable $e) {
            $types = ['A', 'AAAA', 'CNAME', 'TXT'];
        }
        echo 'TYPES:' . implode(',', $types) . "\n";

        // The registry's own token, taken out of csrf_field()'s hidden input.
        $token = '';
        if (function_exists('csrf_field')) {
            if (preg_match('/value="([^"]+)"/', csrf_field(), $m) === 1) {
                $token = (string)$m[1];
            }
        }
        echo 'CSRF:' . $token . "\n";
        break;

    case 'status':
        echo 'TITLE:' . ($zh ? '查询申请' : 'Check a request') . "\n";
        echo 'LINE:' . ($zh
            ? '用申请编号查询进度：' . app_base_url() . 'status.php'
            : 'Follow a request with its code: ' . app_base_url() . 'status.php') . "\n";
        echo "BLANK:\n";
        echo 'LINE:' . ($zh
            ? '申请编号只在提交时显示一次，请务必保存。'
            : 'The request code is shown once, when the request is submitted. Keep it.') . "\n";
        break;

    case 'manage':
        echo 'TITLE:' . ($zh ? '管理记录' : 'Manage records') . "\n";
        echo 'LINE:' . ($zh
            ? '登录后可以修改 A / AAAA / CNAME / TXT / MX / SRV 等记录：'
            : 'After signing in you can change A, AAAA, CNAME, TXT, MX, SRV and more:') . "\n";
        echo 'LINE:  ' . app_base_url() . 'account/login.php' . "\n";
        break;

    case 'doc':
        echo 'TITLE:' . ($zh ? '文档' : 'Documentation') . "\n";
        echo 'LINE:' . ($zh ? '注册政策与文档：' : 'Policy and documentation: ') . app_base_url() . 'doc.php' . "\n";
        break;

    case 'account':
        echo 'TITLE:' . ($zh ? '登录' : 'Sign in') . "\n";
        echo 'LINE:' . ($zh ? '登录地址：' : 'Sign in at: ') . app_base_url() . 'account/login.php' . "\n";
        break;

    case 'admin':
        echo 'TITLE:' . ($zh ? '后台 · 只读统计' : 'Back office - read only stats') . "\n";

        // Identity comes from the existing admin session cookie, which the Flash request carries
        // because it is same-origin. No second login form: the .swf never sees a password.
        $admin = function_exists('current_admin') ? current_admin() : null;
        if ($admin === null) {
            echo 'LINE:' . ($zh ? '需要先登录后台：' : 'Sign in to the back office first:') . "\n";
            echo 'LINE:  ' . app_base_url() . 'admin/login.php' . "\n";
            echo "BLANK:\n";
            echo 'LINE:' . ($zh
                ? '登录后再回到这一页，就会显示统计数字。'
                : 'Come back to this page after signing in and the numbers appear.') . "\n";
            break;
        }

        echo 'LINE:' . ($zh ? '当前管理员：' : 'Signed in as: ') . (string)($admin['username'] ?? '') . "\n";
        echo "BLANK:\n";

        // Every count below reads a column that the registry already uses elsewhere; asking for a
        // column that does not exist would make db_schema_trouble() print its page and stop.
        $stats = [
            ['domains active',   "SELECT COUNT(*) FROM domains WHERE status = 'active'"],
            ['domains total',    'SELECT COUNT(*) FROM domains'],
            ['users',            'SELECT COUNT(*) FROM users'],
            ['users active',     "SELECT COUNT(*) FROM users WHERE status = 'active'"],
            ['users developer',  'SELECT COUNT(*) FROM users WHERE developer = 1'],
            ['records',          'SELECT COUNT(*) FROM records'],
        ];
        echo 'LINE:' . ($zh ? '规模' : 'Size') . "\n";
        echo "BLANK:\n";
        foreach ($stats as $row) {
            $value = 0;
            try {
                $value = (int)db_value($row[1], [], 0);
            } catch (Throwable $e) {
                $value = -1;
            }
            echo 'LINE:' . str_pad((string)$row[0], 20) . '  ' . ($value < 0 ? 'n/a' : (string)$value) . "\n";
        }

        echo "BLANK:\n";
        echo 'LINE:' . ($zh ? '各后缀已生效域名' : 'Active names per zone') . "\n";
        echo "BLANK:\n";
        $perZone = [];
        try {
            $perZone = db_all("SELECT zone, COUNT(*) AS c FROM domains WHERE status = 'active' GROUP BY zone");
        } catch (Throwable $e) {
            $perZone = [];
        }
        if ($perZone === []) {
            echo 'LINE:' . ($zh ? '（读不到）' : '(not available)') . "\n";
        } else {
            foreach ($perZone as $row) {
                echo 'LINE:' . str_pad((string)($row['zone'] ?? '?'), 16) . '  ' . (int)($row['c'] ?? 0) . "\n";
            }
        }

        echo "BLANK:\n";
        echo 'LINE:' . ($zh
            ? '这是只读页：Flash 版不做写操作，改数据请用 HTML 后台。'
            : 'Read only: the Flash interface does no writes, use the HTML back office to change data.') . "\n";
        break;
    case 'home':
    default:
        echo 'TITLE:' . h(setting_lang('site_title') !== '' ? setting_lang('site_title') : 'freedns.my') . "\n";
        echo 'LINE:' . ($zh ? '免费二级域名' : 'Free domain names') . "\n";
        echo "BLANK:\n";

        // The zones and their live counts: the same numbers the HTML front page shows.
        $zones = [];
        try {
            // name, title and title_zh are the columns the table really has; sort_order is the
            // operator's ordering. Asking for a column that does not exist would not throw a
            // catchable error here: db_schema_trouble() prints its "please try again" page and
            // stops the request, which is exactly how this file failed the first time.
            $zones = db_all('SELECT name, title, title_zh FROM zones ORDER BY sort_order ASC, name ASC');
        } catch (Throwable $e) {
            $zones = [];
        }
        if ($zones === []) {
            echo 'LINE:' . ($zh ? '（暂时读不到后缀列表）' : '(the zone list is not available right now)') . "\n";
        } else {
            echo 'LINE:' . ($zh ? '可申请的后缀' : 'Zones offered') . "\n";
            echo "BLANK:\n";
            foreach ($zones as $zone) {
                $active = 0;
                try {
                    $active = (int)db_value(
                        "SELECT COUNT(*) FROM domains WHERE zone = ? AND status = 'active'",
                        [(string)$zone['name']],
                        0
                    );
                } catch (Throwable $e) {
                    $active = 0;
                }
                echo 'LINE:' . str_pad((string)$zone['name'], 12) . '  '
                    . str_pad((string)$active, 5) . '  '
                    . ($zh ? (string)($zone['title_zh'] ?? '') : (string)($zone['title'] ?? '')) . "\n";
            }
        }

        echo "BLANK:\n";
        echo 'LINE:' . ($zh
            ? '本注册局自己负责解析：审核通过即发布 A / AAAA / CNAME / TXT 记录。'
            : 'This registry answers for the names itself: an approved name is published with A, AAAA, CNAME and TXT records.') . "\n";
        break;
}

echo 'STATUS:' . ($zh ? 'Flash 界面 · 仅供浏览' : 'Flash interface - reading only') . "\n";
