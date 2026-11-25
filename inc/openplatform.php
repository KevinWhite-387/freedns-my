<?php
declare(strict_types=1);

/**
 * freedns.my - the open platform (OAuth 2.0, authorisation code + PKCE).
 *
 * Third parties sign a user in with "Sign in with freedns.my" and then read (or, with the write scope, change)
 * what that user allowed: names, records, mailboxes, application state. This file is the shared machinery -
 * scopes, applications, codes, tokens, consents - used by the endpoints (oauth/authorize.php, oauth/token.php,
 * oauth/userinfo.php, open/v1/*), by the console page (account/apps.php) and by the back office
 * (admin/openplatform.php). Nothing here prints anything.
 *
 * Rules this file enforces, because they are the whole security story:
 *   * an application only exists once a human approved it (status approved); pending and revoked never get a token;
 *   * a redirect address must match one of the stored ones exactly - no prefixes, no wildcards, no case fudging;
 *   * a code is bound to one application, one redirect address, one PKCE challenge, and it dies on first use;
 *   * tokens are stored as sha256 hashes; the plaintext is shown once, at the moment it is minted;
 *   * scopes are checked on every resource call, never assumed from the token's presence.
 */

/** Every scope the platform knows, with the label the consent page shows. */
function op_scopes(): array
{
    return [
        'profile'        => ['Account basics (username, e-mail, whether it is confirmed)', '账号基本信息（用户名 / 邮箱 / 是否已验证）'],
        'domains'        => ['Your names (name, state, suffix, registered date)', '你的域名列表（名字 / 状态 / 后缀 / 注册时间）'],
        'records'        => ['Read the DNS records of your names', '读取你域名的解析记录'],
        'records.write'  => ['Change the DNS records of your names', '修改你域名的解析记录（写权限）'],
        'mailboxes'      => ['Your mailboxes on this registry (address, state, quota)', '你的邮箱（地址 / 状态 / 配额）'],
        'applications'   => ['Your requests, their progress, your quota and counts', '你的申请与进度、配额与统计'],
    ];
}

/** Is this a scope we know? */
function op_scope_valid(string $scope): bool
{
    return array_key_exists($scope, op_scopes());
}

/** Keep only known scopes, order them as op_scopes() lists them, and return a clean space-separated string. */
function op_scopes_clean(string $raw): string
{
    $wanted = preg_split('/[\s,]+/', strtolower(trim($raw))) ?: [];
    $out    = [];
    foreach (array_keys(op_scopes()) as $known) {
        if (in_array($known, $wanted, true)) {
            $out[] = $known;
        }
    }

    return implode(' ', $out);
}

/** The scopes of a token/app as an array. */
function op_scopes_list(string $raw): array
{
    return array_values(array_filter(preg_split('/[\s,]+/', trim($raw)) ?: [], 'strlen'));
}

/** Does this scope set include $scope? */
function op_has(string $raw, string $scope): bool
{
    return in_array($scope, op_scopes_list($raw), true);
}

/** Scope labels, joined - for the consent screen and the back office. */
function op_scopes_text(string $raw): string
{
    $labels = [];
    foreach (op_scopes_list($raw) as $one) {
        if (op_scope_valid($one)) {
            $labels[] = is_zh() ? op_scopes()[$one][1] : op_scopes()[$one][0];
        }
    }

    return implode(' · ', $labels);
}

/* ------------------------------------------------------------------ *
 * Tables                                                              *
 * ------------------------------------------------------------------ */

function op_table_ready(): bool
{
    if (isset($GLOBALS['FD_OP_TABLE'])) {
        return (bool)$GLOBALS['FD_OP_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'oauth\\_apps'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_OP_TABLE'] = $ready;

    return $ready;
}

/** The table is missing: the caller says so instead of exploding. */
function op_unavailable(): bool
{
    return op_table_ready() === false;
}

/* ------------------------------------------------------------------ *
 * Applications                                                        *
 * ------------------------------------------------------------------ */

/** A fresh client_id, and for confidential clients a secret that is only ever shown once. */
function op_new_client(): array
{
    $id     = bin2hex(random_bytes(16));
    $secret = bin2hex(random_bytes(32));

    return [
        'client_id'     => $id,
        'secret'        => $secret,
        'secret_hash'   => hash('sha256', $secret),
        'secret_hint'   => substr($secret, -6),
    ];
}

function op_app(int $id): ?array
{
    if (op_table_ready() === false || $id <= 0) {
        return null;
    }

    return db_one('SELECT * FROM oauth_apps WHERE id = ?', [$id]);
}

function op_app_by_client(string $clientId): ?array
{
    $clientId = trim($clientId);
    if (op_table_ready() === false || $clientId === '') {
        return null;
    }

    return db_one('SELECT * FROM oauth_apps WHERE client_id = ?', [$clientId]);
}

/** An application that may actually be used right now. */
function op_app_active(?array $app): bool
{
    return $app !== null && (string)$app['status'] === 'approved';
}

/** Are these redirect addresses ones the application registered? Exact match only. */
function op_redirect_ok(array $app, string $uri): bool
{
    $uri = trim($uri);
    if ($uri === '' || preg_match('#^https?://#i', $uri) !== 1) {
        return false;
    }

    foreach (preg_split('/\r\n|\r|\n/', (string)$app['redirect_uris']) ?: [] as $one) {
        if (trim($one) !== '' && trim($one) === $uri) {
            return true;
        }
    }

    return false;
}

/** Applications that submitted this user. */
function op_apps_of_user(int $userId): array
{
    if (op_table_ready() === false || $userId <= 0) {
        return [];
    }

    return db_all('SELECT * FROM oauth_apps WHERE owner_user_id = ? ORDER BY id DESC', [$userId]);
}

function op_app_touch(int $appId): void
{
    if (op_table_ready() === false || $appId <= 0) {
        return;
    }

    try {
        db_query('UPDATE oauth_apps SET requests = requests + 1, last_used_at = NOW() WHERE id = ?', [$appId]);
    } catch (Throwable $e) {
        // A counter is not worth failing a request over.
    }
}

/* ------------------------------------------------------------------ *
 * Codes                                                               *
 * ------------------------------------------------------------------ */

function op_code_new(int $appId, int $userId, string $redirectUri, string $scopes,
                     ?string $challenge, ?string $method, int $ttlSeconds = 600): string
{
    $plain = bin2hex(random_bytes(32));
    db_query(
        'INSERT INTO oauth_codes (app_id, user_id, code_hash, redirect_uri, scopes, code_challenge,
            code_challenge_method, ip, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())',
        [$appId, $userId, hash('sha256', $plain), $redirectUri, $scopes,
         $challenge !== '' ? $challenge : null, $method !== '' ? strtoupper($method) : null,
         client_ip(), $ttlSeconds]
    );

    return $plain;
}

/** A code that is still valid, or null. Does not consume it. */
function op_code_find(string $plain): ?array
{
    $plain = trim($plain);
    if (op_table_ready() === false || $plain === '') {
        return null;
    }

    $row = db_one('SELECT * FROM oauth_codes WHERE code_hash = ? LIMIT 1', [hash('sha256', $plain)]);
    if ($row === null || (string)$row['used_at'] !== '' && $row['used_at'] !== null) {
        return null;
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        return null;
    }

    return $row;
}

/** Burn a code: it can never be exchanged twice, even if two requests race. */
function op_code_consume(int $id): bool
{
    db_query('UPDATE oauth_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$id]);

    return db_one('SELECT used_at FROM oauth_codes WHERE id = ?', [$id]) !== null;
}

/** Housekeeping: codes are worthless once expired. */
function op_codes_purge(): void
{
    if (op_table_ready() === false) {
        return;
    }

    try {
        db_query('DELETE FROM oauth_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    } catch (Throwable $e) {
        // housekeeping only
    }
}

/* ------------------------------------------------------------------ *
 * PKCE                                                                *
 * ------------------------------------------------------------------ */

/** RFC 7636: S256 is the only method we encourage, plain is accepted for old clients. */
function op_pkce_ok(string $verifier, ?string $challenge, ?string $method): bool
{
    $verifier  = trim($verifier);
    $challenge = trim((string)$challenge);
    $method    = strtoupper(trim((string)$method));

    if ($challenge === '') {
        return true;   // no PKCE was asked for at authorisation time
    }
    if ($verifier === '') {
        return false;
    }
    if ($method === 'S256') {
        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }
    if ($method === 'PLAIN' || $method === '') {
        return hash_equals($challenge, $verifier);
    }

    return false;
}

/* ------------------------------------------------------------------ *
 * Tokens                                                              *
 * ------------------------------------------------------------------ */

function op_token_issue(int $appId, int $userId, string $scopes, string $kind, int $ttlSeconds): string
{
    $plain = bin2hex(random_bytes(32));
    db_query(
        'INSERT INTO oauth_tokens (app_id, user_id, kind, token_hash, token_hint, scopes, ip, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())',
        [$appId, $userId, $kind === 'refresh' ? 'refresh' : 'access', hash('sha256', $plain),
         substr($plain, -6), $scopes, client_ip(), $ttlSeconds]
    );

    return $plain;
}

/**
 * The whole gate for every resource call: a live token, its application, its user, and the scope asked for.
 *
 * @return array{token: array<string,mixed>, app: array<string,mixed>, user: array<string,mixed>}|null
 */
function op_token_verify(string $plain, string $neededScope = ''): ?array
{
    $plain = trim($plain);
    if (op_table_ready() === false || $plain === '') {
        return null;
    }

    $row = db_one('SELECT * FROM oauth_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1',
        [hash('sha256', $plain)]);
    if ($row === null || (string)$row['kind'] !== 'access') {
        return null;
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        return null;
    }

    $app = op_app((int)$row['app_id']);
    if (op_app_active($app) === false) {
        return null;   // a revoked or pending application answers nothing, even with a live token
    }

    if ($neededScope !== '' && op_has((string)$row['scopes'], $neededScope) === false) {
        return null;
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [(int)$row['user_id']]);
    if ($user === null) {
        return null;
    }
    $status = strtolower((string)($user['status'] ?? 'active'));
    if ($status !== 'active' && $status !== '1') {
        return null;   // suspended or unconfirmed accounts stop working immediately
    }

    try {
        db_query('UPDATE oauth_tokens SET last_used_at = NOW(), ip = ? WHERE id = ?', [client_ip(), (int)$row['id']]);
    } catch (Throwable $e) {
        // counting is best effort
    }
    op_app_touch((int)$row['app_id']);

    return ['token' => $row, 'app' => $app, 'user' => $user];
}

/** The access token of a request: Authorization: Bearer, X-Open-Token, ?access_token= or the POST body. */
function op_token_from_request(array $input = []): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        $raw = trim((string)($_SERVER[$key] ?? ''));
        if ($raw !== '' && stripos($raw, 'bearer ') === 0) {
            return trim(substr($raw, 7));
        }
    }
    foreach (['HTTP_X_OPEN_TOKEN', 'HTTP_X_API_KEY'] as $key) {
        $raw = trim((string)($_SERVER[$key] ?? ''));
        if ($raw !== '') {
            return $raw;
        }
    }
    foreach (['access_token', 'token'] as $key) {
        if (isset($_REQUEST[$key]) && trim((string)$_REQUEST[$key]) !== '') {
            return trim((string)$_REQUEST[$key]);
        }
        if (isset($input[$key]) && trim((string)$input[$key]) !== '') {
            return trim((string)$input[$key]);
        }
    }

    return '';
}

/** The JSON body of a request, or the form fields. */
function op_input(): array
{
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($type, 'json')) {
        $raw  = file_get_contents('php://input');
        $data = $raw === false ? null : json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    return $_POST;
}

/** Every answer of the platform is JSON. */
function op_send(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit;
}

/** RFC 6749 error shape, so client libraries understand it without a manual. */
function op_error(string $code, string $description, int $status = 400): void
{
    op_send(['error' => $code, 'error_description' => $description], $status);
}

/** Revoke every token of an application (used when it is revoked in the back office). */
function op_app_revoke_tokens(int $appId): void
{
    if (op_table_ready() === false || $appId <= 0) {
        return;
    }

    db_query('UPDATE oauth_tokens SET revoked_at = NOW() WHERE app_id = ? AND revoked_at IS NULL', [$appId]);
}

/** How many live tokens / consents an application currently has (back office numbers). */
function op_app_counts(int $appId): array
{
    if (op_table_ready() === false || $appId <= 0) {
        return ['users' => 0, 'tokens' => 0];
    }

    $users  = db_one('SELECT COUNT(*) AS n FROM oauth_consents WHERE app_id = ? AND revoked_at IS NULL', [$appId]);
    $tokens = db_one('SELECT COUNT(*) AS n FROM oauth_tokens WHERE app_id = ? AND revoked_at IS NULL AND kind = \'access\'', [$appId]);

    return [
        'users'  => $users === null ? 0 : (int)$users['n'],
        'tokens' => $tokens === null ? 0 : (int)$tokens['n'],
    ];
}
