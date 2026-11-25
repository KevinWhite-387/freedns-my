<?php
declare(strict_types=1);

/**
 * freedns.my - signing in with a vc.ci account (OAuth 2.0, authorization code + PKCE).
 *
 * vc.ci is the provider. Verified endpoints (vc-ci/app/public/index.php, routes at lines 54-60
 * and op_authorize_validate() in inc/openplatform.php):
 *
 *   authorize  GET  /open/oauth/authorize   client_id, redirect_uri, scope, state, nonce,
 *                                           code_challenge, code_challenge_method=S256
 *   token      POST /open/oauth/token       grant_type=authorization_code, code, redirect_uri,
 *                                           client_id, client_secret, code_verifier
 *   userinfo   GET  /open/oauth/userinfo    Authorization: Bearer <access_token>
 *
 * Scopes: openid profile email domains - "domains" is what lets us pull the account's names and
 * migrate them into this registry.
 *
 * Credentials come from the settings first (Admin -> Settings -> vc.ci), then from inc/config.php.
 * Never hard-code them.
 */

/* ------------------------------------------------------------------ config */

function vcci_setting(string $key, string $default = ''): string
{
    $value = trim((string)setting('vcci_' . $key, ''));

    return $value !== '' ? $value : trim((string)config('vcci_' . $key, $default));
}

function vcci_issuer(): string
{
    $issuer = vcci_setting('issuer', 'https://register.vc.ci');

    return rtrim($issuer, '/');
}

function vcci_client_id(): string
{
    return vcci_setting('client_id');
}

function vcci_client_secret(): string
{
    return vcci_setting('client_secret');
}

function vcci_redirect_uri(): string
{
    $set = vcci_setting('redirect_uri');
    if ($set !== '') {
        return $set;
    }

    return url('account/vcci-callback.php');   /* absolute, see url() */
}

function vcci_configured(): bool
{
    return vcci_client_id() !== '' && vcci_client_secret() !== '';
}

function vcci_scopes(): string
{
    return 'openid profile email domains';
}

/* ------------------------------------------------------------------- http */

/**
 * @return array{ok:bool,status:int,data:mixed,error:string}
 */
function vcci_http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $status = 0;
    $raw    = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Network: ' . $err];
        }
        $raw = (string)$out;
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers) . "\r\n",
            'content'       => $body ?? '',
            'timeout'       => 20,
            'ignore_errors' => true,
        ]]);
        $out = @file_get_contents($url, false, $ctx);
        if ($out === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Could not reach ' . $url];
        }
        $raw = (string)$out;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m) === 1) {
                $status = (int)$m[1];
            }
        }
    }

    $data = json_decode($raw, true);
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
    }
    $message = '';
    if (is_array($data)) {
        $message = (string)($data['error_description'] ?? $data['error'] ?? $data['message'] ?? '');
    }
    if ($message === '') {
        $message = 'HTTP ' . $status;
    }

    return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $message];
}

/* ------------------------------------------------------------------- flow */

/**
 * Start the flow. $mode is "login" or "link"; $linkUserId is the signed-in account when linking.
 */
function vcci_begin(string $mode = 'login', ?int $linkUserId = null): void
{
    $state    = bin2hex(random_bytes(16));
    $verifier = bin2hex(random_bytes(32));
    $nonce    = bin2hex(random_bytes(16));

    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $_SESSION['FD_VCCI'] = [
        'state'    => $state,
        'verifier' => $verifier,
        'nonce'    => $nonce,
        'mode'     => $mode,
        'user'     => $linkUserId ?? 0,
        'at'       => time(),
    ];

    $params = [
        'response_type'         => 'code',
        'client_id'             => vcci_client_id(),
        'redirect_uri'          => vcci_redirect_uri(),
        'scope'                 => vcci_scopes(),
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ];

    redirect(vcci_issuer() . '/open/oauth/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

/**
 * Finish the flow: validate state, exchange the code, read the profile.
 *
 * @return array{ok:bool,error:string,mode:string,link:int,profile:array}
 */
function vcci_callback(): array
{
    $fail = static function (string $message): array {
        return ['ok' => false, 'error' => $message, 'mode' => 'login', 'link' => 0, 'profile' => []];
    };

    $saved = $_SESSION['FD_VCCI'] ?? null;
    unset($_SESSION['FD_VCCI']);

    if (!is_array($saved) || (int)($saved['at'] ?? 0) < time() - 900) {
        return $fail('The sign-in attempt expired. Please start again.');
    }

    $state = trim((string)($_GET['state'] ?? ''));
    if ($state === '' || !hash_equals((string)$saved['state'], $state)) {
        return $fail('The state did not match. Please start again.');
    }
    if (($_GET['error'] ?? '') !== '') {
        return $fail('vc.ci answered: ' . (string)$_GET['error']);
    }

    $code = trim((string)($_GET['code'] ?? ''));
    if ($code === '') {
        return $fail('vc.ci did not send an authorization code.');
    }

    $body = http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => vcci_redirect_uri(),
        'client_id'     => vcci_client_id(),
        'client_secret' => vcci_client_secret(),
        'code_verifier' => (string)$saved['verifier'],
    ]);

    $token = vcci_http('POST', vcci_issuer() . '/open/oauth/token', [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ], $body);

    if (!$token['ok'] || !is_array($token['data']) || empty($token['data']['access_token'])) {
        return $fail('Could not get a token: ' . (string)$token['error']);
    }

    $access = (string)$token['data']['access_token'];

    $info = vcci_http('GET', vcci_issuer() . '/open/oauth/userinfo', [
        'Accept: application/json',
        'Authorization: Bearer ' . $access,
    ]);

    if (!$info['ok'] || !is_array($info['data'])) {
        return $fail('Could not read the profile: ' . (string)$info['error']);
    }

    $profile = $info['data'];
    /* Some providers nest the payload. */
    if (isset($profile['user']) && is_array($profile['user'])) {
        $profile = $profile['user'];
    }

    return [
        'ok'      => true,
        'error'   => '',
        'mode'    => (string)($saved['mode'] ?? 'login'),
        'link'    => (int)($saved['user'] ?? 0),
        'profile' => $profile,
        'access'  => $access,
    ];
}

/** Pull the pieces we care about out of the userinfo payload. */
function vcci_identity(array $profile): array
{
    $sub = '';
    foreach (['sub', 'id', 'user_id', 'uid'] as $k) {
        if (isset($profile[$k]) && (string)$profile[$k] !== '') {
            $sub = (string)$profile[$k];
            break;
        }
    }
    $email = '';
    foreach (['email', 'mail'] as $k) {
        if (isset($profile[$k]) && (string)$profile[$k] !== '') {
            $email = strtolower(trim((string)$profile[$k]));
            break;
        }
    }
    $name = '';
    foreach (['preferred_username', 'username', 'name', 'display_name'] as $k) {
        if (isset($profile[$k]) && (string)$profile[$k] !== '') {
            $name = trim((string)$profile[$k]);
            break;
        }
    }

    return ['sub' => $sub, 'email' => $email, 'name' => $name];
}

/** The domain list vc.ci sends with the "domains" scope, normalised to fqdn strings. */
function vcci_domains(array $profile): array
{
    $out = [];
    foreach (['domains', 'names'] as $key) {
        if (!isset($profile[$key]) || !is_array($profile[$key])) {
            continue;
        }
        foreach ($profile[$key] as $item) {
            if (is_string($item)) {
                $out[] = strtolower(trim($item));
            } elseif (is_array($item)) {
                $fqdn = (string)($item['fqdn'] ?? $item['name'] ?? $item['domain'] ?? '');
                if ($fqdn !== '') {
                    $out[] = strtolower(trim($fqdn));
                }
            }
        }
    }

    return array_values(array_unique(array_filter($out)));
}

/* ---------------------------------------------------------------- binding */

function vcci_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $cols = db_all("SHOW COLUMNS FROM users LIKE 'vcci_%'");
        $have = [];
        foreach ($cols as $c) {
            $have[] = (string)$c['Field'];
        }
        $ready = in_array('vcci_id', $have, true);
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function vcci_user_by_sub(string $sub): ?array
{
    if (!vcci_columns_ready() || $sub === '') {
        return null;
    }
    $row = db_one('SELECT * FROM users WHERE vcci_id = ? LIMIT 1', [$sub]);

    return $row === null ? null : $row;
}

/** Store the binding on an existing account. */
function vcci_bind(int $userId, array $identity): array
{
    if (!vcci_columns_ready()) {
        return ['ok' => false, 'error' => 'The vc.ci columns are missing: import upgrade-vcci.sql first.'];
    }
    if ($identity['sub'] === '') {
        return ['ok' => false, 'error' => 'vc.ci did not send an account id.'];
    }
    $other = vcci_user_by_sub($identity['sub']);
    if ($other !== null && (int)$other['id'] !== $userId) {
        return ['ok' => false, 'error' => 'That vc.ci account is already linked to another user.'];
    }
    db_query(
        'UPDATE users SET vcci_id = ?, vcci_email = ?, vcci_linked_at = NOW() WHERE id = ?',
        [$identity['sub'], $identity['email'] !== '' ? $identity['email'] : null, $userId]
    );
    log_action('vcci_linked', 'user', $userId, $identity['sub'] . ' ' . $identity['email']);

    return ['ok' => true, 'error' => ''];
}

function vcci_unbind(int $userId): void
{
    if (!vcci_columns_ready()) {
        return;
    }
    db_query('UPDATE users SET vcci_id = NULL, vcci_email = NULL, vcci_linked_at = NULL WHERE id = ?', [$userId]);
    log_action('vcci_unlinked', 'user', $userId, '');
}

/**
 * Find or create the local account for a vc.ci profile.
 *
 * @return array{ok:bool,error:string,user:?array,created:bool}
 */
function vcci_login(array $identity): array
{
    if (!vcci_columns_ready()) {
        return ['ok' => false, 'error' => 'The vc.ci columns are missing: import upgrade-vcci.sql first.', 'user' => null, 'created' => false];
    }
    if ($identity['sub'] === '') {
        return ['ok' => false, 'error' => 'vc.ci did not send an account id.', 'user' => null, 'created' => false];
    }

    $user = vcci_user_by_sub($identity['sub']);
    if ($user !== null) {
        if ((string)$user['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'This account is suspended.', 'user' => null, 'created' => false];
        }

        return ['ok' => true, 'error' => '', 'user' => $user, 'created' => false];
    }

    /* Same e-mail already here: bind instead of making a second account. */
    if ($identity['email'] !== '') {
        $byMail = db_one('SELECT * FROM users WHERE email_lower = ? LIMIT 1', [$identity['email']]);
        if ($byMail !== null) {
            $bind = vcci_bind((int)$byMail['id'], $identity);
            if (!$bind['ok']) {
                return ['ok' => false, 'error' => $bind['error'], 'user' => null, 'created' => false];
            }

            return ['ok' => true, 'error' => '', 'user' => $byMail, 'created' => false];
        }
    }

    /* Brand new account, already active (vc.ci verified the address). */
    $base = $identity['name'] !== '' ? preg_replace('/[^a-z0-9_\-]+/i', '', $identity['name']) : '';
    if ($base === '' || mb_strlen($base) > 16) {
        $base = 'vcuser';
    }
    $username = $base;
    for ($i = 1; $i < 50; $i++) {
        $exists = db_one('SELECT id FROM users WHERE username_lower = ?', [strtolower($username)]);
        if ($exists === null) {
            break;
        }
        $username = $base . $i;
    }
    $email = $identity['email'] !== '' ? $identity['email'] : ($username . '@vcci.local');

    db_query(
        'INSERT INTO users (username, username_lower, email, email_lower, password_hash, status,
            verify_token, verify_expires, verify_sent_at, language, created_at)
         VALUES (?, ?, ?, ?, ?, \'active\', NULL, NULL, NULL, ?, NOW())',
        [$username, strtolower($username), $email, strtolower($email),
            password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), lang()]
    );
    $id = (int)db()->lastInsertId();
    vcci_bind($id, $identity);
    log_action('user_created_vcci', 'user', $id, $username . ' ' . $email);

    $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);

    return $user === null
        ? ['ok' => false, 'error' => 'The account was created but cannot be read back.', 'user' => null, 'created' => true]
        : ['ok' => true, 'error' => '', 'user' => $user, 'created' => true];
}

/** Sign a local user row in (same session key the rest of the site uses). */
function vcci_sign_in(array $user): void
{
    session_regenerate_id(true);
    $_SESSION[FD_USER_SESSION] = (int)$user['id'];
    unset($GLOBALS['FD_CURRENT_USER']);
    try {
        db_query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int)$user['id']]);
    } catch (Throwable $e) {
        /* a missing column must not block the sign-in */
    }
}
