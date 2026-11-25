<?php
declare(strict_types=1);

/**
 * freedns.my - administration: list, filter and bulk-edit domains.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();

$statusOptions = status_options();
$status        = (string)($_GET['status'] ?? 'all');
if ($status !== 'all' && !in_array($status, $statusOptions, true)) {
    $status = 'all';
}
$q          = trim((string)($_GET['q'] ?? ''));
$zoneFilter = strtolower(trim((string)($_GET['zone'] ?? '')));
$page       = max(1, (int)($_GET['page'] ?? 1));

if ($zoneFilter !== '' && zone_by_name($zoneFilter) === null) {
    $zoneFilter = '';
}

$scope  = admin_scope_sql('zone');

$counts = ['all' => 0];
foreach ($statusOptions as $option) {
    $counts[$option] = 0;
}
$counts['all'] = (int)db_value('SELECT COUNT(*) FROM domains WHERE 1 = 1' . $scope[0], $scope[1], 0);
foreach (db_all('SELECT status, COUNT(*) AS n FROM domains WHERE 1 = 1' . $scope[0] . ' GROUP BY status',
    $scope[1]) as $row) {
    $counts[(string)$row['status']] = (int)$row['n'];
}

// A reviewer only lists the suffixes assigned to it (the fragment starts with " AND ").
$scopeFilter = admin_scope_sql('d.zone');

$where  = [];
$params = [];
if ($status !== 'all') {
    $where[]  = 'd.status = ?';
    $params[] = $status;
}
if ($zoneFilter !== '') {
    $where[]  = 'd.zone = ?';
    $params[] = $zoneFilter;
}
if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $where[]  = '(d.name LIKE ? OR d.registrant_email LIKE ? OR d.registrant_name LIKE ?
                  OR d.organization LIKE ? OR d.request_code LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($scopeFilter[0] !== '') {
    $where[] = substr($scopeFilter[0], 5);
    foreach ($scopeFilter[1] as $scopeParam) {
        $params[] = $scopeParam;
    }
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$total = (int)db_value('SELECT COUNT(*) FROM domains d' . $whereSql, $params, 0);
$pager = paginate($total, 50, $page);

$rows = db_all(
    'SELECT d.*,
            (SELECT COUNT(*) FROM records r WHERE r.domain_id = d.id) AS record_count,
            (SELECT COUNT(*) FROM change_requests c WHERE c.domain_id = d.id AND c.status = \'pending\') AS change_count
     FROM domains d' . $whereSql . "
     ORDER BY (d.status <> 'pending') ASC, d.created_at DESC
     LIMIT " . (int)$pager['per_page'] . ' OFFSET ' . (int)$pager['offset'],
    $params
);

$currentUrl = 'domains.php?' . http_build_query([
    'status' => $status,
    'q'      => $q,
    'zone'   => $zoneFilter,
    'page'   => $pager['page'],
]);

page_header('Domains');
?>
<p>
<?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?> match the current filter.
Checked rows can be processed together at the bottom of the table.
</p>

<p class="small">
<?php
$filters = [];
foreach (['all' => 'ALL'] as $key => $label) {
    $filters[] = $status === $key
        ? '<strong>' . h($label) . '</strong>'
        : '<a href="?' . h(http_build_query(['status' => $key, 'q' => $q, 'zone' => $zoneFilter])) . '">' . h($label) . '</a>';
}
foreach ($statusOptions as $option) {
    $label = status_text($option) . ' (' . (int)$counts[$option] . ')';
    $filters[] = $status === $option
        ? '<strong>' . h($label) . '</strong>'
        : '<a href="?' . h(http_build_query(['status' => $option, 'q' => $q, 'zone' => $zoneFilter])) . '">' . h($label) . '</a>';
}
echo implode(' &middot; ', $filters);
?>
</p>

<form method="get" action="domains.php">
<label for="q">Search (name, applicant, e-mail, organization, request code)</label>
<input type="text" id="q" name="q" value="<?= h($q ?: '') ?>" maxlength="120">
<input type="hidden" name="status" value="<?= h($status) ?>">

<?php if (count(zone_list()) > 1): ?>
<label for="zone">Zone</label>
<select id="zone" name="zone">
  <option value="">all zones</option>
  <?php foreach (zone_list() as $zoneOption): ?>
  <option value="<?= h((string)$zoneOption['name']) ?>"<?= $zoneFilter === strtolower((string)$zoneOption['name']) ? ' selected' : '' ?>>
    <?= h((string)$zoneOption['name']) ?>
  </option>
  <?php endforeach; ?>
</select>
<?php endif; ?>

<input type="submit" value="Search">
<?php if ($q !== '' || $zoneFilter !== ''): ?>
<a href="domains.php?status=<?= h(urlencode($status)) ?>">reset</a>
<?php endif; ?>
</form>

<?= page_links($pager, ['status' => $status, 'q' => $q, 'zone' => $zoneFilter]) ?>

<?php if ($rows === []): ?>
<p>No domain matches this filter.</p>
<?php else: ?>
<form method="post" action="decision.php">
<?= csrf_field() ?>
<input type="hidden" name="back" value="/admin/<?= h($currentUrl) ?>">

<table>
  <tr>
    <th></th><th>ID</th><th>Domain</th><th>Status</th><th>Applicant</th><th>Records</th>
    <th>Submitted</th><th>Decided</th><th></th>
  </tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>"></td>
    <td><?= (int)$row['id'] ?></td>
    <td><a href="edit.php?id=<?= (int)$row['id'] ?>"><?= h((string)$row['name']) ?></a>
        <?php if (!empty($row['reserved'])): ?><span class="err">reserved</span><?php endif; ?><br>
        <span class="small"><?= h((string)$row['request_code']) ?> &middot;
        <?= h((string)$row['zone']) ?></span></td>
    <td><?= h(status_text((string)$row['status'])) ?></td>
    <td><?= h((string)$row['registrant_name']) ?><br>
        <span class="small"><?= h((string)$row['registrant_email']) ?></span></td>
    <td><?= (int)$row['record_count'] ?><?php if ((int)$row['change_count'] > 0): ?>
        <br><span class="small"><a href="changes.php?status=pending">change pending</a></span>
        <?php endif; ?></td>
    <td><?= h(format_date((string)$row['created_at'])) ?></td>
    <td><?= h(format_date((string)($row['decided_at'] ?? ''))) ?><br>
        <span class="small"><?= h((string)($row['decided_by'] ?? '')) ?></span></td>
    <td class="actions"><a href="edit.php?id=<?= (int)$row['id'] ?>">open</a></td>
  </tr>
  <?php endforeach; ?>
</table>

<h3>Apply to the checked rows</h3>
<table>
  <tr>
    <th>Action</th>
    <td>
      <select name="action">
        <option value="approve">Approve (set ACTIVE and publish)</option>
        <option value="reject">Reject (set REJECTED)</option>
        <option value="suspend">Suspend</option>
        <option value="reactivate">Reactivate</option>
        <option value="pending">Put back to PENDING</option>
        <option value="delete">Delete (hide from the registry)</option>
      </select>
    </td>
  </tr>
  <tr>
    <th>Note for the applicant</th>
    <td>
      <input type="text" name="public_note" maxlength="500" placeholder="optional, shown on the status page">
      <p class="hint">When filled in, the note replaces the current note of every selected domain.
      Approving, rejecting or suspending also sends the decision by e-mail.</p>
    </td>
  </tr>
</table>
<p><input type="submit" value="Apply action"></p>
</form>
<?php endif; ?>

<?= page_links($pager, ['status' => $status, 'q' => $q, 'zone' => $zoneFilter]) ?>
<?php
page_footer();
