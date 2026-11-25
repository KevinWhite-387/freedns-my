<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the names a holder owns.
 *
 *   GET /open/v1/domains.php                 all of them
 *   GET /open/v1/domains.php?name=abc.us.ci  just one (404 when it is not this holder's)
 *
 *   Authorization: Bearer <access token>     scope: domains
 *
 * Read only, and always read-only: changing a name is not something a third party is allowed to do, and the
 * entries never include the holder's e-mail - that belongs to the "profile" scope, on purpose.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

if (op_unavailable()) {
    op_error('temporarily_unavailable', 'The open platform is not set up yet.', 503);
}

$input = op_input();
$plain = op_token_from_request($input);
if ($plain === '') {
    header('WWW-Authenticate: Bearer realm="freedns.my"');
    op_error('invalid_token', 'Send the access token: Authorization: Bearer <token>.', 401);
}

$auth = op_token_verify($plain, 'domains');
if ($auth === null) {
    header('WWW-Authenticate: Bearer realm="freedns.my", error="insufficient_scope"');
    op_error('insufficient_scope', 'This token may not read the names of this account.', 403);
}

$user = $auth['user'];

/** One name, in the shape the platform promises. */
$shape = static function (array $domain): array {
    $records = 0;
    try {
        $records = count(domain_records((int)$domain['id']));
    } catch (Throwable $e) {
        $records = 0;
    }

    return [
        'name'       => (string)$domain['name'],
        'status'     => (string)$domain['status'],
        'zone'       => (string)$domain['zone'],
        'label'      => (string)($domain['label'] ?? ''),
        'proxied'    => (int)($domain['proxied'] ?? 0) === 1,
        'delegated'  => (int)($domain['delegated'] ?? 0) === 1,
        'reserved'   => (int)($domain['reserved'] ?? 0) === 1,
        'records'    => $records,
        'created_at' => (string)($domain['created_at'] ?? ''),
    ];
};

$wanted = trim((string)($_GET['name'] ?? $input['name'] ?? ''));
if ($wanted !== '') {
    $domain = api_domain_of_user($user, $wanted);
    if ($domain === null) {
        op_error('nohost', 'This account holds no name called "' . $wanted . '".', 404);
    }
    op_send(['ok' => true, 'domain' => $shape($domain)]);
}

$out = [];
foreach (user_domains($user) as $domain) {
    $out[] = $shape($domain);
}

op_send(['ok' => true, 'count' => count($out), 'domains' => $out]);
