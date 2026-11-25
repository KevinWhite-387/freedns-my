<?php
/**
 * freedns.my - the holding page: "please wait", with a reference code.
 *
 * Shown when the registry cannot serve normally: the operator switched it on, the site is being hammered,
 * or an upstream (database, provider) is not answering. The visitor gets a short code, the registry writes
 * down what it knows about that visit under the same code, and the visitor may mail the code in - the
 * operator looks it up in the back office.
 *
 * Like error.php this page is built to survive the situation it describes:
 *   1. try the site layout (navigation, language, footer);
 *   2. if any of that fails, fall back to a standalone page with no database, no session, no cookie.
 * And if the database itself is the problem, a code is still produced (from the address and the minute),
 * so the visitor has something to send.
 *
 * Reached as: /hold.php?reason=hold|flood|upstream|attack  (also used by the bootstrap when the switch is on)
 */

$reason = strtolower(trim((string)($_GET['reason'] ?? 'hold')));
if (!in_array($reason, ['hold', 'flood', 'upstream', 'attack'], true)) {
    $reason = 'hold';
}

/** A short code people can retype: HOLD-XXXX-XXXX, without characters that are easy to confuse. */
function hold_make_code(): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return 'HOLD-' . substr($out, 0, 4) . '-' . substr($out, 4, 4);
}

/** The code used when the database cannot be reached: deterministic, so the operator can still find it. */
function hold_fallback_code(string $ip): string
{
    $seed = $ip . '|' . date('Y-m-d H:i');

    return 'HOLD-' . strtoupper(substr(hash('sha256', $seed), 0, 4)) . '-'
        . strtoupper(substr(hash('sha256', $seed), 4, 4));
}

$ip    = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$path  = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
$ref   = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
$when  = date('Y-m-d H:i:s');

$code   = '';
$stored = false;
$contact = '';
$zh     = (isset($_GET['lang']) && strtolower((string)$_GET['lang']) === 'zh')
    || strpos($path, '/zh/') === 0
    || strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/zh/') !== false;

// ---------------------------------------------------------------- store what we know (never fatal)
// This page is usually reached *from* the bootstrap (when the switch is on) or from the flood guard, so the
// bootstrap is very often already loaded - requiring it a second time would run the whole bootstrap again
// and end in a recursion. The constant tells us which case we are in.
try {
    if (!defined('FD_ROOT')) {
        require __DIR__ . '/inc/bootstrap.php';
    }

    if (function_exists('lang') && $zh && function_exists('lang_switch') && lang() !== 'zh') {
        lang_switch('zh');
    }

    $contact = trim((string)setting('contact_email', ''));
    $holdMsg = trim((string)setting_lang('hold_message'));
    $holdEta = trim((string)setting_lang('hold_eta'));

    if (function_exists('client_ip')) {
        $ip = client_ip();
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'hold_events'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }

    if ($ready) {
        for ($try = 0; $try < 5; $try++) {
            $candidate = hold_make_code();
            try {
                db_query(
                    'INSERT INTO hold_events (code, reason, path, ip, agent, referer, user_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [$candidate, $reason, substr($path, 0, 255), $ip, $agent, $ref,
                     function_exists('current_user') && current_user() !== null ? (int)current_user()['id'] : null]
                );
                $code   = $candidate;
                $stored = true;
                break;
            } catch (Throwable $e) {
                // A duplicate code is practically impossible; anything else is reported by leaving $code empty.
                $code = '';
            }
        }
    }
} catch (Throwable $e) {
    // The site itself is what is broken: carry on with the standalone page below.
}

if ($code === '') {
    $code = hold_fallback_code($ip);
}

if (!headers_sent()) {
    /*
     * Which status to answer with. 200 by default, on purpose: nginx replaces the body of an error status
     * with its own error page (or its built-in one when that target cannot be served), which is exactly how
     * this page ends up being swallowed. A 200 keeps our wording on the screen; the page carries noindex and
     * Retry-After, so nothing is indexed and well-behaved clients still wait.
     *
     * Set the settings row hold_status to 503 (or 429) if you prefer the strict status and have made sure
     * nothing replaces the body - or to "auto" to let the caller decide through ?status=.
     */
    $fdHoldSetting = strtolower(trim((string)setting('hold_status', '200')));
    if ($fdHoldSetting === 'auto') {
        $fdHoldStatus = (int)($_GET['status'] ?? 503);
    } else {
        $fdHoldStatus = (int)$fdHoldSetting;
    }
    if (!in_array($fdHoldStatus, [200, 429, 503], true)) {
        $fdHoldStatus = 200;
    }

    http_response_code($fdHoldStatus);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if ($fdHoldStatus !== 200) {
        header('Retry-After: 300');
    }
}

$T = $zh ? [
    'title'   => '请耐心等待',
    'lead'    => '网站暂时无法正常提供服务。我们已知晓并正在处理，请稍后再试 —— 不需要重复刷新。',
    'code'    => '参考编号',
    'code_hint' => '把这个编号用邮件发给我们，我们就能直接找到你这次的访问记录。',
    'what'    => '我们已经记录了什么',
    'w_when'  => '时间',
    'w_path'  => '你访问的地址',
    'w_ip'    => '来源地址',
    'reason_hold' => '维护 / 暂停服务',
    'reason_flood' => '访问过于频繁',
    'reason_upstream' => '后端服务暂时不可用',
    'reason_attack' => '正在受到攻击，已进入保护模式',
    'why'     => '为什么会出现这一页',
    'next'    => '你可以做什么',
    'n1'      => '等几分钟再试（我们通常在几分钟到几小时内恢复）；',
    'n2'      => '如果一直是这样，把上面的参考编号用邮件发给我们；',
    'n3'      => '不需要反复刷新，那只会让情况更糟。',
    'stored_yes' => '这次访问已记录在案。',
    'stored_no'  => '注意：当前无法写入记录，请把编号与时间一起发给我们。',
    'mail'    => '用邮件反馈编号',
    'contact' => '联系邮箱',
] : [
    'title'   => 'Please wait a moment',
    'lead'    => 'The registry cannot serve normally right now. We know about it and are working on it - there is no need to reload over and over.',
    'code'    => 'Reference code',
    'code_hint' => 'Send us that code by mail and we can look up exactly this visit.',
    'what'    => 'What we have written down',
    'w_when'  => 'Time',
    'w_path'  => 'The address you asked for',
    'w_ip'    => 'Where you came from',
    'reason_hold' => 'maintenance / paused',
    'reason_flood' => 'too many requests',
    'reason_upstream' => 'a backend service is unavailable',
    'reason_attack' => 'under attack - protection mode is on',
    'why'     => 'Why this page appears',
    'next'    => 'What you can do',
    'n1'      => 'try again in a few minutes (recovery usually takes minutes to hours);',
    'n2'      => 'if it keeps happening, mail us the reference code above;',
    'n3'      => 'no need to keep reloading - that only makes it worse.',
    'stored_yes' => 'This visit was recorded.',
    'stored_no'  => 'Note: the record could not be written right now, so please send the code together with the time.',
    'mail'    => 'Send us the code by mail',
    'contact' => 'Contact address',
];

$h        = static function (string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
};
$reasonTx = $T['reason_' . $reason];
$mailTo   = $contact === '' ? '' : 'mailto:' . $contact
    . '?subject=' . rawurlencode('freedns.my ' . $code)
    . '&body=' . rawurlencode($code . ' | ' . $when . ' | ' . $path . "\n\n" . ($zh
        ? '说明：我在访问时看到了等待页。' : 'Note: I saw the holding page while visiting.'));

$prefix = $zh ? '/zh' : '';
?>
<!DOCTYPE html>
<html lang="<?= $zh ? 'zh-CN' : 'en' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($T['title']) ?> &middot; freedns.my</title>
<style>
  :root { color-scheme: light dark; }
  body { margin: 0; padding: 2.5rem 1.25rem; font: 16px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
         background: #fff; color: #111; }
  @media (prefers-color-scheme: dark) { body { background: #101214; color: #e6e6e6; } a { color: #7cc4ff; } }
  main { max-width: 46rem; margin: 0 auto; }
  h1 { font-size: 1.6rem; margin: 0 0 .6rem; }
  .code { font-size: 2rem; font-weight: 700; letter-spacing: .08em; margin: .4rem 0 .2rem; }
  .box { border: 1px solid currentColor; border-radius: 6px; padding: .9rem 1rem; margin: 1.3rem 0; }
  table { border-collapse: collapse; }
  th, td { text-align: left; padding: .2rem 1rem .2rem 0; vertical-align: top; }
  ul { padding-left: 1.2rem; }
  a { word-break: break-all; }
  footer { margin-top: 2rem; font-size: .9rem; opacity: .8; }
</style>
</head>
<body>
<main>
  <h1><?= $h($T['title']) ?></h1>
  <p><?= $h($T['lead']) ?></p>
  <?php if (isset($holdMsg) && $holdMsg !== ''): ?>
  <div class="box"><?= nl2br($h($holdMsg)) ?></div>
  <?php endif; ?>

  <div class="box">
    <div><?= $h($T['code']) ?></div>
    <div class="code"><?= $h($code) ?></div>
    <div><?= $h($T['code_hint']) ?></div>
  </div>

  <h2 style="font-size:1.1rem"><?= $h($T['what']) ?></h2>
  <table>
    <tr><th><?= $h($T['w_when']) ?></th><td><?= $h($when) ?></td></tr>
    <tr><th><?= $h($T['w_path']) ?></th><td><?= $h($path === '' ? '/' : $path) ?></td></tr>
    <tr><th><?= $h($T['w_ip']) ?></th><td><?= $h($ip) ?></td></tr>
    <tr><th><?= $h($T['why']) ?></th><td><?= $h($reasonTx) ?></td></tr>
  </table>
  <p><?= $h($stored ? $T['stored_yes'] : $T['stored_no']) ?>
    <?php if (isset($holdEta) && $holdEta !== ''): ?> <?= $h($holdEta) ?><?php endif; ?></p>

  <h2 style="font-size:1.1rem"><?= $h($T['next']) ?></h2>
  <ul>
    <li><?= $h($T['n1']) ?></li>
    <li><?= $h($T['n2']) ?><?php if ($mailTo !== ''): ?>
      &mdash; <a href="<?= $h($mailTo) ?>"><?= $h($T['mail']) ?></a>
      (<?= $h($T['contact']) ?>: <?= $h($contact) ?>)<?php endif; ?></li>
    <li><?= $h($T['n3']) ?></li>
  </ul>

  <footer>
    <p><a href="<?= $h($prefix) ?>/index.php"><?= $h($zh ? '重试首页' : 'Try the home page') ?></a>
      &middot; <a href="<?= $h($prefix) ?>/chat.php"><?= $h($zh ? '在线咨询' : 'Chat') ?></a>
      &middot; <?= $h($code) ?></p>
  </footer>
</main>
</body>
</html>
