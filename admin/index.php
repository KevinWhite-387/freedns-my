<?php
declare(strict_types=1);

/**
 * freedns.my - administration dashboard.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();

$counts = ['pending' => 0, 'active' => 0, 'rejected' => 0, 'suspended' => 0, 'deleted' => 0];

// A reviewer only ever sees the suffixes that are assigned to it; an owner sees everything.
$scopeDomains = admin_scope_sql('zone');
$scopePending = admin_scope_sql('d.zone');
$scopeChanges = admin_scope_sql('d.zone');

foreach (db_all('SELECT status, COUNT(*) AS n FROM domains WHERE 1 = 1' . $scopeDomains[0]
    . ' GROUP BY status', $scopeDomains[1]) as $row) {
    $counts[(string)$row['status']] = (int)$row['n'];
}
$total = array_sum($counts);

$pending = db_all(
    "SELECT d.*, (SELECT COUNT(*) FROM records r WHERE r.domain_id = d.id) AS record_count
     FROM domains d WHERE d.status = 'pending'" . $scopePending[0] . ' ORDER BY d.created_at ASC LIMIT 40',
    $scopePending[1]
);

$changes = db_all(
    "SELECT c.*, d.name, d.zone, d.registrant_email
     FROM change_requests c JOIN domains d ON d.id = c.domain_id
     WHERE c.status = 'pending'" . $scopeChanges[0] . ' ORDER BY c.submitted_at ASC LIMIT 40',
    $scopeChanges[1]
);

$pendingChanges = is_owner() ? count_pending_changes() : (int)db_value(
    'SELECT COUNT(*) FROM change_requests c JOIN domains d ON d.id = c.domain_id WHERE c.status = ?'
        . $scopeChanges[0],
    array_merge(['pending'], $scopeChanges[1]),
    0
);

$scopeZones = admin_scope_zones();
$zones = [];
foreach (zone_list() as $zoneRow) {
    if (in_array(strtolower((string)$zoneRow['name']), $scopeZones, true)) {
        $zones[] = $zoneRow;
    }
}
$zoneCounts = [];
// When settings.admin_stats_follow_home is on, the console prints the same numbers as
// the visitor-facing home page (display overrides included).
$fdZoneRows = setting_bool('admin_stats_follow_home', true) ? home_zone_rows() : (function () {

    $out = [];
    foreach (db_all("SELECT zone, COUNT(*) AS n FROM domains WHERE status = 'active' GROUP BY zone") as $zr) {
        $out[] = ['zone' => (string)$zr['zone'], 'n' => (int)$zr['n']];
    }
    return $out;
})();
foreach ($fdZoneRows as $row) {
    $zoneCounts[(string)$row['zone']] = (int)$row['n'];
}

$recentLog = is_owner() ? db_all('SELECT * FROM audit_log ORDER BY id DESC LIMIT 12') : [];

page_header('Dashboard');
?>
<p>
Signed in as <strong><?= h((string)$admin['username']) ?></strong><?= (int)$admin['is_owner'] === 1 ? ' (owner)' : '' ?>.
Two queues need attention: new domain requests and the record changes proposed by registrants.
</p>

<?php if (!is_owner()): ?>
<p>
  Your account is a <strong>reviewer</strong>:
  <?= $scopeZones === []
      ? '<span class="err">no suffix is assigned to you yet - ask the owner</span>'
      : 'it may decide requests and record changes for <code>' . h(implode(', ', $scopeZones)) . '</code>, read those names and suspend or delete them. Everything else is reserved for the owner.' ?>
</p>
<?php endif; ?>

<?php
$fdLimit = function_exists('user_register_daily_limit') ? user_register_daily_limit() : 0;
$fdUsed  = function_exists('user_registrations_today') ? user_registrations_today() : 0;
$fdFull  = $fdLimit > 0 && $fdUsed >= $fdLimit;
?>
<div class="msg <?= $fdFull ? 'error' : 'info' ?>">
  <p>
    <strong>Registrations today:</strong>
    <?= (int)$fdUsed ?><?= $fdLimit > 0 ? ' / ' . (int)$fdLimit : ' (no daily limit)' ?>
    <?= $fdFull
        ? ' &mdash; allowance used up: new accounts are refused until midnight.'
        : ' &mdash; the registration form is open.' ?>
  </p>
</div>

<h2>Registry overview</h2>
<table>
  <tr><th>Status</th><th>Domains</th><th></th></tr>
  <?php foreach ($counts as $status => $n): ?>
  <tr>
    <td><?= h(status_text((string)$status)) ?></td>
    <td><?= number_format($n) ?></td>
    <td><a href="<?= h('domains.php?status=' . urlencode((string)$status)) ?>">open list</a></td>
  </tr>
  <?php endforeach; ?>
  <tr><th>total</th><th><?= number_format($total) ?></th><td><a href="domains.php">all domains</a></td></tr>
  <tr><th>record changes waiting</th><th><?= number_format($pendingChanges) ?></th>
      <td><a href="changes.php">review queue</a></td></tr>
</table>

<h2>Zones</h2>
<table>
  <tr><th>Zone</th><th>Requests</th><th>Active names</th><th>Sub-names</th><th></th></tr>
  <?php foreach ($zones as $zone): ?>
  <tr>
    <td><code><?= h((string)$zone['name']) ?></code></td>
    <td><?= (int)$zone['is_open'] === 1 ? '<span class="ok">open</span>' : '<span class="err">closed</span>' ?></td>
    <td><?= number_format($zoneCounts[(string)$zone['name']] ?? 0) ?></td>
    <td><?= (int)$zone['allow_subdomains'] === 1 ? 'yes' : 'no' ?></td>
    <td>
      <?php if (is_owner()): ?>
      <a href="zones.php?edit=<?= (int)$zone['id'] ?>">edit</a> &middot;
      <a href="<?= h('export.php?zone=' . urlencode((string)$zone['name'])) ?>">export</a>
      <?php else: ?>
      <span class="small">view only</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if ($zones === []): ?>
  <tr><td colspan="5"><?= is_owner()
      ? 'No zone configured yet &mdash; <a href="zones.php">add one</a> before requests can be accepted.'
      : 'No suffix is assigned to your account yet &mdash; ask the owner.' ?></td></tr>
  <?php endif; ?>
</table>

<h2>Record changes waiting for review (<?= count($changes) ?><?= count($changes) === 40 ? '+' : '' ?>)</h2>
<?php if ($changes === []): ?>
<p>No record change is waiting.</p>
<?php else: ?>
<form method="post" action="changes.php">
<?= csrf_field() ?>
<table>
  <tr><th></th><th>Submitted</th><th>Domain</th><th>Applicant</th><th>Note</th><th></th></tr>
  <?php foreach ($changes as $change): ?>
  <tr>
    <td><input type="checkbox" name="ids[]" value="<?= (int)$change['id'] ?>"></td>
    <td><?= h(format_date((string)$change['submitted_at'])) ?></td>
    <td><a href="change.php?id=<?= (int)$change['id'] ?>"><?= h((string)$change['name']) ?></a><br>
        <span class="small"><?= h((string)$change['zone']) ?></span></td>
    <td><?= h((string)$change['registrant_email']) ?></td>
    <td class="small"><?= h(shorten((string)($change['applicant_note'] ?? ''), 100)) ?></td>
    <td class="actions">
      <a href="change.php?id=<?= (int)$change['id'] ?>">review</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p>
  <select name="action">
    <option value="approve">Approve and publish the checked changes</option>
    <option value="reject">Reject the checked changes</option>
  </select>
  <input type="text" name="admin_note" maxlength="500" placeholder="note for the applicant (optional)">
  <input type="submit" value="Apply">
</p>
</form>
<?php endif; ?>

<h2>Requests waiting for review (<?= count($pending) ?><?= count($pending) === 40 ? '+' : '' ?>)</h2>
<?php if ($pending === []): ?>
<p>Nothing is waiting. All requests have been decided.</p>
<?php else: ?>
<table>
  <tr><th>Submitted</th><th>Domain</th><th>Applicant</th><th>Records</th><th>Purpose</th><th>Decision</th></tr>
  <?php foreach ($pending as $domain): ?>
  <tr>
    <td><?= h(format_date((string)$domain['created_at'])) ?></td>
    <td><a href="<?= h('edit.php?id=' . (int)$domain['id']) ?>"><?= h((string)$domain['name']) ?></a><br>
        <span class="small"><?= h((string)$domain['request_code']) ?> &middot;
        <?= h((string)$domain['zone']) ?></span></td>
    <td><?= h((string)$domain['registrant_name']) ?><br>
        <span class="small"><?= h((string)$domain['registrant_email']) ?></span></td>
    <td><?= (int)$domain['record_count'] ?></td>
    <td class="small"><?= h(shorten((string)($domain['justification'] ?? ''), 120)) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="decision.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="back" value="/admin/index.php">
        <input type="submit" value="Approve">
      </form>
      <form class="inline" method="post" action="decision.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="back" value="/admin/index.php">
        <input type="submit" value="Reject">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="small">
The buttons apply immediately. To write a note for the applicant, or to correct the records,
open the domain first &mdash; the decision can also be taken on that page.
</p>
<?php endif; ?>

<h2>What to check before approving</h2>
<ol>
  <li>At least one publicly routable address is on file. Private, loopback, link-local, carrier-grade NAT
      and documentation ranges are refused by the forms automatically.</li>
  <li>The applicant plausibly controls the address: for a web site, the address usually answers on
      port 80 or 443, and the reverse DNS or the hosting provider should match the story.</li>
  <li>The requested name is not a trademark of somebody else and is not already used by an active domain.</li>
  <li>The name is not obviously abusive, and the description is plausible.</li>
  <li>Remember that the registry answers for these names itself: an approved name stops working as soon as
      the address changes, so a dynamic home connection is a poor fit.</li>
</ol>
<p class="small">
For a record change, compare the proposed records with the live ones: a CNAME must be the only record of
its name, sub-names are single labels, and the registered name keeps at least one A or AAAA record.
Name-server delegation is not offered yet because the zones are not listed in the Public Suffix List.
</p>

<?php if (is_owner()): ?>
<h2>Recent activity</h2>
<table>
  <tr><th>When</th><th>Administrator</th><th>Action</th><th>Target</th><th>Detail</th></tr>
  <?php foreach ($recentLog as $entry): ?>
  <tr>
    <td><?= h((string)$entry['created_at']) ?></td>
    <td><?= h((string)$entry['admin_username']) ?></td>
    <td><?= h((string)$entry['action']) ?></td>
    <td><?= h(trim((string)$entry['target_type'] . ' ' . (string)($entry['target_id'] ?? ''))) ?></td>
    <td class="small"><?= h(shorten((string)($entry['detail'] ?? ''), 100)) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="small"><a href="logs.php">Full audit log</a></p>
<?php endif; ?>
<?php
page_footer();
