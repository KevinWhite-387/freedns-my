<?php
declare(strict_types=1);

/**
 * freedns.my - back office: move the records of a name in or out as a zone file.
 *
 * Export writes the records of the chosen name as "name type value" lines. Import reads such lines back:
 * it shows what it understood, together with every validation error, and applies the whole set only when
 * nothing is wrong - the same write path (replace_records then publish_domain) the console uses, and the
 * change is written to the audit log.
 *
 * Owner only, because it replaces the complete record set of a name at once.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Bulk record import');
$errors = [];

$domains = db_all("SELECT id, name, zone, status FROM domains WHERE status IN ('active', 'pending')
                   ORDER BY name ASC LIMIT 500");

$domainId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$domain   = $domainId > 0 ? domain_by_id($domainId) : null;

/** Parses a zone file into records, returning the rows and the problems. */
$parse = static function (string $text, string $domainName): array {
    $rows   = [];
    $problems = [];
    $lineNo = 0;

    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $lineNo++;
        $line = trim((string)preg_replace('/\s*;.*$/', '', (string)$line));
        if ($line === '') {
            continue;
        }
        // name [ttl] [IN] type value... [proxied|direct] [ttl=NNNN]
        $parts = preg_split('/\s+/', $line) ?: [];
        if (count($parts) < 3) {
            $problems[] = 'line ' . $lineNo . ': too short ("' . $line . '")';
            continue;
        }
        $name  = array_shift($parts);
        $ttl   = null;
        $proxy = null;

        if ($parts !== [] && preg_match('/^\d+$/', (string)$parts[0]) === 1) {
            $ttl = (int)array_shift($parts);           // ttl directly after the name
        }
        if ($parts !== [] && strtoupper((string)$parts[0]) === 'IN') {
            array_shift($parts);
        }
        $type = strtoupper((string)array_shift($parts));

        // Trailing flags: "proxied"/"proxy" (orange cloud) or "direct"/"dns" (grey cloud), and ttl=NNNN.
        while ($parts !== []) {
            $last = strtolower((string)$parts[count($parts) - 1]);
            if (in_array($last, ['proxied', 'proxy'], true)) {
                $proxy = 1;
                array_pop($parts);
                continue;
            }
            if (in_array($last, ['direct', 'dns'], true)) {
                $proxy = 0;
                array_pop($parts);
                continue;
            }
            if (preg_match('/^ttl=(\d+)$/', $last, $match) === 1) {
                $ttl = (int)$match[1];
                array_pop($parts);
                continue;
            }
            break;
        }

        $value = trim(implode(' ', $parts));

        $sub   = normalize_record_sub($name, $domainName);
        $value = normalize_record_value($type, $value);

        $error = record_validation_error($sub, $type, $value, $domainName);
        if ($error !== '') {
            $problems[] = 'line ' . $lineNo . ': ' . $error;
            continue;
        }

        $row = ['name' => $sub, 'type' => $type, 'value' => $value];
        if ($ttl !== null) {
            if ($ttl < 60 || $ttl > 86400) {
                $problems[] = 'line ' . $lineNo . ': the TTL has to be between 60 and 86400 seconds.';
                continue;
            }
            $row['ttl'] = $ttl;
        }
        if ($proxy !== null) {
            if (!in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
                $problems[] = 'line ' . $lineNo . ': only A, AAAA and CNAME records can be proxied.';
                continue;
            }
            $row['proxied'] = $proxy;
        }
        $rows[] = $row;
    }

    $problem = validate_record_set($rows, $domainName);
    if (is_string($problem) && $problem !== '') {
        $problems[] = $problem;
    }

    return ['rows' => $rows, 'problems' => $problems];
};

$zoneText = (string)($_POST['zone'] ?? '');

/* ---- export ----------------------------------------------------------------------------------- */

if ($domain !== null && isset($_GET['export'])) {
    $lines = ['; ' . (string)$domain['name'] . '  (' . date('Y-m-d H:i:s') . ')',
        '; name  type  value  ttl  proxied|direct'];
    foreach (domain_records((int)$domain['id']) as $record) {
        $sub = normalize_record_sub((string)($record['name'] ?? ''), (string)$domain['name']);
        $lines[] = record_sub_display($sub) . "\t" . strtoupper((string)($record['type'] ?? '')) . "\t"
            . (string)($record['value'] ?? '') . "\t" . (int)($record['ttl'] ?? 0) . "\t"
            . (empty($record['proxied']) ? 'direct' : 'proxied');
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . (string)$domain['name'] . '.zone"');
    echo implode("\n", $lines) . "\n";
    exit;
}

/* ---- import ----------------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'import') {
    csrf_verify();

    if ($domain === null) {
        $errors[] = 'Choose a name first.';
    } else {
        $parsed = $parse($zoneText, (string)$domain['name']);
        if ($parsed['problems'] !== []) {
            $errors = $parsed['problems'];
        } elseif (isset($_POST['confirm'])) {
            replace_records((int)$domain['id'], $parsed['rows']);
            $published = publish_domain((int)$domain['id'], $domain);
            if (isset($published['ok']) && !$published['ok']) {
                $errors[] = 'Stored, but publishing failed: ' . (string)($published['error'] ?? 'unknown');
            } else {
                log_action('bulk_import', 'domain', (int)$domain['id'],
                    count($parsed['rows']) . ' record(s) imported by ' . (string)$admin['username']);
                flash(count($parsed['rows']) . ' record(s) imported and published.', 'ok');
                redirect('bulk.php?id=' . (int)$domain['id']);
            }
        }
    }
}

$preview = ($domain !== null && $zoneText !== '') ? $parse($zoneText, (string)$domain['name']) : null;

page_header('Bulk records');
?>
<p>
Move the records of a name in or out as a zone file. Export writes one line per record
(<code>name type value</code>); import shows what it understood and every problem before it changes
anything. Applying replaces the whole record set of that name at once.
</p>
<?= error_list($errors) ?>

<h2>1. Pick a name</h2>
<form method="get" action="bulk.php">
<label for="id">Name</label>
<select id="id" name="id">
  <option value="0">- choose -</option>
  <?php foreach ($domains as $row): ?>
  <option value="<?= (int)$row['id'] ?>"<?= (int)$row['id'] === $domainId ? ' selected' : '' ?>>
    <?= h((string)$row['name']) ?> (<?= h((string)$row['status']) ?>)</option>
  <?php endforeach; ?>
</select>
<p><input type="submit" value="Open"></p>
</form>

<?php if ($domain !== null): ?>
<h2>2. The records of <code><?= h((string)$domain['name']) ?></code></h2>
<table>
  <tr><th>Name</th><th>Type</th><th>Value</th><th>TTL</th><th>Cloud</th></tr>
  <?php foreach (domain_records((int)$domain['id']) as $record): ?>
  <tr>
    <td><code><?= h(record_sub_display(normalize_record_sub((string)($record['name'] ?? ''),
        (string)$domain['name']))) ?></code></td>
    <td><?= h(strtoupper((string)($record['type'] ?? ''))) ?></td>
    <td class="small"><?= h((string)($record['value'] ?? '')) ?></td>
    <td><?= (int)($record['ttl'] ?? 0) ?></td>
    <td><?= empty($record['proxied']) ? 'direct' : 'proxied' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p><a href="bulk.php?id=<?= (int)$domain['id'] ?>&amp;export=1">Download these records as a zone file</a></p>

<h2>3. Import</h2>
<form method="post" action="bulk.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="import">
<input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
<label for="zone">Paste the records (one per line: name type value [ttl] [proxied|direct])</label>
<textarea id="zone" name="zone" rows="12" spellcheck="false"><?= h($zoneText) ?></textarea>
<p class="hint">
<code>@</code> means the name itself; <code>www</code>, <code>*</code> or <code>mail</code> are sub-names;
<code>IN</code> and a TTL right after the name are accepted (<code>www 300 A 1.2.3.4</code>);
<code>ttl=300</code> and <code>proxied</code> / <code>direct</code> may be added at the end, the latter only
for A, AAAA and CNAME; <code>;</code> starts a comment. The whole set replaces what is stored now.
</p>
<p><input type="submit" value="Check what it read"></p>
</form>

<?php if ($preview !== null): ?>
<h3>What the import understood</h3>
<?php if ($preview['problems'] !== []): ?>
<div class="msg error">Nothing was changed. Please fix:
  <ul><?php foreach ($preview['problems'] as $problem): ?><li><?= h($problem) ?></li><?php endforeach; ?></ul>
</div>
<?php else: ?>
<table>
  <tr><th>Name</th><th>Type</th><th>Value</th><th>TTL</th><th>Cloud</th></tr>
  <?php foreach ($preview['rows'] as $row): ?>
  <tr><td><code><?= h(record_sub_display((string)$row['name'])) ?></code></td>
      <td><?= h((string)$row['type']) ?></td><td class="small"><?= h((string)$row['value']) ?></td>
      <td><?= isset($row['ttl']) ? (int)$row['ttl'] : 'default' ?></td>
      <td><?= !isset($row['proxied']) ? 'default' : ((int)$row['proxied'] === 1 ? 'proxied' : 'direct') ?></td></tr>
  <?php endforeach; ?>
</table>
<form method="post" action="bulk.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="import">
<input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
<input type="hidden" name="confirm" value="1">
<textarea name="zone" rows="4" style="display:none"><?= h($zoneText) ?></textarea>
<p><input type="submit" value="Replace the records of this name with these <?= count($preview['rows']) ?> line(s)"></p>
</form>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php
page_footer();
