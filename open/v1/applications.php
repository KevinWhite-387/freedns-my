<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the state of a holder's requests, quota and counts.
 *
 *   GET /open/v1/applications.php       scope: applications
 *   Authorization: Bearer <access token>
 *
 * What a dashboard needs and nothing more: how much of the allowance is used, how the names are spread over the
 * states, and which record changes are still waiting for a human. No applicant details, no notes from the review,
 * no e-mail - those stay inside the console.
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

$auth = op_token_verify($plain, 'applications');
if ($auth === null) {
    header('WWW-Authenticate: Bearer realm="freedns.my", error="insufficient_scope"');
    op_error('insufficient_scope', 'This token may not read the requests and quota of this account.', 403);
}

$user   = $auth['user'];
$userId = (int)$user['id'];
$notes  = [];

// ---- names, by state ------------------------------------------------------------------------------
$byState = ['active' => 0, 'pending' => 0, 'rejected' => 0, 'suspended' => 0, 'deleted' => 0, 'other' => 0];
$total   = 0;
$names   = [];
foreach (user_domains($user) as $domain) {
    $state = strtolower((string)($domain['status'] ?? ''));
    if (!array_key_exists($state, $byState)) {
        $state = 'other';
    }
    $byState[$state]++;
    $total++;
    $names[] = [
        'name'       => (string)$domain['name'],
        'zone'       => (string)$domain['zone'],
        'status'     => (string)($domain['status'] ?? ''),
        'reserved'   => (int)($domain['reserved'] ?? 0) === 1,
        'delegated'  => (int)($domain['delegated'] ?? 0) === 1,
        'created_at' => (string)($domain['created_at'] ?? ''),
    ];
}

// ---- quota ---------------------------------------------------------------------------------------
$unlimited = (int)($user['quota_unlimited'] ?? 0) === 1;
$byZone    = [];
try {
    foreach (db_all('SELECT zone, COUNT(*) AS n FROM domains WHERE registrant_email = ? GROUP BY zone',
        [(string)$user['email']]) as $row) {
        $byZone[(string)$row['zone']] = (int)$row['n'];
    }
} catch (Throwable $e) {
    $notes[] = 'The per-suffix counts could not be read.';
}

$quota = [
    'used'      => $total,
    'unlimited' => $unlimited,
    'limit'     => $unlimited ? null : (isset($user['quota_limit']) && $user['quota_limit'] !== null
        ? (int)$user['quota_limit'] : null),
    'bonus'     => isset($user['quota_bonus']) ? (int)$user['quota_bonus'] : 0,
    'by_zone'   => $byZone,
];

// ---- change requests still waiting -----------------------------------------------------------------
$waiting = [];
try {
    $rows = db_all(
        "SELECT c.id, c.status, c.submitted_at, d.name AS domain
           FROM change_requests c
           JOIN domains d ON d.id = c.domain_id
          WHERE d.registrant_email = ?
          ORDER BY c.id DESC LIMIT 50",
        [(string)$user['email']]
    );
    foreach ($rows as $row) {
        $waiting[] = [
            'id'           => (int)$row['id'],
            'domain'       => (string)$row['domain'],
            'status'       => (string)$row['status'],
            'submitted_at' => (string)$row['submitted_at'],
        ];
    }
} catch (Throwable $e) {
    $notes[] = 'The change requests could not be read.';
}

$pendingCount = 0;
foreach ($waiting as $one) {
    if (strtolower($one['status']) === 'pending') {
        $pendingCount++;
    }
}

op_send([
    'ok'             => true,
    'quota'          => $quota,
    'counts'         => ['domains' => $total, 'by_state' => $byState, 'changes_pending' => $pendingCount],
    'domains'        => $names,
    'change_requests' => $waiting,
    'notes'          => $notes,
]);
