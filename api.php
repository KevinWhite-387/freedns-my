<?php
declare(strict_types=1);

/**
 * freedns.my - the HTTP API.
 *
 *   GET  api.php?action=domains
 *   GET  api.php?action=records&domain=myname.us.ci
 *   POST api.php            action=record.add | record.update | record.delete
 *                           domain=… name=@ type=A value=1.2.3.4 [index=0]
 *
 * Authorisation: "Authorization: Bearer <token>" (or ?token= / X-API-Key for simple clients).
 * Every answer is JSON and mentions the reason of a failure in words.
 */

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!api_enabled()) {
    api_fail('api_disabled', 'The API is switched off or its table is missing.', 503);
}

// Deliberate downtime ("下架维护"): answer 503 with the operator's own note and an expected return time, so a
// client can tell "you are wrong" apart from "we are down" and back off instead of retrying hard.
if (api_maint_on()) {
    $until = api_maint_until();
    header('Retry-After: 3600');
    api_fail(
        'maintenance',
        api_maint_note() . ($until !== '' ? ' (' . (is_zh() ? '预计恢复：' : 'Expected back: ') . $until . ')' : ''),
        503
    );
}

$user = api_current_user();
if ($user === null) {
    api_fail('badauth', 'Send a valid token: Authorization: Bearer <token>.', 401);
}
/*
 * The console stores status as a word ("pending", "active", "suspended"). The line here used to cast it to a
 * number, and (int)'active' is 0 - so every account, including a perfectly good one, was refused with "This
 * account is disabled": a valid key could never be used at all. Compare the word, and say which case it is,
 * because an account whose e-mail is not confirmed yet is not a suspended one.
 */
if (quota_columns_ready()) {
    $fdStatus = strtolower((string)($user['status'] ?? 'active'));
    if ($fdStatus === '0') {
        $fdStatus = 'pending';   // rows written before the words were introduced
    } elseif ($fdStatus === '1') {
        $fdStatus = 'active';
    }
    if ($fdStatus === 'suspended') {
        api_fail('account_suspended', 'This account is suspended.', 403);
    }
    if ($fdStatus !== 'active') {
        api_fail('account_pending',
            'This account is not active yet: open the link in the confirmation e-mail first.', 403);
    }
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'domains')));
$input  = api_input();

if ($action === 'me') {
    api_send(['ok' => true, 'account' => ['username' => (string)$user['username'], 'email' => (string)$user['email']]]);
}

if ($action === 'domains') {
    api_send(['ok' => true, 'domains' => api_domains_of($user)]);
}

if (!in_array($action, ['records', 'record.add', 'record.update', 'record.delete'], true)) {
    api_fail('unknown_action', 'Known actions: me, domains, records, record.add, record.update, record.delete.');
}

$wanted = trim((string)($_REQUEST['domain'] ?? $input['domain'] ?? ''));
$domain = api_domain_of_user($user, $wanted);
if ($domain === null) {
    api_fail('nohost', 'That name does not belong to this account.', 404);
}

if ($action === 'records') {
    api_send([
        'ok'      => true,
        'domain'  => (string)$domain['name'],
        'status'  => (string)$domain['status'],
        'proxied' => (int)($domain['proxied'] ?? 0) === 1,
        'records' => api_record_rows($domain),
    ]);
}

// ---- writing ---------------------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('method', 'Changing records needs POST.', 405);
}

$rows  = [];
foreach (domain_records((int)$domain['id']) as $row) {
    $rows[] = [
        'name'  => normalize_record_sub((string)($row['name'] ?? ''), (string)$domain['name']),
        'type'  => strtoupper((string)($row['type'] ?? '')),
        'value' => (string)($row['value'] ?? ''),
    ];
}

$sub   = (string)($input['name'] ?? $_REQUEST['name'] ?? '@');
$sub   = normalize_record_sub($sub, (string)$domain['name']);
$type  = strtoupper(trim((string)($input['type'] ?? $_REQUEST['type'] ?? '')));
$value = (string)($input['value'] ?? $_REQUEST['value'] ?? '');

if ($action === 'record.add') {
    if ($type === '' || $value === '') {
        api_fail('missing', 'name (optional), type and value are required.');
    }
    $rows[] = ['name' => $sub, 'type' => $type, 'value' => $value];
}

if ($action === 'record.update' || $action === 'record.delete') {
    $index = isset($input['index']) ? (int)$input['index']
        : (isset($_REQUEST['index']) ? (int)$_REQUEST['index'] : -1);
    if ($index < 0 || !isset($rows[$index])) {
        api_fail('norow', 'Give the index of the record (see action=records).', 404);
    }
    if ($action === 'record.delete') {
        array_splice($rows, $index, 1);
    } else {
        if ($type !== '') {
            $rows[$index]['type'] = $type;
        }
        if ($value !== '') {
            $rows[$index]['value'] = $value;
        }
        if (isset($input['name']) || isset($_REQUEST['name'])) {
            $rows[$index]['name'] = $sub;
        }
    }
}

$saved = api_save_records($domain, $rows);
if (!$saved['ok']) {
    api_fail($saved['error'], $saved['message'], $saved['error'] === 'locked' ? 409 : 400);
}

api_send(['ok' => true, 'message' => $saved['message'], 'records' => api_record_rows($domain)]);
