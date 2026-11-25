<?php
declare(strict_types=1);

/**
 * freedns.my - the health page (owner only).
 *
 * Answers the question the operator asks first when something looks wrong: is the database up to date,
 * is every suffix configured for publishing, does the provider answer, and how much work is waiting.
 * It is for owner accounts only, because it shows configuration and provider answers.
 */

require __DIR__ . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('The health page');

$checks = [];

$note = static function (string $label, bool $pass, string $hint = '') use (&$checks): void {
    $checks[] = ['label' => $label, 'pass' => $pass, 'hint' => $hint];
};

/* ---- the basics ------------------------------------------------------------------------------- */

$dbOk = true;
try {
    db_value('SELECT 1', [], 0);
} catch (Throwable $e) {
    $dbOk = false;
}
$note('Database answers', $dbOk, $dbOk ? '' : 'The connection failed.');

$tables = [];
foreach (db_all('SHOW TABLES') as $row) {
    $tables[] = (string)array_values($row)[0];
}
$wanted = ['zones', 'domains', 'records', 'change_requests', 'users', 'dns_records'];
$absent = array_values(array_diff($wanted, $tables));
$note('Core tables present', $absent === [], 'Missing: ' . implode(', ', $absent));

$newer = ['mail_routes', 'api_tokens', 'chat_threads', 'shop_promos', 'market_listings', 'transfer_requests'];
$absentNew = array_values(array_diff($newer, $tables));
$note('Optional tables (newer features)', $absentNew === [],
    'Missing: ' . implode(', ', $absentNew) . ' - those features are switched off, nothing else breaks.');

/* ---- publishing ------------------------------------------------------------------------------- */

$zones     = zone_list();
$unready   = [];
$reachable = 0;
$tested    = 0;
$providerLines = [];

foreach ($zones as $zone) {
    $name     = (string)$zone['name'];
    $provider = publish_provider($name);
    $ready    = $provider !== '' && publish_zone_ready($name);

    if (!$ready) {
        $unready[] = $name . ($provider === '' ? ' (no service chosen)' : ' (' . $provider . ' not configured)');
    }

    $lastSync = (string)db_value(
        'SELECT MAX(updated_at) FROM dns_records WHERE provider = ?', [$provider === '' ? 'cloudflare' : $provider], ''
    );
    $providerLines[] = [
        'name'     => $name,
        'provider' => publish_provider_label($provider),
        'ready'    => $ready,
        'last'     => $lastSync,
        'domains'  => (int)db_value('SELECT COUNT(*) FROM domains WHERE zone = ?', [$name], 0),
        'records'  => (int)db_value(
            'SELECT COUNT(*) FROM dns_records d JOIN domains x ON x.id = d.domain_id WHERE x.zone = ?',
            [$name],
            0
        ),
    ];

    if ($provider === 'cloudflare' && cf_zone_id($name) !== '' && cf_zone_token($name) !== '') {
        $tested++;
        $answer = cf_request('GET', '/zones/' . rawurlencode(cf_zone_id($name)) . '/dns_records?per_page=1',
            null, cf_zone_token($name), 12);
        if (!empty($answer['ok'])) {
            $reachable++;
        } else {
            $providerLines[count($providerLines) - 1]['error'] = (string)($answer['error'] ?? 'refused');
        }
    }
}

$note('Every suffix has a publishing service', $unready === [],
    'Not configured: ' . implode(', ', $unready));
if ($tested > 0) {
    $note('Cloudflare answers', $reachable === $tested,
        $reachable . ' of ' . $tested . ' suffix(es) answered.');
}

/* ---- what is waiting -------------------------------------------------------------------------- */

$waiting = [
    'domains'    => (int)db_value("SELECT COUNT(*) FROM domains WHERE status = 'pending'", [], 0),
    'changes'    => (int)db_value("SELECT COUNT(*) FROM change_requests WHERE status = 'pending'", [], 0),
];
foreach ([
    'transfers (tickets)' => "SELECT COUNT(*) FROM transfer_requests WHERE status IN ('open', 'approved')",
    'market offers'       => "SELECT COUNT(*) FROM market_listings WHERE status = 'pending'",
    'promotions'          => "SELECT COUNT(*) FROM shop_promos WHERE status = 'pending'",
    'chat messages'       => 'SELECT COALESCE(SUM(unread_staff), 0) FROM chat_threads',
] as $label => $sql) {
    if (in_array($label === 'transfers (tickets)' ? 'transfer_requests'
        : ($label === 'market offers' ? 'market_listings'
        : ($label === 'promotions' ? 'shop_promos' : 'chat_threads')), $tables, true)) {
        $waiting[$label] = (int)db_value($sql, [], 0);
    }
}

$cronKey = trim((string)setting('cf_cron_key', ''));
$note('The cron key is set', $cronKey !== '',
    'Empty: press "Generate a cron key" on the DNS page. It is the secret that lets the reconcile run and '
    . 'the publishing watchdog be started from cron - without it both refuse to run.');

/* ---- how much is registered ------------------------------------------------------------------- */

$counts = [];
foreach (['active', 'pending', 'suspended', 'rejected', 'deleted'] as $status) {
    $counts[$status] = (int)db_value('SELECT COUNT(*) FROM domains WHERE status = ?', [$status], 0);
}
$counts['users'] = (int)db_value('SELECT COUNT(*) FROM users', [], 0);
$counts['records'] = (int)db_value('SELECT COUNT(*) FROM records', [], 0);

$failed = 0;
foreach ($checks as $check) {
    if (!$check['pass']) {
        $failed++;
    }
}

page_header('Registry health');
?>
<p>
Everything the operator wants to know at a glance: the database, the publishing services, the provider
answers and the work that is waiting. This page is visible to owner accounts only.
</p>

<table>
  <tr><th>Check</th><th>Result</th><th>Note</th></tr>
  <?php foreach ($checks as $check): ?>
  <tr>
    <td><?= h($check['label']) ?></td>
    <td><?= $check['pass'] ? '<span class="ok">OK</span>' : '<span class="err">ATTENTION</span>' ?></td>
    <td class="small"><?= $check['pass'] ? '' : h($check['hint']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Publishing services</h2>
<?php if ($providerLines === []): ?>
<p>No suffix is defined yet.</p>
<?php else: ?>
<table>
  <tr><th>Suffix</th><th>Service</th><th>Ready</th><th>Names</th><th>Published records</th>
      <th>Last publish</th><th>Answer</th></tr>
  <?php foreach ($providerLines as $line): ?>
  <tr>
    <td><code><?= h($line['name']) ?></code></td>
    <td><?= h($line['provider']) ?></td>
    <td><?= $line['ready'] ? '<span class="ok">yes</span>' : '<span class="err">no</span>' ?></td>
    <td><?= (int)$line['domains'] ?></td>
    <td><?= (int)$line['records'] ?></td>
    <td class="small"><?= h($line['last'] === '' ? '-' : $line['last']) ?></td>
    <td class="small"><?= isset($line['error'])
        ? '<span class="err">' . h((string)$line['error']) . '</span>'
        : ($line['provider'] === 'DNSHE' ? 'not probed' : ($line['ready'] ? 'answers' : '-')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">
Only Cloudflare is probed live (a cheap read call). DNSHE is shown from what is configured and from the
last publish, because a probe there would spend the shared rate limit for nothing.
</p>
<?php endif; ?>

<h2>Publishing watchdog</h2>
<?php
$watchReady = false;
try {
    $watchReady = db_one("SHOW TABLES LIKE 'domain\\_checks'") !== null;
} catch (Throwable $e) {
    $watchReady = false;
}
?>
<?php if (!$watchReady): ?>
<p class="small">
Not in use yet: import <code>upgrade-alerts.sql</code> and call
<code>/alert-cron.php?key=&lt;cron key&gt;</code> from cron. The manual check is under
<a href="verify.php">Published records</a>.
</p>
<?php else: ?>
<?php
$watch = db_one('SELECT COUNT(*) AS total, SUM(ok = 0) AS bad, MAX(checked_at) AS last FROM domain_checks');
$watchTotal = (int)($watch['total'] ?? 0);
$watchBad   = (int)($watch['bad'] ?? 0);
$watchLast  = (string)($watch['last'] ?? '');
?>
<table>
  <tr><th>Names checked so far</th><td><?= $watchTotal ?></td></tr>
  <tr><th>With a problem</th><td><?= $watchBad > 0
      ? '<span class="err">' . $watchBad . '</span>' : '<span class="ok">0</span>' ?></td></tr>
  <tr><th>Last run</th><td><?= h($watchLast === '' ? 'never' : $watchLast) ?></td></tr>
</table>
<?php if ($watchBad > 0): ?>
<p class="small"><a href="verify.php">Open Published records to see the differences and repair them</a></p>
<?php endif; ?>
<p class="small">
The watchdog is started by cron: <code>/alert-cron.php?key=&lt;cron key&gt;&amp;limit=25</code>. It mails
only when a name has a new problem and never repairs anything on its own.
</p>
<?php endif; ?>

<h2>Waiting for a human</h2>
<table>
  <tr><th>Queue</th><th>Items</th></tr>
  <?php foreach ($waiting as $label => $value): ?>
  <tr><td><?= h((string)$label) ?></td><td><?= (int)$value ?></td></tr>
  <?php endforeach; ?>
</table>
<p class="small">
<a href="domains.php?status=pending">pending names</a> &middot;
<a href="changes.php">change requests</a> &middot;
<a href="tickets.php">transfer tickets</a> &middot;
<a href="market.php">market offers</a> &middot;
<a href="shop.php">promotions</a> &middot;
<a href="chat.php">chat</a>
</p>

<h2>Registry size</h2>
<table>
  <tr><th>What</th><th>Count</th></tr>
  <?php foreach ($counts as $label => $value): ?>
  <tr><td><?= h((string)$label) ?></td><td><?= (int)$value ?></td></tr>
  <?php endforeach; ?>
</table>

<?php if ($failed > 0): ?>
<div class="msg error"><?= (int)$failed ?> point(s) need attention.</div>
<?php else: ?>
<div class="msg ok">Everything looks healthy.</div>
<?php endif; ?>

<p><?= h(t('health.footer')) ?></p>
<?php
page_footer();
