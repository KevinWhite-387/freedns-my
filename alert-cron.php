<?php
declare(strict_types=1);

/**
 * freedns.my - the publishing watchdog.
 *
 * Call it from cron, for example every 30 minutes:
 *   curl -s "https://freedns.my/alert-cron.php?key=<the cron key from the settings>&limit=25"
 *
 * It reads what the DNS service really holds for the next few active names, compares it with the records
 * stored here, remembers the result, and mails the operator when a name has a NEW problem (a different
 * diff than last time). Identical problems are not mailed again, so a known outage does not flood the
 * mailbox. Nothing is changed here: repairing happens under "Published records" or with the reconcile run.
 *
 * The answer is plain text: one line per name plus a summary. No session, no cookie, no HTML.
 */

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key = trim((string)($_GET['key'] ?? $_REQUEST['key'] ?? ''));
$expected = trim((string)setting('cf_cron_key', ''));

if ($expected === '') {
    http_response_code(503);
    echo "no cron key is set; add one in the back office first\n";
    exit;
}
if ($key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

$tableReady = false;
try {
    $tableReady = db_one("SHOW TABLES LIKE 'domain\\_checks'") !== null;
} catch (Throwable $e) {
    $tableReady = false;
}
if (!$tableReady) {
    http_response_code(503);
    echo "the domain_checks table is missing; import upgrade-alerts.sql\n";
    exit;
}

$limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));

/** Turns a record list into comparable "sub|type|value" keys. */
$keys = static function (array $records, string $domainName): array {
    $out = [];
    foreach ($records as $record) {
        $sub   = normalize_record_sub((string)($record['sub'] ?? $record['name'] ?? ''), $domainName);
        $type  = strtoupper((string)($record['type'] ?? ''));
        $value = normalize_record_value($type, (string)($record['value'] ?? $record['content'] ?? ''));
        $out[strtolower(record_sub_display($sub)) . '|' . $type . '|' . $value] = true;
    }

    return $out;
};

// Names that were not checked for the longest time come first, so a normal run spreads over everything.
$names = db_all(
    "SELECT d.* FROM domains d LEFT JOIN domain_checks c ON c.domain_id = d.id
     WHERE d.status = 'active'
     ORDER BY (c.checked_at IS NULL) DESC, c.checked_at ASC, d.id ASC LIMIT " . $limit
);

$checked = 0;
$fine    = 0;
$broken  = 0;
$new     = 0;
$alerts  = [];

foreach ($names as $domain) {
    $id   = (int)$domain['id'];
    $name = (string)$domain['name'];
    $zone = (string)$domain['zone'];

    $stored = [];
    foreach (domain_records($id) as $record) {
        $stored[] = [
            'sub'   => (string)($record['name'] ?? ''),
            'type'  => (string)($record['type'] ?? ''),
            'value' => (string)($record['value'] ?? ''),
        ];
    }
    $have = $keys($stored, $name);

    $answer = publish_name_records($zone, $name);
    $error  = empty($answer['ok']) ? (string)($answer['error'] ?? 'the service did not answer') : '';
    $live   = $error === '' ? $keys((array)($answer['records'] ?? []), $name) : [];
    $have   = $keys($stored, $name);

    $missing = [];
    $extra   = [];
    if ($error === '') {
        foreach (array_keys($have) as $k) {
            if (!isset($live[$k])) {
                $missing[] = $k;
            }
        }
        foreach (array_keys($live) as $k) {
            if (!isset($have[$k])) {
                $extra[] = $k;
            }
        }
    }

    $ok    = ($error === '' && $missing === [] && $extra === []);
    $hash  = md5($error . '|' . implode(',', $missing) . '|' . implode(',', $extra));
    $before = db_one('SELECT alert_hash, alerted_at FROM domain_checks WHERE domain_id = ?', [$id]);

    db_query(
        'INSERT INTO domain_checks (domain_id, ok, stored_count, live_count, missing, extra, error,
            checked_at, alert_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE ok = VALUES(ok), stored_count = VALUES(stored_count),
            live_count = VALUES(live_count), missing = VALUES(missing), extra = VALUES(extra),
            error = VALUES(error), checked_at = VALUES(checked_at)',
        [
            $id, $ok ? 1 : 0, count($have), count($live),
            $missing === [] ? null : implode("\n", $missing),
            $extra === [] ? null : implode("\n", $extra),
            $error === '' ? null : substr($error, 0, 255),
            $hash,
        ]
    );

    $checked++;
    $ok ? $fine++ : $broken++;

    // A mail only when this problem is new (or different from the last one that was reported).
    if (!$ok) {
        $previousHash = $before === null ? '' : (string)($before['alert_hash'] ?? '');
        if ($previousHash !== $hash) {
            $new++;
            $alerts[] = $name . "\n  stored: " . count($have) . " record(s), upstream: " . count($live)
                . ($error !== '' ? "\n  the service said: " . $error : '')
                . ($missing === [] ? '' : "\n  missing upstream:\n    " . implode("\n    ", $missing))
                . ($extra === [] ? '' : "\n  only upstream:\n    " . implode("\n    ", $extra));
            db_query('UPDATE domain_checks SET alert_hash = ?, alerted_at = NOW() WHERE domain_id = ?',
                [$hash, $id]);
        }
    } elseif ($before !== null && (string)($before['alert_hash'] ?? '') !== '') {
        // Back to normal: remember it without mailing, so the next problem is reported again.
        db_query('UPDATE domain_checks SET alert_hash = NULL WHERE domain_id = ?', [$id]);
    }

    echo $name . ': ' . ($ok ? 'ok' : 'PROBLEM') . "\n";
}

if ($alerts !== []) {
    $admin = trim((string)setting('notify_admin_email', ''));
    if ($admin !== '') {
        $previous = lang_switch('en');
        send_mail(
            $admin,
            t('mail.alert_subject', (string)$new),
            implode("\n", array_merge(
                [t('mail.dear', $admin), '', t('mail.alert_intro', (string)$new), ''],
                $alerts,
                ['', rtrim(app_base_url(), '/') . '/admin/verify.php', '',
                 t('mail.alert_footer'), '', (string)setting('site_title', 'freedns.my')]
            ))
        );
        lang_switch($previous);
    }
    log_action('alert_mail', 'settings', null, $new . ' new publishing problem(s) reported');
}

echo "\nchecked " . $checked . ", fine " . $fine . ", problems " . $broken . ", newly reported " . $new . "\n";

// The same cron run also nudges the holders whose bound cloud server expires soon (when that feature is on).
if (function_exists('rainyun_tables_ready') && rainyun_tables_ready()) {
    $srv = rainyun_remind(50);
    echo 'servers: ' . (int)$srv['checked'] . " expiring, " . (int)$srv['mailed'] . " reminder(s) sent\n";
}

// Protection mode, when it switched itself on because of an attack, switches itself off again here.
if (setting_bool('hold_mode', false)) {
    $until = trim((string)setting('hold_auto_until', ''));
    if ($until !== '' && strtotime($until) !== false && strtotime($until) <= time()) {
        set_setting('hold_mode', '0');
        set_setting('hold_auto_until', '');
        log_action('hold_auto_off', 'settings', null,
            'protection mode switched itself off (the window that started at '
            . (string)setting('hold_auto_at', '?') . ' is over)');
        echo "protection mode: switched off again\n";

        $admin = trim((string)setting('notify_admin_email', ''));
        if ($admin !== '') {
            $previous = lang_switch('en');
            send_mail($admin, 'freedns.my: protection mode is off again', implode("\n", [
                t('mail.dear', $admin), '',
                'The holding page switched itself off; the site answers normally again.',
                'If it comes back, the addresses are still being limited - look under Holding page.',
                '',
                rtrim(app_base_url(), '/') . '/admin/holds.php',
            ]));
            lang_switch($previous);
        }
    } elseif ($until !== '') {
        echo 'protection mode: on until ' . $until . "\n";
    }
}
