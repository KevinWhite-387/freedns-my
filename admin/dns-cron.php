<?php
declare(strict_types=1);

/**
 * freedns.my - cron entry point for the Cloudflare reconciliation.
 *
 * Called with the key from the DNS page (Settings -> DNS):
 *     https://freedns.my/admin/dns-cron.php?key=<cf_cron_key>
 *
 * It republishes every active name, so anything changed by hand in the Cloudflare dashboard is put
 * back. The answer is plain text for the cron log. Admin sign-in is not required - the key is the
 * credential - so keep the address and the key to yourself.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$expected = trim((string)setting('cf_cron_key', ''));
$given    = trim((string)($_GET['key'] ?? ''));

if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo "403 forbidden\n\nGenerate a key on the DNS page and call this address with ?key=<key>.\n";
    exit;
}

if (!cf_enabled() && !dnshe_enabled()) {
    http_response_code(409);
    echo "409 conflict\n\nAutomatic publishing is switched off (Administration -> DNS).\n";
    exit;
}

if (!publish_mapping_ready()) {
    http_response_code(409);
    echo "409 conflict\n\nThe dns_records table is missing; import upgrade-cloudflare.sql.\n";
    exit;
}

$started = microtime(true);
$summary = publish_reconcile_all();
$took    = number_format(microtime(true) - $started, 2);

log_action(
    'cf_cron',
    'dns',
    null,
    'checked ' . $summary['checked'] . ', changed ' . $summary['changed'] . ', failed ' . $summary['failed']
);

echo ($summary['ok'] ? "ok" : "failed") . "\n\n";
echo 'checked : ' . $summary['checked'] . "\n";
echo 'changed : ' . $summary['changed'] . "\n";
echo 'failed  : ' . $summary['failed'] . "\n";
echo 'seconds : ' . $took . "\n";

if ($summary['messages'] !== []) {
    echo "\nmessages:\n";
    foreach (array_slice($summary['messages'], 0, 30) as $message) {
        echo '  - ' . $message . "\n";
    }
}
