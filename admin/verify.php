<?php
declare(strict_types=1);

/**
 * freedns.my - back office: does what is published match what is stored?
 *
 * For every active name it reads what the provider really holds (publish_name_records) and compares it with
 * the records this registry stores. Differences are listed per name, and a name can be published again
 * from here; the whole set can be reconciled in one go (the same routine the cron job calls).
 *
 * This is the part of "monitoring" that needs no timer: run it from the back office whenever you like, or
 * let the cron job call the reconcile endpoint.
 *
 * Owner only.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Published records check');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'republish') {
        $domain = domain_by_id((int)($_POST['id'] ?? 0));
        if ($domain === null) {
            $errors[] = 'That name does not exist.';
        } else {
            $result = publish_domain((int)$domain['id'], $domain);
            if (isset($result['ok']) && !$result['ok']) {
                $errors[] = (string)$domain['name'] . ': ' . (string)($result['error'] ?? 'publishing failed');
            } else {
                log_action('republished', 'domain', (int)$domain['id'],
                    (string)$domain['name'] . ' published again by ' . (string)$admin['username']);
                flash('Published ' . (string)$domain['name'] . ' again.', 'ok');
                redirect('verify.php');
            }
        }
    }

    if ($action === 'reconcile') {
        $summary = publish_reconcile_all();
        log_action('reconcile', 'settings', null,
            'Manual reconcile: ' . (int)($summary['changed'] ?? 0) . ' change(s), '
            . (int)($summary['failed'] ?? 0) . ' failure(s)');
        flash('Reconcile finished: ' . (int)($summary['checked'] ?? 0) . ' checked, '
            . (int)($summary['changed'] ?? 0) . ' changed, ' . (int)($summary['failed'] ?? 0) . ' failed.', 'ok');
        redirect('verify.php');
    }
}

/** Normalises one record list into "sub|type|value" for comparison. */
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

$checkId = (int)($_GET['id'] ?? 0);
$rows    = [];

if ($checkId > 0) {
    $only = [domain_by_id($checkId)];
} else {
    $only = db_all("SELECT * FROM domains WHERE status = 'active' ORDER BY name ASC LIMIT 100");
}

foreach ($only as $domain) {
    if ($domain === null) {
        continue;
    }
    $name = (string)$domain['name'];
    $zone = (string)$domain['zone'];

    $stored = [];
    foreach (domain_records((int)$domain['id']) as $record) {
        $stored[] = [
            'sub'   => normalize_record_sub((string)($record['name'] ?? ''), $name),
            'type'  => (string)($record['type'] ?? ''),
            'value' => (string)($record['value'] ?? ''),
        ];
    }

    $line = [
        'id'       => (int)$domain['id'],
        'name'     => $name,
        'zone'     => $zone,
        'provider' => publish_provider_label(publish_provider($zone)),
        'stored'   => count($stored),
        'live'     => null,
        'missing'  => [],
        'extra'    => [],
        'error'    => '',
    ];

    $answer = publish_name_records($zone, $name);
    if (empty($answer['ok'])) {
        $line['error'] = (string)($answer['error'] ?? 'the service did not answer');
        $rows[]        = $line;
        continue;
    }

    $have = $keys($stored, $name);
    $live = $keys((array)($answer['records'] ?? []), $name);
    $line['live'] = count($live);

    foreach (array_keys($have) as $key) {
        if (!isset($live[$key])) {
            $line['missing'][] = $key;
        }
    }
    foreach (array_keys($live) as $key) {
        if (!isset($have[$key])) {
            $line['extra'][] = $key;
        }
    }

    $rows[] = $line;
}

$mismatched = 0;
$failed     = 0;
foreach ($rows as $row) {
    if ($row['error'] !== '') {
        $failed++;
    } elseif ($row['missing'] !== [] || $row['extra'] !== []) {
        $mismatched++;
    }
}

page_header('Published records');
?>
<p>
Compares what the DNS service really holds with the records stored here. A name that differs can be
published again on the spot; the whole set can be reconciled with one click (that is the same routine the
cron job calls, so nothing here needs a timer).
</p>
<p class="hint">
Reading every name costs one API call per name, so the list is limited to 100 active names per run.
</p>
<?= error_list($errors) ?>

<p>
<?= count($rows) ?> name(s) checked &middot;
<span class="<?= $mismatched > 0 ? 'err' : 'ok' ?>"><?= $mismatched ?> with a difference</span> &middot;
<span class="<?= $failed > 0 ? 'err' : 'ok' ?>"><?= $failed ?> not answerable</span>
</p>

<form class="inline" method="post" action="verify.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="reconcile">
  <input type="submit" value="Reconcile everything">
</form>
<a href="verify.php">check again</a>

<?php if ($checkId > 0 && $rows !== []): ?>
<h2><?= h($rows[0]['name']) ?></h2>
<table>
  <tr><th>Stored here</th><th>At the service</th><th>Difference</th></tr>
  <tr>
    <td><?= (int)$rows[0]['stored'] ?> record(s)</td>
    <td><?= $rows[0]['live'] === null ? '-' : (int)$rows[0]['live'] . ' record(s)' ?></td>
    <td class="small">
      <?php if ($rows[0]['error'] !== ''): ?>
      <span class="err"><?= h($rows[0]['error']) ?></span>
      <?php elseif ($rows[0]['missing'] === [] && $rows[0]['extra'] === []): ?>
      <span class="ok">identical</span>
      <?php else: ?>
      <?php foreach ($rows[0]['missing'] as $key): ?>
      <div>missing upstream: <code><?= h($key) ?></code></div>
      <?php endforeach; ?>
      <?php foreach ($rows[0]['extra'] as $key): ?>
      <div>only upstream: <code><?= h($key) ?></code></div>
      <?php endforeach; ?>
      <?php endif; ?>
    </td>
  </tr>
</table>
<form method="post" action="verify.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="republish">
  <input type="hidden" name="id" value="<?= (int)$rows[0]['id'] ?>">
  <p><input type="submit" value="Publish this name again"></p>
</form>
<?php endif; ?>

<?php if ($checkId === 0): ?>
<h2>Results</h2>
<?php if ($rows === []): ?>
<p>No active name to check.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Name</th><th>Service</th><th>Here</th><th>Upstream</th><th>Difference</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <?php $diff = $row['error'] !== '' || $row['missing'] !== [] || $row['extra'] !== []; ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h($row['name']) ?></code></td>
    <td><?= h($row['provider']) ?></td>
    <td><?= (int)$row['stored'] ?></td>
    <td><?= $row['live'] === null ? '-' : (int)$row['live'] ?></td>
    <td class="small">
      <?php if ($row['error'] !== ''): ?>
      <span class="err"><?= h($row['error']) ?></span>
      <?php elseif (!$diff): ?>
      <span class="ok">identical</span>
      <?php else: ?>
      <?= count($row['missing']) ?> missing, <?= count($row['extra']) ?> extra
      <?php endif; ?>
    </td>
    <td class="actions">
      <a href="verify.php?id=<?= (int)$row['id'] ?>">details</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>
<?php
page_footer();
