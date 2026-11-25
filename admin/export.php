<?php
declare(strict_types=1);

/**
 * freedns.my - administration: export the registry, one zone at a time.
 *
 * export.php                       - page with the zone selector and the download links
 * export.php?zone=X&format=csv     - comma separated list of the active names
 * export.php?zone=X&format=zone    - BIND style zone data (A, AAAA, CNAME, TXT), ready to paste
 * export.php?zone=X&format=json    - machine readable dump
 *
 * The registry answers for the names itself (no name-server delegation yet, because the zones are
 * not in the Public Suffix List), so the zone export contains the records kept here.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Data export');
$zones  = zone_list();
$format = strtolower(trim((string)($_GET['format'] ?? '')));

$zoneName = strtolower(trim((string)($_GET['zone'] ?? '')));
$zone     = $zoneName === '' ? null : zone_by_name($zoneName);

// Unknown zone without a download request: fall back to the default zone instead of showing a
// page with dead links.
if ($zone === null && $format === '') {
    $fallback = default_zone();
    $zone     = $fallback;
    $zoneName = $fallback === null ? '' : strtolower((string)$fallback['name']);
}

if ($format !== '' && $zone === null) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown or missing zone; use export.php?zone=<zone>&format=" . $format . "\n";
    exit;
}

$rows = [];
if ($zone !== null) {
    $rows = db_all(
        "SELECT * FROM domains WHERE status = 'active' AND zone = ? ORDER BY label ASC",
        [(string)$zone['name']]
    );
}

$recordsByDomain = [];
if ($rows !== []) {
    $ids          = array_map(static fn($r) => (int)$r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all(
        'SELECT * FROM records WHERE domain_id IN (' . $placeholders . ')
         ORDER BY domain_id ASC, sub ASC, type ASC, position ASC',
        $ids
    ) as $record) {
        $recordsByDomain[(int)$record['domain_id']][] = $record;
    }
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="freedns-' . $zoneName . '-' . date('Ymd-Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fwrite($out, "\xEF\xBB\xBF"); // BOM so that Excel reads UTF-8 correctly
        fputcsv($out, ['id', 'name', 'zone', 'status', 'request_code', 'registrant_name',
            'registrant_email', 'organization', 'country', 'records', 'created_at', 'decided_at', 'decided_by']);
        foreach ($rows as $row) {
            $flat = [];
            foreach ($recordsByDomain[(int)$row['id']] ?? [] as $record) {
                $flat[] = ((string)$record['sub'] === '' ? '@' : (string)$record['sub']) . ' '
                    . strtoupper((string)$record['type']) . ' ' . (string)$record['value'];
            }
            fputcsv($out, [
                $row['id'], $row['name'], $row['zone'], $row['status'], $row['request_code'],
                $row['registrant_name'], $row['registrant_email'],
                $row['organization'], $row['country'], implode(' | ', $flat),
                $row['created_at'], $row['decided_at'], $row['decided_by'],
            ]);
        }
        fclose($out);
    }
    log_action('export_csv', 'zone', (int)$zone['id'], count($rows) . ' names of ' . $zoneName . ' exported');
    exit;
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="freedns-' . $zoneName . '-' . date('Ymd-Hi') . '.json"');
    $payload = ['zone' => $zoneName, 'ttl' => default_ttl(), 'generated_at' => date('c'), 'domains' => []];
    foreach ($rows as $row) {
        $records = [];
        foreach ($recordsByDomain[(int)$row['id']] ?? [] as $record) {
            $records[] = [
                'sub'   => (string)$record['sub'],
                'name'  => record_fqdn($record, (string)$row['name']),
                'type'  => strtoupper((string)$record['type']),
                'value' => (string)$record['value'],
            ];
        }
        $payload['domains'][] = [
            'name'         => (string)$row['name'],
            'label'        => (string)$row['label'],
            'organization' => (string)($row['organization'] ?? ''),
            'records'      => $records,
            'decided_at'   => (string)($row['decided_at'] ?? ''),
        ];
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    log_action('export_json', 'zone', (int)$zone['id'], count($rows) . ' names of ' . $zoneName . ' exported');
    exit;
}

if ($format === 'zone') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="freedns-' . $zoneName . '-' . date('Ymd-Hi') . '.txt"');
    echo '; zone ' . $zoneName . ' - records served by the registry' . "\n";
    echo '; generated ' . date('Y-m-d H:i') . ', ' . count($rows) . " name(s)\n";
    echo "; paste this into the zone file of " . $zoneName . " and reload the zone\n";
    echo "; the SOA and the NS records of the zone itself stay as they are configured there\n\n";
    echo '$TTL ' . default_ttl() . "\n";
    echo '$ORIGIN ' . $zoneName . ".\n\n";
    $written = 0;
    foreach ($rows as $row) {
        $records = $recordsByDomain[(int)$row['id']] ?? [];
        if ($records === []) {
            continue;
        }
        echo '; ' . (string)$row['name'] . "\n";
        foreach (record_zone_lines($records, (string)$row['name']) as $line) {
            echo $line . "\n";
        }
        echo "\n";
        $written++;
    }
    if ($written === 0) {
        echo "; no active name in this zone yet\n";
    }
    log_action('export_zone', 'zone', (int)$zone['id'], $written . ' names of ' . $zoneName . ' exported');
    exit;
}

/* ---------------- the overview page ---------------- */

$previewLines = [];
if ($zone !== null) {
    foreach ($rows as $row) {
        foreach (record_zone_lines($recordsByDomain[(int)$row['id']] ?? [], (string)$row['name']) as $line) {
            $previewLines[] = $line;
        }
        if (count($previewLines) >= 8) {
            break;
        }
    }
}

page_header('Export');
?>
<p>
Every zone is exported separately, because they are separate DNS zones. Only names whose status is
<strong>ACTIVE</strong> are included, so an export can be published without leaking applicant data.
</p>

<?php if ($zones === []): ?>
<div class="msg error">No zone is configured yet. <a href="zones.php">Add one</a> first.</div>
<?php else: ?>

<h2>Zone</h2>
<p class="small">
<?php
$zoneLinks = [];
foreach ($zones as $zoneOption) {
    $label = (string)$zoneOption['name'];
    $zoneLinks[] = $zone !== null && strcasecmp((string)$zoneOption['name'], (string)$zone['name']) === 0
        ? '<strong>' . h($label) . '</strong>'
        : '<a href="?' . h(http_build_query(['zone' => $label])) . '">' . h($label) . '</a>';
}
echo implode(' &middot; ', $zoneLinks);
?>
</p>

<h2>Downloads for <?= h($zoneName !== '' ? $zoneName : '-') ?></h2>
<table>
  <tr><th>Format</th><th>Contents</th><th></th></tr>
  <tr>
    <td>Zone data</td>
    <td>BIND style <code>A</code>, <code>AAAA</code>, <code>CNAME</code> and <code>TXT</code> records for
        every active name, sub-names included</td>
    <td><a href="?<?= h(http_build_query(['zone' => $zoneName, 'format' => 'zone'])) ?>">download</a></td>
  </tr>
  <tr>
    <td>CSV</td>
    <td>every active name with applicant, notes and its records in one column</td>
    <td><a href="?<?= h(http_build_query(['zone' => $zoneName, 'format' => 'csv'])) ?>">download</a></td>
  </tr>
  <tr>
    <td>JSON</td>
    <td>machine readable dump for a cron job that writes the records into the DNS server</td>
    <td><a href="?<?= h(http_build_query(['zone' => $zoneName, 'format' => 'json'])) ?>">download</a></td>
  </tr>
</table>

<p>
<?= number_format(count($rows)) ?> active name(s) in this zone. Pending, rejected, suspended and deleted
records are kept out.
</p>

<h2>Preview</h2>
<pre class="wrap code-block">$TTL <?= (int)default_ttl() . "\n" ?>$ORIGIN <?= h($zoneName) ?>.
<?php
if ($previewLines === []) {
    echo "; no active name with records yet\n";
} else {
    foreach ($previewLines as $line) {
        echo h($line) . "\n";
    }
    if (count($previewLines) >= 8) {
        echo "; ...\n";
    }
}
?>
</pre>

<h2>How the records reach the internet</h2>
<p>
The registry answers for <code><?= h($zoneName) ?></code> itself: each active name is published with the
records kept here. Download the zone data, paste it into the zone file where
<code><?= h($zoneName) ?></code> is hosted (the built-in DNS of aaPanel, Cloudflare, BIND, PowerDNS, ...)
and reload the zone.
</p>
<p class="hint">
Automation: take the JSON export and let a cron job write the records into your DNS server after every
approval. Delegation to the registrant's own name servers is not offered yet, because the zones are not
listed in the Public Suffix List.
</p>
<?php endif; ?>
<?php
page_footer();
