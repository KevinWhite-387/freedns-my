<?php
declare(strict_types=1);

/**
 * freedns.my - administration: audit log.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('The audit log');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ((string)($_POST['action'] ?? '') === 'purge' && is_owner()) {
        $days   = max(30, (int)($_POST['days'] ?? 180));
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
        $deleted = db_query('DELETE FROM audit_log WHERE created_at < ?', [$cutoff])->rowCount();
        log_action('audit_purged', 'audit', null, 'Removed ' . $deleted . ' entries older than ' . $days . ' days');
        flash('Removed ' . $deleted . ' log entries.', 'ok');
        redirect('logs.php');
    }
    flash('That action is not allowed.', 'error');
    redirect('logs.php');
}

$filter = trim((string)($_GET['action'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($filter !== '') {
    $where[]  = '(action LIKE ? OR admin_username LIKE ? OR detail LIKE ?)';
    $like     = '%' . addcslashes($filter, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$total = (int)db_value('SELECT COUNT(*) FROM audit_log' . $whereSql, $params, 0);
$pager = paginate($total, 100, $page);

$rows = db_all(
    'SELECT * FROM audit_log' . $whereSql . ' ORDER BY id DESC
     LIMIT ' . (int)$pager['per_page'] . ' OFFSET ' . (int)$pager['offset'],
    $params
);

page_header('Audit log');
?>
<p>
<?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?>.
Every sign-in, decision and settings change is recorded here together with the source address.
</p>

<form method="get" action="logs.php">
<label for="action">Filter (action, administrator or detail)</label>
<input type="text" id="action" name="action" value="<?= h($filter) ?>" maxlength="120">
<input type="submit" value="Filter">
<?php if ($filter !== ''): ?> <a href="logs.php">reset</a><?php endif; ?>
</form>

<?= page_links($pager, ['action' => $filter]) ?>

<?php if ($rows === []): ?>
<p>No log entry matches this filter.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Administrator</th><th>Action</th><th>Target</th><th>From</th><th>Detail</th></tr>
  <?php foreach ($rows as $entry): ?>
  <tr>
    <td><?= h((string)$entry['created_at']) ?></td>
    <td><?= h((string)$entry['admin_username']) ?></td>
    <td><?= h((string)$entry['action']) ?></td>
    <td><?= h(trim((string)$entry['target_type'] . ' ' . (string)($entry['target_id'] ?? ''))) ?></td>
    <td class="small"><?= h((string)($entry['ip'] ?? '')) ?></td>
    <td class="small"><?= h(shorten((string)($entry['detail'] ?? ''), 160)) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?= page_links($pager, ['action' => $filter]) ?>

<?php if (is_owner()): ?>
<h2>Housekeeping</h2>
<form method="post" action="logs.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="purge">
  <label for="days">Delete entries older than (days)</label>
  <input type="text" id="days" name="days" value="180" maxlength="5">
  <p><input type="submit" value="Delete old entries"></p>
</form>
<?php endif; ?>
<?php
page_footer();
