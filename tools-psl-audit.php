<?php
declare(strict_types=1);

/**
 * freedns.my - audit what is actually published, zone by zone.
 *
 * The registry knows which names were registered; it does not know which of them answer DNS right
 * now, because it keeps no query log. This script closes that gap the honest way: it asks the
 * authoritative servers for every name and records what comes back.
 *
 * Run:
 *   sudo -u www php tools-psl-audit.php                          # all zones, every active name
 *   sudo -u www php tools-psl-audit.php --zone=kz.ci --sample=300
 *   sudo -u www php tools-psl-audit.php --csv=/tmp/psl-audit.csv
 *
 * Output columns
 *   registered      rows in `domains` with status 'active' for that zone
 *   with_records    of those, how many have at least one A / AAAA / CNAME row in `records`
 *   answering       of those, how many actually answered just now (A, AAAA or CNAME)
 *   accounts        distinct registrant e-mail addresses holding at least one name
 *
 * "answering" is the only number a reviewer can reproduce without trusting us, which is exactly
 * why it is measured here with a live query instead of being derived from our own tables.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/inc/bootstrap.php';

$zone   = '';
$sample = 0;
$csv    = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--zone='))   { $zone   = strtolower(trim(substr($arg, 7))); }
    if (str_starts_with($arg, '--sample=')) { $sample = max(1, (int)substr($arg, 9)); }
    if (str_starts_with($arg, '--csv='))    { $csv    = (string)substr($arg, 6); }
}

$zones = db_all("SELECT name FROM zones ORDER BY name ASC");
if ($zone !== '') {
    $zones = array_values(array_filter($zones, static fn($z) => (string)$z['name'] === $zone));
    if ($zones === []) {
        fwrite(STDERR, "Unknown zone: " . $zone . "\n");
        exit(1);
    }
}

function audit_zone(string $zone, int $sample, array &$csvRows): array
{
    $registered = (int)db_value(
        "SELECT COUNT(*) FROM domains WHERE zone = ? AND status = 'active'", [$zone], 0
    );
    $withRecords = (int)db_value(
        "SELECT COUNT(*) FROM domains d WHERE d.zone = ? AND d.status = 'active'
           AND EXISTS (SELECT 1 FROM records r WHERE r.domain_id = d.id AND r.type IN ('A','AAAA','CNAME'))",
        [$zone], 0
    );
    $accounts = (int)db_value(
        "SELECT COUNT(DISTINCT registrant_email) FROM domains WHERE zone = ? AND status = 'active'",
        [$zone], 0
    );

    $sql = "SELECT DISTINCT d.name FROM domains d
              WHERE d.zone = ? AND d.status = 'active'
                AND EXISTS (SELECT 1 FROM records r WHERE r.domain_id = d.id AND r.type IN ('A','AAAA','CNAME'))
              ORDER BY d.name ASC";
    $names = db_all($sql, [$zone]);
    if ($sample > 0 && count($names) > $sample) {
        shuffle($names);
        $names = array_slice($names, 0, $sample);
        sort($names);
    }

    $answering = 0;
    $checked   = 0;
    foreach ($names as $row) {
        $name = (string)$row['name'];
        $checked++;
        $ok = false;
        try {
            foreach ([DNS_A, DNS_AAAA, DNS_CNAME] as $type) {
                $res = @dns_get_record($name, $type);
                if (is_array($res) && $res !== []) {
                    $ok = true;
                }
            }
        } catch (Throwable $e) {
            $ok = false;
        }
        if ($ok) {
            $answering++;
        }
        if ($csv !== '') {
            $csvRows[] = [$zone, $name, $ok ? 'yes' : 'no'];
        }
        if ($checked % 200 === 0) {
            fwrite(STDERR, '  ' . $zone . ': checked ' . $checked . '/' . count($names) . "\n");
        }
    }

    return [
        'zone'         => $zone,
        'registered'   => $registered,
        'with_records' => $withRecords,
        'checked'      => $checked,
        'answering'    => $answering,
        'accounts'     => $accounts,
    ];
}

$csvRows = [];
$results = [];
foreach ($zones as $z) {
    $results[] = audit_zone((string)$z['name'], $sample, $csvRows);
}

printf("\n%-10s %12s %14s %10s %10s %10s\n", 'zone', 'registered', 'with_records', 'checked', 'answering', 'accounts');
foreach ($results as $r) {
    printf(
        "%-10s %12d %14d %10d %10d %10d\n",
        $r['zone'], $r['registered'], $r['with_records'], $r['checked'], $r['answering'], $r['accounts']
    );
}
$t = ['registered' => 0, 'with_records' => 0, 'checked' => 0, 'answering' => 0];
foreach ($results as $r) {
    foreach ($t as $k => $_) {
        $t[$k] += (int)$r[$k];
    }
}
printf("\n%-10s %12d %14d %10d %10d\n", 'TOTAL', $t['registered'], $t['with_records'], $t['checked'], $t['answering']);

if ($csv !== '') {
    $fh = fopen($csv, 'wb');
    if ($fh !== false) {
        fputcsv($fh, ['zone', 'name', 'answering']);
        foreach ($csvRows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
        echo "\nCSV written to " . $csv . " (" . count($csvRows) . " rows)\n";
    }
}

echo "\nNothing was modified. Before quoting these numbers anywhere, make sure that\n"
   . "Settings -> Home page counters has no override filled in, so the site shows\n"
   . "the same figures this script measured.\n";