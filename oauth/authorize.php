<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the consent screen ("Sign in with freedns.my").
 *
 *   GET  /oauth/authorize.php?response_type=code&client_id=…&redirect_uri=…&scope=domains+records
 *                            &state=…&code_challenge=…&code_challenge_method=S256
 *
 * The user sees, in words, which application asks and what it wants, and presses Allow or Deny. Only then is a
 * one-time code handed to the redirect address the application registered - exactly, character for character.
 *
 * Two rules from RFC 6749 that this file obeys on purpose:
 *   * if the client_id or the redirect address is wrong, we must NOT redirect anywhere (that is how a stolen
 *     client_id is turned into an open redirect): the error is shown here instead;
 *   * a refusal is told to the application as error=access_denied, with the state it sent, so a client can tell
 *     "the user said no" from "something broke".
 *
 * A holder who already allowed the same scopes is not asked again - the code is issued straight away. Everything
 * else (a new scope, a new application, a revoked consent) asks.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$zh = function_exists('lang') && is_zh();

/** A plain page for the cases where redirecting would be wrong. */
$refuse = static function (string $title, string $text) use ($zh): void {
    page_header($title);
    echo '<h2>' . h($title) . '</h2><div class="msg error">' . h($text) . '</div>';
    echo '<p><a href="' . h(url('index.php')) . '">' . h($zh ? '返回首页' : 'Back to the front page') . '</a></p>';
    page_footer();
    exit;
};

if (op_unavailable()) {
    $refuse($zh ? '开放平台尚未启用' : 'The open platform is not enabled yet',
        $zh ? '数据库里还缺少开放平台的表，请站长先导入 upgrade-openplatform.sql。'
            : 'The tables are missing: the operator has to import upgrade-openplatform.sql first.');
}

// ---------------------------------------------------------------- parameters
$responseType = trim((string)($_REQUEST['response_type'] ?? ''));
$clientId     = trim((string)($_REQUEST['client_id'] ?? ''));
$redirectUri  = trim((string)($_REQUEST['redirect_uri'] ?? ''));
$scopeRaw     = trim((string)($_REQUEST['scope'] ?? ''));
$state        = (string)($_REQUEST['state'] ?? '');
$challenge    = trim((string)($_REQUEST['code_challenge'] ?? ''));
$method       = trim((string)($_REQUEST['code_challenge_method'] ?? ''));

$app = op_app_by_client($clientId);

// Everything that must not be redirected: a bad client, a bad address, a bad response type.
if ($app === null) {
    $refuse($zh ? '未知的应用' : 'Unknown application',
        $zh ? 'client_id 不存在，请向该应用索取正确的地址。'
            : 'No application has that client_id. Ask the application for the right one.');
}
if (op_app_active($app) === false) {
    $refuse($zh ? '应用不可用' : 'This application is not usable',
        $zh ? '该应用尚未通过审核，或已被吊销。' : 'It has not been approved, or it has been revoked.');
}
if (op_redirect_ok($app, $redirectUri) === false) {
    $refuse($zh ? '回调地址不匹配' : 'The redirect address does not match',
        $zh ? '该地址与应用登记的地址不一致（必须完全一致），因此本站不会跳转。'
            : 'It is not one of the addresses this application registered (an exact match is required), so '
              . 'nothing is redirected.');
}
if ($responseType !== 'code') {
    $refuse($zh ? '不支持的授权类型' : 'Unsupported response type',
        $zh ? '本站只支持 response_type=code（授权码 + PKCE）。'
            : 'Only response_type=code is supported (authorisation code with PKCE).');
}

// The scopes asked for must be a subset of what the application is allowed to ask for.
$wanted = op_scopes_list(op_scopes_clean($scopeRaw));
if ($wanted === []) {
    $wanted = ['profile'];   // an application that names no scope gets the least we can give
}
foreach ($wanted as $one) {
    if (op_scope_valid($one) === false || op_has((string)$app['scopes'], $one) === false) {
        $refuse($zh ? '权限范围不被允许' : 'A scope was refused',
            $zh ? '该应用没有申请「' . $one . '」这一项，或本站不认识它。'
                : 'The application is not allowed to ask for "' . $one . '", or the scope is unknown.');
    }
}
$wantScopes = implode(' ', $wanted);

/** Hand the code over and stop. */
$deliver = static function (string $uri, array $params, string $state): void {
    $sep = str_contains($uri, '?') ? '&' : '?';
    $url = $uri . $sep . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    if ($state !== '') {
        $url .= '&state=' . rawurlencode($state);
    }
    header('Location: ' . $url, true, 302);
    exit;
};

// ---------------------------------------------------------------- the holder
$user = function_exists('current_user') ? current_user() : null;
if ($user === null) {
    /*
     * The console login page reads $_SESSION['after_user_login'] and returns the user there when the sign-in
     * succeeds, so a third party does not have to tell people to "open the link again". A relative address is
     * stored on purpose: it can never point at another site.
     */
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['after_user_login'] = 'oauth/authorize.php?' . (string)($_SERVER['QUERY_STRING'] ?? '');
    }

    page_header($zh ? '请先登录' : 'Please sign in first');
    echo '<h2>' . h($zh ? '请先登录' : 'Please sign in first') . '</h2>';
    echo '<p>' . h($zh
        ? '应用「' . (string)$app['name'] . '」想用你的 freedns.my 账号登录。登录后会自动回到这一页，你只需要点一次「允许」。'
        : 'The application "' . (string)$app['name'] . '" wants to sign you in with your freedns.my account. '
          . 'After signing in you come straight back here, and you only have to press Allow once.') . '</p>';
    echo '<p><a href="' . h(url('account/login.php')) . '">' . h($zh ? '去登录' : 'Sign in') . '</a> · '
        . '<a href="' . h(url('account/register.php')) . '">' . h($zh ? '注册账号' : 'Create an account') . '</a></p>';
    page_footer();
    exit;
}
if (strtolower((string)($user['status'] ?? 'active')) !== 'active') {
    $refuse($zh ? '账号还不能使用' : 'This account cannot be used yet',
        $zh ? '请先在邮件里确认邮箱，或联系站长。' : 'Confirm your e-mail address first, or ask the operator.');
}

$userId = (int)$user['id'];

// ---------------------------------------------------------------- allow / deny
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $decision = strtolower((string)($_POST['decision'] ?? 'deny'));

    if ($decision !== 'allow') {
        $deliver($redirectUri, ['error' => 'access_denied',
            'error_description' => 'The user refused the request.'], $state);
    }

    // Remember the consent, so the same application asking for the same scopes does not nag again.
    $existing = db_one('SELECT * FROM oauth_consents WHERE app_id = ? AND user_id = ?', [(int)$app['id'], $userId]);
    $granted  = implode(' ', array_values(array_unique(array_merge(
        $existing === null ? [] : op_scopes_list((string)$existing['scopes']),
        $wanted
    ))));
    if ($existing === null) {
        db_query('INSERT INTO oauth_consents (app_id, user_id, scopes, created_at, updated_at)
                  VALUES (?, ?, ?, NOW(), NOW())', [(int)$app['id'], $userId, $granted]);
    } else {
        db_query('UPDATE oauth_consents SET scopes = ?, updated_at = NOW(), revoked_at = NULL WHERE id = ?',
            [$granted, (int)$existing['id']]);
    }

    $code = op_code_new((int)$app['id'], $userId, $redirectUri, $wantScopes,
        $challenge !== '' ? $challenge : null, $method);
    log_action('open_authorize', 'user', $userId,
        'allowed ' . (string)$app['name'] . ' (' . $wantScopes . ')');

    $deliver($redirectUri, ['code' => $code], $state);
}

// Already allowed, with at least these scopes: no need to ask again.
$consent = db_one('SELECT * FROM oauth_consents WHERE app_id = ? AND user_id = ? AND revoked_at IS NULL',
    [(int)$app['id'], $userId]);
if ($consent !== null) {
    $missing = [];
    foreach ($wanted as $one) {
        if (op_has((string)$consent['scopes'], $one) === false) {
            $missing[] = $one;
        }
    }
    if ($missing === []) {
        $code = op_code_new((int)$app['id'], $userId, $redirectUri, $wantScopes,
            $challenge !== '' ? $challenge : null, $method);
        $deliver($redirectUri, ['code' => $code], $state);
    }
}

// ---------------------------------------------------------------- the consent screen
page_header($zh ? '授权登录' : 'Authorise a sign-in');
?>
<h2><?= h($zh ? '「' . (string)$app['name'] . '」想要访问你的账号' : '"' . (string)$app['name'] . '" wants access to your account') ?></h2>

<?php if (trim((string)$app['homepage']) !== ''): ?>
<p class="small">
  <?= h($zh ? '应用主页：' : 'Home page: ') ?>
  <a href="<?= h((string)$app['homepage']) ?>" rel="nofollow noopener" target="_blank"><?= h((string)$app['homepage']) ?></a>
</p>
<?php endif; ?>

<p><?= $zh
    ? '如果你同意，它会得到下面这些权限。你可以随时在控制台里断开它。'
    : 'If you allow it, it receives exactly the access below. You can disconnect it again from the console at '
      . 'any time.' ?></p>

<table>
  <?php foreach ($wanted as $one): ?>
  <tr>
    <td style="white-space:nowrap"><code><?= h($one) ?></code></td>
    <td><?= h($zh ? op_scopes()[$one][1] : op_scopes()[$one][0]) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<div class="msg info"><?= $zh
    ? '它永远不会拿到你的密码。它拿到的是一枚令牌，只能做上面这些事，而且只对你这个账号有效。'
    : 'It never sees your password. It receives a token that can do only the things listed above, and only for '
      . 'your own account.' ?></div>

<form method="post" action="<?= h(url('oauth/authorize.php')) ?>">
<?= csrf_field() ?>
<?php foreach ([
    'response_type' => $responseType, 'client_id' => $clientId, 'redirect_uri' => $redirectUri,
    'scope' => $scopeRaw, 'state' => $state, 'code_challenge' => $challenge,
    'code_challenge_method' => $method,
] as $k => $v): ?>
<input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
<?php endforeach; ?>
<p>
  <button type="submit" name="decision" value="allow"><?= h($zh ? '允许' : 'Allow') ?></button>
  <button type="submit" name="decision" value="deny"><?= h($zh ? '拒绝' : 'Deny') ?></button>
</p>
</form>

<p class="hint"><?= h($zh
    ? '请求来自：' : 'Requested by: ') ?><code><?= h($clientId) ?></code> &middot;
  <?= h($zh ? '已登录：' : 'Signed in as: ') ?><?= h((string)$user['username']) ?></p>
<?php
page_footer();
