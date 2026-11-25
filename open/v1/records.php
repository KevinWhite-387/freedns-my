<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the DNS records of one name.
 *
 *   GET    /open/v1/records.php?name=abc.us.ci                      scope: records
 *   POST   /open/v1/records.php?name=abc.us.ci  type=A value=1.2.3.4 [name=@]     scope: records.write
 *   PUT    /open/v1/records.php?name=abc.us.ci  index=0 [type=…] [value=…]        scope: records.write
 *   DELETE /open/v1/records.php?name=abc.us.ci  index=1                           scope: records.write
 *
 *   Authorization: Bearer <access token>
 *
 * Writing goes through exactly the same function the web console uses (api_save_records), so a third party cannot
 * talk its way past a rule a human would be stopped by: record types, values, the one-CNAME-per-name rule, the
 * per-name ceiling, and the publish step are all the same code path. A refused record comes back as 422 with the
 * very sentence the console would have shown.
 *
 * Bodies may be form-encoded or JSON; the index is the position in the list this endpoint returns, so a client
 * reads first and then writes.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

if (op_unavailable()) {
    op_error('temporarily_unavailable', 'The open platform is not set up yet.', 503);
}

$input  = op_input();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$write  = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

$plain = op_token_from_request($input);
if ($plain === '') {
    header('WWW-Authenticate: Bearer realm="freedns.my"');
    op_error('invalid_token', 'Send the access token: Authorization: Bearer <token>.', 401);
}

$auth = op_token_verify($plain, $write ? 'records.write' : 'records');
if ($auth === null) {
    header('WWW-Authenticate: Bearer realm="freedns.my", error="insufficient_scope"');
    op_error('insufficient_scope', $write
        ? 'This token may not change records (the records.write scope is needed).'
        : 'This token may not read records (the records scope is needed).', 403);
}

$user = $auth['user'];

/** A field from the query string, the form or the JSON body. */
$field = static function (string $name) use ($input): string {
    if (isset($input[$name]) && is_scalar($input[$name])) {
        return trim((string)$input[$name]);
    }
    if (isset($_REQUEST[$name]) && is_scalar($_REQUEST[$name])) {
        return trim((string)$_REQUEST[$name]);
    }

    return '';
};

$name = $field('name');
$want = $field('domain');
if ($name === '') {
    $name = $want;
}
if ($name === '') {
    op_error('missing', 'Give the name: ?name=abc.us.ci (see /open/v1/domains.php).');
}

$domain = api_domain_of_user($user, $name);
if ($domain === null) {
    op_error('nohost', 'This account holds no name called "' . $name . '".', 404);
}

// Reading stops here; the rest is the write path.
if ($write === false) {
    op_send([
        'ok'      => true,
        'domain'  => (string)$domain['name'],
        'status'  => (string)$domain['status'],
        'proxied' => (int)($domain['proxied'] ?? 0) === 1,
        'records' => api_record_rows($domain),
    ]);
}

// ---- writing ---------------------------------------------------------------------------------------
// The current set is the starting point, exactly as the console does it: read, change, send the whole set back.
$rows = [];
foreach (domain_records((int)$domain['id']) as $row) {
    $rows[] = [
        'name'  => normalize_record_sub((string)($row['name'] ?? ''), (string)$domain['name']),
        'type'  => strtoupper((string)($row['type'] ?? '')),
        'value' => (string)($row['value'] ?? ''),
    ];
}

$type  = strtoupper($field('type'));
$value = $field('value');

/*
 * The record's own label is its own field: "name" would be ambiguous next to the domain being addressed, so the
 * sub-name is read from sub= (or record=, its twin spelling) and defaults to the apex.
 */
$subRaw = $field('sub');
if ($subRaw === '') {
    $subRaw = $field('record');
}
$sub = normalize_record_sub($subRaw !== '' ? $subRaw : '@', (string)$domain['name']);
if ($sub === '') {
    $sub = '@';
}

$rawIndex = $field('index');
$index    = $rawIndex === '' ? -1 : (int)$rawIndex;

if ($method === 'POST') {
    if ($type === '' || $value === '') {
        op_error('missing', 'type and value are required to add a record (name is optional).');
    }
    $rows[] = ['name' => $sub, 'type' => $type, 'value' => $value];
} elseif ($method === 'PUT' || $method === 'PATCH') {
    if ($index < 0 || !isset($rows[$index])) {
        op_error('norow', 'Give index= of the record to change (read the list first; indexes are positions).', 404);
    }
    if ($type !== '') {
        $rows[$index]['type'] = $type;
    }
    if ($value !== '') {
        $rows[$index]['value'] = $value;
    }
    if ($field('sub') !== '' || $field('record') !== '') {
        $rows[$index]['name'] = $sub;
    }
} else {   // DELETE
    if ($index < 0 || !isset($rows[$index])) {
        op_error('norow', 'Give index= of the record to delete (read the list first).', 404);
    }
    array_splice($rows, $index, 1);
}

$result = api_save_records($domain, $rows);

// api_save_records() answers with the console's own wording when it refuses; pass that on untouched.
if (is_array($result) && isset($result['ok']) && $result['ok'] === false) {
    op_error('record_refused', (string)($result['error'] ?? $result['message'] ?? 'The record was refused.'), 422);
}
if (is_array($result) && isset($result['error']) && $result['error'] !== '') {
    op_error('record_refused', (string)$result['error'], 422);
}

log_action('open_record_write', 'domain', (int)$domain['id'],
    $method . ' by ' . (string)$auth['app']['name'] . ' (' . (string)$auth['app']['client_id'] . ')');

$fresh = db_one('SELECT * FROM domains WHERE id = ?', [(int)$domain['id']]);

op_send([
    'ok'      => true,
    'action'  => strtolower($method),
    'domain'  => (string)$domain['name'],
    'status'  => $fresh === null ? (string)$domain['status'] : (string)$fresh['status'],
    'records' => $fresh === null ? api_record_rows($domain) : api_record_rows($fresh),
]);
