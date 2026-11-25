<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: who is signed in.
 *
 *   GET /oauth/userinfo.php          Authorization: Bearer <access token>
 *
 * The smallest useful answer, and the one every "Sign in with …" integration calls first: who the token belongs
 * to. It needs the "profile" scope and nothing else, and it never returns anything the token's own scopes do not
 * cover - counts only appear when the application was also granted "applications".
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

if (op_unavailable()) {
    op_error('temporarily_unavailable', 'The open platform is not set up yet.', 503);
}

$input = op_input();
$plain = op_token_from_request($input);
if ($plain === '') {
    header('WWW-Authenticate: Bearer realm="freedns.my"');
    op_error('invalid_token', 'Send the access token: Authorization: Bearer <token>.', 401);
}

$auth = op_token_verify($plain, 'profile');
if ($auth === null) {
    header('WWW-Authenticate: Bearer realm="freedns.my", error="invalid_token"');
    op_error('invalid_token', 'The token is unknown, expired, revoked, or not allowed to read the profile.', 401);
}

$user   = $auth['user'];
$scopes = (string)$auth['token']['scopes'];
$userId = (int)$user['id'];

// "Confirmed" is the same test the console uses: active, with no pending verification token.
$verified = strtolower((string)($user['status'] ?? '')) === 'active'
    && trim((string)($user['verify_token'] ?? '')) === '';

$out = [
    'ok'              => true,
    'sub'             => (string)$userId,
    'username'        => (string)$user['username'],
    'email'           => (string)$user['email'],
    'email_verified'  => $verified,
    'language'        => (string)($user['language'] ?? 'en'),
    'created_at'      => (string)($user['created_at'] ?? ''),
    'client_id'       => (string)$auth['app']['client_id'],
    'scope'           => $scopes,
];

if (op_has($scopes, 'applications')) {
    $counts = ['domains' => 0, 'active' => 0, 'pending' => 0];
    try {
        foreach (user_domains($user) as $domain) {
            $counts['domains']++;
            $state = strtolower((string)($domain['status'] ?? ''));
            if ($state === 'active') {
                $counts['active']++;
            } elseif ($state === 'pending') {
                $counts['pending']++;
            }
        }
    } catch (Throwable $e) {
        $counts = null;   // a count is never worth failing a sign-in over
    }
    if ($counts !== null) {
        $out['counts'] = $counts;
    }
}

op_send($out);
