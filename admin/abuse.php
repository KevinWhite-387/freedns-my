<?php
declare(strict_types=1);

/** freedns.my - back office: abuse reports (owner only). */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Abuse reports');
$errors = [];

$ready = false;
try {
    $ready = db_one("SHOW TABLES LIKE 'abuse\\_reports'") !== null;
} catch (Throwable $e) {
    $ready = false;
}

if (!$ready) {
    page_header('Abuse reports');
    echo '<div class="msg error">The <code>abuse_reports</code> table is missing; import '
        . '<code>upgrade-abuse.sql</code> (the <a href="' . h(url('selftest.php')) . '">self test</a> can '
        . 'apply it for you).</div>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $id     = (int)($_POST['id'] ?? 0);
    $status = strtolower((string)($_POST['status'] ?? 'resolved'));
    $note   = trim((string)($_POST['note'] ?? ''));
    if (!in_array($status, ['open', 'resolved', 'rejected'], true)) {
        $status = 'resolved';
    }

    $row = db_one('SELECT * FROM abuse_reports WHERE id = ?', [$id]);
    if ($row === null) {
        $errors[] = 'That report does not exist.';
    } else {
        db_query('UPDATE abuse_reports SET status = ?, note = ?, decided_by = ?, updated_at = NOW() WHERE id = ?',
            [$status, $note === '' ? null : $note, (string)$admin['username'], $id]);
        log_action('abuse_' . $status, 'domain', null,
            (string)$row['domain'] . ': ' . $status . ($note === '' ? '' : ' - ' . $note));
        flash('Saved.', 'ok');
        redirect('abuse.php');
    }
}

$open   = db_all("SELECT * FROM abuse_reports WHERE status = 'open' ORDER BY id ASC LIMIT 200");
$closed = db_all("SELECT * FROM abuse_reports WHERE status <> 'open' ORDER BY id DESC LIMIT 50");

page_header('Abuse reports');
?>
<p>
Reports that arrived through <code>/abuse.php</code>. Keeping them here (with what was decided) is what lets
you show an upstream provider that a complaint was handled. Suspending or deleting the name itself happens
under <a href="domains.php">Domains</a>.
</p>
<?= error_list($errors) ?>

<h2>Open (<?= count($open) ?>)</h2>
<?php if ($open === []): ?>
<p>Nothing open - good.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>When</th><th>Name</th><th>Kind</th><th>Reporter</th><th>Report</th><th>Decision</th></tr>
  <?php foreach ($open as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td><code><?= h((string)$row['domain']) ?></code></td>
    <td><?= h(t('abuse.kind_' . (string)$row['kind'])) ?></td>
    <td class="small"><?= h((string)$row['reporter']) ?></td>
    <td class="small"><?= nl2br(h(shorten((string)$row['message'], 500))) ?></td>
    <td>
      <?php foreach (['resolved', 'rejected'] as $status): ?>
      <form class="inline" method="post" action="abuse.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="status" value="<?= h($status) ?>">
        <input type="text" name="note" maxlength="255" placeholder="<?= h($status === 'resolved' ? 'what was done' : 'why not') ?>">
        <input type="submit" value="<?= h($status === 'resolved' ? 'Resolved' : 'Reject') ?>">
      </form>
      <?php endforeach; ?>
      <a href="domains.php?q=<?= h(urlencode((string)$row['domain'])) ?>">find the name</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Closed</h2>
<?php if ($closed === []): ?>
<p>Nothing decided yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Name</th><th>Kind</th><th>Status</th><th>Note</th><th>By</th><th>When</th></tr>
  <?php foreach ($closed as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h((string)$row['domain']) ?></code></td>
    <td><?= h(t('abuse.kind_' . (string)$row['kind'])) ?></td>
    <td><?= h((string)$row['status']) ?></td>
    <td class="small"><?= h((string)($row['note'] ?? '')) ?></td>
    <td class="small"><?= h((string)($row['decided_by'] ?? '')) ?></td>
    <td class="small"><?= h((string)$row['updated_at']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();
