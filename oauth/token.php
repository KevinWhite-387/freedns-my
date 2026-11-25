<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the token endpoint.
 *
 *   POST /oauth/token.php
 *     grant_type=authorization_code & code=… & redirect_uri=… & client_id=… & code_verifier=…
 *     grant_type=refresh_token    & refresh_token=… & client_id=…
 *
 * Client authentication, exactly as the application was registered:
 *   * a confidential application sends client_secret (in the body, or as HTTP Basic);
 *   * a public application (browser, mobile) has no secret at all and must use PKCE - which is why a code that
 *     was issued with a challenge can never be redeemed without the matching verifier.
 *
 * Two habits that make a stolen token less useful:
 *   * refresh tokens rotate - using one revokes it and returns a new one, so a leaked copy is detectable;
 *   * every answer is no-store, and errors follow RFC 6749 so client libraries handle them without a manual.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    op_error('invalid_request', 'This endpoint answers POST only.', 405);
}

if (op_unavailable()) {
    op_error('temporarily_unavailable', 'The open platform is not set up yet.', 503);
}

$input = op_input();

/** Read a field from the JSON body or from the form. */
$field = static function (string $name) use ($input): string {
    if (isset($input[$name]) && is_scalar($input[$name])) {
        return trim((string)$input[$name]);
    }
    if (isset($_POST[$name]) && is_scalar($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }

    return '';
};

$grantType = $field('grant_type');
$clientId  = $field('client_id');

// HTTP Basic is still the usual way for a server-to-server client.
if ($clientId === '' && isset($_SERVER['PHP_AUTH_USER'])) {
    $clientId = trim((string)$_SERVER['PHP_AUTH_USER']);
    if ($field('client_secret') === '' && isset($_SERVER['PHP_AUTH_PW'])) {
        $input['client_secret'] = (string)$_SERVER['PHP_AUTH_PW'];
    }
}

$app = op_app_by_client($clientId);
if ($app === null) {
    op_error('invalid_client', 'Unknown client_id.', 401);
}
if (op_app_active($app) === false) {
    op_error('invalid_client', 'This application is not approved, or it has been revoked.', 401);
}

// ---- client authentication -------------------------------------------------------------------------
$secret = $field('client_secret');
if ((int)$app['is_public'] === 1) {
    // A public client proves itself with PKCE instead of a secret; nothing to check here.
} else {
    $stored = (string)($app['client_secret_hash'] ?? '');
    if ($secret === '' || $stored === '' || hash_equals($stored, hash('sha256', $secret)) === false) {
        op_error('invalid_client', 'The client_secret is missing or wrong.', 401);
    }
}

/** The answer both grants end with. */
$respond = static function (array $app, int $userId, string $scopes) : void {
    $access  = op_token_issue((int)$app['id'], $userId, $scopes, 'access', 3600);
    $refresh = op_token_issue((int)$app['id'], $userId, $scopes, 'refresh', 2592000);   // 30 days
    log_action('open_token', 'user', $userId, 'access token issued to ' . (string)$app['name'] . ' (' . $scopes . ')');
    op_send([
        'access_token'  => $access,
        'token_type'    => 'Bearer',
        'expires_in'    => 3600,
        'refresh_token' => $refresh,
        'scope'         => $scopes,
    ]);
};

// ---- grant: authorisation code ---------------------------------------------------------------------
if ($grantType === 'authorization_code') {
    $code        = $field('code');
    $redirectUri = $field('redirect_uri');
    $verifier    = $field('code_verifier');

    if ($code === '') {
        op_error('invalid_request', 'code is required.');
    }

    $row = op_code_find($code);
    if ($row === null) {
        op_error('invalid_grant', 'That code is unknown, already used, or expired.', 400);
    }
    if ((int)$row['app_id'] !== (int)$app['id']) {
        op_error('invalid_grant', 'That code was issued to another application.', 400);
    }
    if (trim((string)$row['redirect_uri']) !== $redirectUri) {
        op_error('invalid_grant', 'redirect_uri has to be the one the code was issued for.', 400);
    }
    if (op_pkce_ok($verifier, (string)($row['code_challenge'] ?? ''), (string)($row['code_challenge_method'] ?? '')) === false) {
        op_error('invalid_grant', 'The PKCE code_verifier does not match the code_challenge.', 400);
    }

    // One code, one exchange - even if two requests arrive at the same moment.
    $before = (string)($row['used_at'] ?? '');
    op_code_consume((int)$row['id']);
    $after = db_one('SELECT used_at FROM oauth_codes WHERE id = ?', [(int)$row['id']]);
    if ($before !== '' || $after === null || (string)$after['used_at'] === '') {
        op_error('invalid_grant', 'That code has just been used.', 400);
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [(int)$row['user_id']]);
    if ($user === null) {
        op_error('invalid_grant', 'The account no longer exists.', 400);
    }
    $status = strtolower((string)($user['status'] ?? 'active'));
    if ($status !== 'active' && $status !== '1') {
        op_error('access_denied', 'That account is not active.', 403);
    }

    // The scopes that were actually granted are the ones the user allowed, never what the client asks for now.
    $respond($app, (int)$row['user_id'], op_scopes_clean((string)$row['scopes']));
}

// ---- grant: refresh token --------------------------------------------------------------------------
if ($grantType === 'refresh_token') {
    $plain = $field('refresh_token');
    if ($plain === '') {
        op_error('invalid_request', 'refresh_token is required.');
    }

    $row = db_one('SELECT * FROM oauth_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1',
        [hash('sha256', $plain)]);
    if ($row === null || (string)$row['kind'] !== 'refresh') {
        op_error('invalid_grant', 'That refresh token is unknown or has been revoked.', 400);
    }
    if ((int)$row['app_id'] !== (int)$app['id']) {
        op_error('invalid_grant', 'That refresh token belongs to another application.', 400);
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        op_error('invalid_grant', 'That refresh token has expired; ask the user to sign in again.', 400);
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [(int)$row['user_id']]);
    if ($user === null) {
        op_error('invalid_grant', 'The account no longer exists.', 400);
    }
    $status = strtolower((string)($user['status'] ?? 'active'));
    if ($status !== 'active' && $status !== '1') {
        op_error('access_denied', 'That account is not active.', 403);
    }

    // Rotation: the old refresh token dies here, so a copy that leaked cannot be replayed after the real client
    // has used its own.
    db_query('UPDATE oauth_tokens SET revoked_at = NOW() WHERE id = ?', [(int)$row['id']]);
    $respond($app, (int)$row['user_id'], op_scopes_clean((string)$row['scopes']));
}

op_error('unsupported_grant_type', 'Use authorization_code or refresh_token.');
