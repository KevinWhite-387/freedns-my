<?php
/**
 * freedns.my - the error page for 400/401/403/404/429/500/502/503/504.
 *
 * It uses the site itself (navigation, language, theme, footer) so an error looks like the rest of the
 * registry - the same shape as the "too many requests" page. But the errors it has to show are often
 * caused by the machinery underneath being broken, so everything is wrapped:
 *
 *   1. try to load the site and render inside its layout;
 *   2. if ANY step fails (no database, a missing file, a broken include), fall back to a small standalone
 *      page that needs nothing - it writes no cookie, opens no session and reads no language file.
 *
 * Wiring (aaPanel -> Website -> freedns.my -> Config, in the server block, then reload nginx):
 *     error_page 400 /error.php?code=400;
 *     error_page 401 /error.php?code=401;
 *     error_page 403 /error.php?code=403;
 *     error_page 404 /error.php?code=404;
 *     error_page 429 /error.php?code=429;
 *     error_page 500 /error.php?code=500;
 *     error_page 502 /error.php?code=502;
 *     error_page 503 /error.php?code=503;
 *     error_page 504 /error.php?code=504;
 * (Leave fastcgi_intercept_errors off: PHP's own 403/404 pages carry wording that matters.)
 */

$code = isset($_GET['code']) ? (int)$_GET['code'] : 0;

// No "?code=" in the address? nginx tells us the status it is handling through REDIRECT_STATUS when it
// performs the internal redirect, so a plain "error_page 404 /error.php;" works as well - and that form is
// accepted by every nginx, while a target with arguments is not.
if ($code === 0 && isset($_SERVER['REDIRECT_STATUS'])) {
    $code = (int)$_SERVER['REDIRECT_STATUS'];
}
if ($code < 400 || $code > 599) {
    $code = 500;
}

/** The wording of every code in both languages, so the standalone fallback can use it as well. */
$catalogue = [
    400 => ['zh' => '请求不正确', 'en' => 'Bad request',
        'zh_what' => '服务器看不懂这个请求，通常是从旧链接或手工拼错的地址进来的。',
        'en_what' => 'The server could not understand the request - usually an old link or a typed address.'],
    401 => ['zh' => '需要登录', 'en' => 'Authentication required',
        'zh_what' => '这一页需要先登录控制台账号才能打开。',
        'en_what' => 'This page needs a signed-in console account.'],
    403 => ['zh' => '没有权限', 'en' => 'Forbidden',
        'zh_what' => '这个地址不允许访问，或者你的账号没有打开它的权限。',
        'en_what' => 'This address may not be opened, or your account is not allowed to see it.'],
    404 => ['zh' => '页面不存在', 'en' => 'Page not found',
        'zh_what' => '这个地址没有对应的页面：链接可能写错了，或者页面已经改名、搬走了。',
        'en_what' => 'Nothing lives at this address: the link may be wrong, or the page was renamed or moved.'],
    429 => ['zh' => '请求太频繁', 'en' => 'Too many requests',
        'zh_what' => '短时间内的请求太多，已被临时限制，请稍等一会儿再试。',
        'en_what' => 'Too many requests in a short time and access is limited for now. Please wait a moment.'],
    500 => ['zh' => '服务器内部错误', 'en' => 'Internal server error',
        'zh_what' => '程序出错了，这一页没能生成。注册局已记录，通常很快会修复。',
        'en_what' => 'The program failed while building this page. The registry logs it and usually fixes it quickly.'],
    502 => ['zh' => '网关错误', 'en' => 'Bad gateway',
        'zh_what' => '处理请求的服务（PHP 或上游接口）没有正常回应，可能正在重启。',
        'en_what' => 'The service behind this page (PHP or an upstream API) did not answer properly; it may be restarting.'],
    503 => ['zh' => '服务暂时不可用', 'en' => 'Service unavailable',
        'zh_what' => '服务正在维护或暂时过载，请过几分钟再试。',
        'en_what' => 'The service is being maintained or is briefly overloaded. Please try again in a few minutes.'],
    504 => ['zh' => '网关超时', 'en' => 'Gateway timeout',
        'zh_what' => '后端服务没有在限定时间内回应，通常是上游接口变慢了。',
        'en_what' => 'The backend did not answer in time, usually because an upstream API got slow.'],
];
if (!isset($catalogue[$code])) {
    $code = 500;
}

$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$lang = (isset($_GET['lang']) && strtolower((string)$_GET['lang']) === 'zh') || strpos($path, '/zh/') === 0
    ? 'zh' : 'en';
$info  = $catalogue[$code];
$title = $lang === 'zh' ? $info['zh'] : $info['en'];
$other = $lang === 'zh' ? $info['en'] : $info['zh'];
$what  = $lang === 'zh' ? $info['zh_what'] : $info['en_what'];
$when  = date('Y-m-d H:i:s');

$h = static function (string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
};

if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if ($code === 429 || $code === 503) {
        header('Retry-After: 120');
    }
}

/* ------------------------------------------------------------------ *
 * 1. Inside the site layout (navigation, language, theme, footer)     *
 * ------------------------------------------------------------------ */

try {
    require __DIR__ . '/inc/bootstrap.php';

    // Serve the language the visitor was using: /zh/... and ?lang=zh mean Chinese, otherwise English.
    if (function_exists('lang_switch')) {
        if ($lang === 'zh' && lang() !== 'zh') {
            lang_switch('zh');
        } elseif ($lang === 'en' && lang() !== 'en') {
            lang_switch('en');
        }
    }

    $contact = trim((string)setting('contact_email', ''));
    $report  = $code . ' | ' . $path . ' | ' . $when;

    // The labels of this page are written out here on purpose: the error page must not depend on the
    // language files, because a broken deployment is one of the things it has to report.
    $L = $lang === 'zh' ? [
        'report' => '反馈时请提供这些信息',
        'hint'   => '把下面三行复制到你的消息里（或直接用下面的按钮）：管理员凭它就能立刻定位问题。',
        'code'   => '错误代码',
        'path'   => '访问的地址',
        'time'   => '时间',
        'copy'   => '一行打包全部信息：',
        'chat'   => '到在线咨询反馈',
        'mail'   => '用邮件发送',
        'links'  => '接下来可以去',
    ] : [
        'report' => 'What to send us if you report this',
        'hint'   => 'Copy the three lines below into your message (or use the buttons under them): the operator can find the problem with them straight away.',
        'code'   => 'Error code',
        'path'   => 'Address asked for',
        'time'   => 'Time',
        'copy'   => 'One line with everything:',
        'chat'   => 'Report it in the chat',
        'mail'   => 'Send it by e-mail',
        'links'  => 'Where to go next',
    ];

    page_header($code . ' ' . $title);
    ?>
    <div class="msg error">
      <strong><?= $h((string)$code) ?> &middot; <?= $h($title) ?></strong>
      <div><?= $h($what) ?></div>
    </div>

    <p class="hint"><?= $h($other) ?> &mdash;
      <?= $h($lang === 'zh' ? $info['en_what'] : $info['zh_what']) ?></p>

    <h2><?= $h($L['report']) ?></h2>
    <p><?= $h($L['hint']) ?></p>
    <table>
      <tr><th><?= $h($L['code']) ?></th><td><code><?= $h((string)$code) ?></code></td></tr>
      <tr><th><?= $h($L['path']) ?></th><td><code><?= $h($path) ?></code></td></tr>
      <tr><th><?= $h($L['time']) ?></th><td><code><?= $h($when) ?></code></td></tr>
    </table>
    <p><?= $h($L['copy']) ?></p>
    <pre class="wrap code-block"><?= $h($report) ?></pre>

    <p>
      <a href="<?= $h(url('chat.php')) ?>"><?= $h($L['chat']) ?></a>
      <?php if ($contact !== ''): ?>
      &middot; <a href="mailto:<?= $h($contact) ?>?subject=<?= $h('freedns.my error ' . $code) ?>&amp;body=<?= $h($report) ?>"><?= $h($L['mail']) ?></a>
      <?php endif; ?>
    </p>

    <h2><?= $h($L['links']) ?></h2>
    <ul>
      <li><a href="<?= $h(url('index.php')) ?>"><?= $h(t('nav.home')) ?></a></li>
      <li><a href="<?= $h(url('request.php')) ?>"><?= $h(t('nav.request')) ?></a></li>
      <li><a href="<?= $h(url('status.php')) ?>"><?= $h(t('nav.status')) ?></a></li>
      <li><a href="<?= $h(url('doc.php')) ?>"><?= $h(t('nav.doc')) ?></a></li>
      <li><a href="<?= $h(url('whois.php')) ?>"><?= $h(t('nav.whois')) ?></a></li>
      <li><a href="<?= $h(url('changelog.php')) ?>"><?= $h(t('nav.changelog')) ?></a></li>
      <li><a href="<?= $h(url('chat.php')) ?>"><?= $h(t('nav.chat')) ?></a></li>
    </ul>
    <?php
    page_footer();
    exit;
} catch (Throwable $e) {
    // Fall through to the standalone page: the site itself is what is broken.
}

/* ------------------------------------------------------------------ *
 * 2. Standalone fallback (no database, no session, no cookie, no JS)  *
 * ------------------------------------------------------------------ */

$prefix  = $lang === 'zh' ? '/zh' : '';
$strings = $lang === 'zh' ? [
    'hint' => '你可以试试：', 'home' => '回到首页', 'request' => '申请一个域名', 'status' => '查询我的申请',
    'doc' => '阅读文档', 'whois' => 'WHOIS 查询', 'chat' => '在线咨询', 'updates' => '更新日志',
    'report' => '方便反馈的信息',
    'copy' => '把上面这一行发给我们，就能直接定位问题。',
    'note' => '如果这一页反复出现，请到「在线咨询」告诉我们，并附上上面的信息。',
    'code' => '错误代码',
] : [
    'hint' => 'You can try:', 'home' => 'Home', 'request' => 'Apply for a name', 'status' => 'Look up my request',
    'doc' => 'Documentation', 'whois' => 'WHOIS lookup', 'chat' => 'Chat with us', 'updates' => 'Changelog',
    'report' => 'What to send us',
    'copy' => 'Send us that line and we can find the problem straight away.',
    'note' => 'If this keeps happening, tell us on the chat page and include the line above.',
    'code' => 'Error code',
];
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'zh' ? 'zh-CN' : 'en' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($code . ' ' . $title) ?> &middot; freedns.my</title>
<style>
  :root { color-scheme: light dark; }
  body { margin: 0; padding: 2.5rem 1.25rem; font: 16px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
         background: #fff; color: #111; }
  @media (prefers-color-scheme: dark) { body { background: #101214; color: #e6e6e6; } a { color: #7cc4ff; } }
  main { max-width: 46rem; margin: 0 auto; }
  .code { font-size: 4.5rem; line-height: 1; font-weight: 700; letter-spacing: .05em; }
  .label { font-size: 1.5rem; font-weight: 700; margin: .35rem 0 1rem; }
  .alt { opacity: .7; font-size: .95rem; }
  .box { border: 1px solid currentColor; border-radius: 6px; padding: .9rem 1rem; margin: 1.4rem 0; }
  pre { border: 1px solid currentColor; border-radius: 6px; padding: .6rem .8rem; overflow-x: auto; }
  ul { padding-left: 1.2rem; }
  h2 { font-size: 1.1rem; margin-top: 1.6rem; }
  footer { margin-top: 2rem; font-size: .9rem; opacity: .8; }
</style>
</head>
<body>
<main>
  <div class="code"><?= $h((string)$code) ?></div>
  <div class="label"><?= $h($title) ?></div>
  <div class="alt"><?= $h($other) ?></div>

  <div class="box">
    <p style="margin-top:0"><?= $h($what) ?></p>
    <p style="margin-bottom:0"><strong><?= $h($strings['code']) ?>:</strong> <?= $h((string)$code) ?></p>
  </div>

  <h2><?= $h($strings['report']) ?></h2>
  <pre><?= $h($code . ' | ' . $path . ' | ' . $when) ?></pre>
  <p class="alt"><?= $h($strings['copy']) ?></p>

  <p><?= $h($strings['hint']) ?></p>
  <ul>
    <li><a href="<?= $h($prefix) ?>/index.php"><?= $h($strings['home']) ?></a></li>
    <li><a href="<?= $h($prefix) ?>/request.php"><?= $h($strings['request']) ?></a></li>
    <li><a href="<?= $h($prefix) ?>/status.php"><?= $h($strings['status']) ?></a></li>
    <li><a href="<?= $h($prefix) ?>/doc.php"><?= $h($strings['doc']) ?></a></li>
    <li><a href="<?= $h($prefix) ?>/whois.php"><?= $h($strings['whois']) ?></a></li>
    <li><a href="<?= $h($prefix) ?>/changelog.php"><?= $h($strings['updates']) ?></a></li>
    <li><a href="<?= $h($prefix) ?>/chat.php"><?= $h($strings['chat']) ?></a></li>
  </ul>

  <footer><p><?= $h($strings['note']) ?></p></footer>
</main>
</body>
</html>
