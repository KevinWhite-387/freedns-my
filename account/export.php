<?php
declare(strict_types=1);

/**
 * freedns.my - export of everything one account holds (own data, complete addresses).
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$user    = require_user();
$domains = user_domains($user);

$filename = 'freedns-' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$user['username']) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}
// Byte order mark so Excel opens the UTF-8 file correctly.
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['domain', 'zone', 'status', 'request_code', 'name', 'type', 'value', 'ttl']);

foreach ($domains as $domain) {
    $records = domain_records((int)$domain['id']);
    if ($records === []) {
        fputcsv($out, [
            (string)$domain['name'], (string)$domain['zone'], (string)$domain['status'],
            (string)$domain['request_code'], '', '', '', '',
        ]);
        continue;
    }
    foreach ($records as $record) {
        fputcsv($out, [
            (string)$domain['name'],
            (string)$domain['zone'],
            (string)$domain['status'],
            (string)$domain['request_code'],
            record_sub_display((string)$record['sub']),
            (string)$record['type'],
            (string)$record['value'],
            (string)($record['ttl'] ?? ''),
        ]);
    }
}

fclose($out);
log_action('user_export', 'user', (int)$user['id'], (string)$user['username']);
