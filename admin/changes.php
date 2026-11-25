<?php
declare(strict_types=1);

/**
 * freedns.my - administration: record changes proposed by the applicants.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
    $note   = trim((string)($_POST['admin_note'] ?? ''));

    $ids = [];
    if (isset($_POST['id'])) {
        $ids[] = (int)$_POST['id'];
    }
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $value) {
            $ids[] = (int)$value;
        }
    }
    $ids = array_values(array_unique(array_filter($ids, static fn($v) => $v > 0)));

    if (!in_array($action, ['approve', 'reject'], true)) {
        flash('Unknown action requested.', 'error');
        redirect('changes.php');
    }
    if ($ids === []) {
        flash('No change request was selected.', 'error');
        redirect('changes.php');
    }

    $done = 0;
    $outOfScope = [];
    foreach ($ids as $id) {
        $change = change_request_by_id($id);
        if ($change === null || (string)$change['status'] !== 'pending') {
            continue;
        }
        $domain   = domain_by_id((int)$change['domain_id']);
        $proposed = change_snapshot_decode((string)$change['snapshot']);

        // A reviewer may only decide changes that belong to the suffixes assigned to it.
        if ($domain === null || !admin_may_zone((string)$domain['zone'])) {
            $outOfScope[] = $domain === null ? ('#' . $id) : (string)$domain['name'];
            continue;
        }

        try {
            if ($action === 'approve') {
                apply_change_request($change, (string)$admin['username'], $note);
                $change = change_request_by_id($id) ?? $change;
                if ($domain !== null) {
                    notify_applicant_change($domain, $change, domain_records((int)$domain['id']), true);
                }
                log_action(
                    'change_approved',
                    'domain',
                    (int)$change['domain_id'],
                    'Change request ' . $id . ' approved'
                        . ($domain === null ? '' : ' for ' . (string)$domain['name'])
                );
            } else {
                reject_change_request($change, (string)$admin['username'], $note);
                $change = change_request_by_id($id) ?? $change;
                if ($domain !== null) {
                    notify_applicant_change($domain, $change, $proposed, false);
                }
                log_action(
                    'change_rejected',
                    'domain',
                    (int)$change['domain_id'],
                    'Change request ' . $id . ' rejected'
                        . ($domain === null ? '' : ' for ' . (string)$domain['name'])
                );
            }
            $done++;
        } catch (RuntimeException $e) {
            $errors[] = 'Change request ' . $id . ': ' . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = 'Change request ' . $id . ' could not be applied.';
        }
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            flash($error, 'error');
        }
    }
    if ($outOfScope !== []) {
        flash(
            'Not your suffixes, so nothing was done for: ' . implode(', ', array_unique($outOfScope))
            . '. Your account may only decide ' . implode(', ', admin_scope_zones()) . '.',
            'error'
        );
    }
    flash(
        $done === 0
            ? 'Nothing was changed.'
            : $done . ' change request(s) ' . ($action === 'approve' ? 'approved and published.' : 'rejected.'),
        $done === 0 ? 'error' : 'ok'
    );
    redirect('changes.php' . ($action === 'approve' ? '?status=approved' : '?status=rejected'));
}

$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}
$page = max(1, (int)($_GET['page'] ?? 1));

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$scopeChanges = admin_scope_sql('d.zone');
foreach (db_all(
    'SELECT c.status, COUNT(*) AS n FROM change_requests c JOIN domains d ON d.id = c.domain_id
     WHERE 1 = 1' . $scopeChanges[0] . ' GROUP BY c.status',
    $scopeChanges[1]
) as $row) {
    $counts[(string)$row['status']] = (int)$row['n'];
}
$totalAll = array_sum($counts);

$where  = $status === 'all' ? '' : ' WHERE c.status = ?';
$params = $status === 'all' ? [] : [$status];

// A reviewer only lists the changes of the suffixes assigned to it.
if ($scopeChanges[0] !== '') {
    $where    = ($status === 'all' ? ' WHERE 1 = 1' : $where) . $scopeChanges[0];
    $params   = array_merge($params, $scopeChanges[1]);
}

$total = (int)db_value('SELECT COUNT(*) FROM change_requests c JOIN domains d ON d.id = c.domain_id'
    . $where, $params, 0);
$pager = paginate($total, 50, $page);

$rows = db_all(
    'SELECT c.*, d.name, d.zone, d.registrant_name, d.registrant_email, d.status AS domain_status
     FROM change_requests c JOIN domains d ON d.id = c.domain_id' . $where . '
     ORDER BY c.submitted_at ASC
     LIMIT ' . (int)$pager['per_page'] . ' OFFSET ' . (int)$pager['offset'],
    $params
);

page_header('Record changes');
?>
<p>
Applicants propose record changes for names that are already active (change an address, add
<code>www.name</code>, add CNAME/TXT, remove a record). Nothing is published before it is approved here.
</p>

<p class="small">
<?php
$filterLinks = [];
foreach (['pending' => 'PENDING', 'approved' => 'APPROVED', 'rejected' => 'REJECTED', 'all' => 'ALL'] as $key => $label) {
    $text = $label . ' (' . ($key === 'all' ? $totalAll : (int)$counts[$key]) . ')';
    $filterLinks[] = $status === $key
        ? '<strong>' . h($text) . '</strong>'
        : '<a href="?' . h(http_build_query(['status' => $key])) . '">' . h($text) . '</a>';
}
echo implode(' &middot; ', $filterLinks);
?>
</p>

<?= error_list($errors) ?>
<?= page_links($pager, ['status' => $status]) ?>

<?php if ($rows === []): ?>
<p>No change request matches this filter.</p>
<?php else: ?>
<form method="post" action="changes.php">
<?= csrf_field() ?>
<table>
  <tr><th></th><th>Submitted</th><th>Domain</th><th>Applicant</th><th>Note</th><th>Records</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <?php $proposed = change_snapshot_decode((string)$row['snapshot']); ?>
  <tr>
    <td>
      <?php if ((string)$row['status'] === 'pending'): ?>
      <input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>">
      <?php else: ?>
      <?= (int)$row['id'] ?>
      <?php endif; ?>
    </td>
    <td><?= h((string)$row['submitted_at']) ?><br>
        <span class="small"><?= h(status_text((string)$row['status'])) ?></span></td>
    <td><a href="change.php?id=<?= (int)$row['id'] ?>"><?= h((string)$row['name']) ?></a><br>
        <span class="small"><?= h((string)$row['zone']) ?> &middot;
        <?= h(status_text((string)$row['domain_status'])) ?></span></td>
    <td><?= h((string)$row['registrant_name']) ?><br>
        <span class="small"><?= h((string)$row['registrant_email']) ?></span></td>
    <td class="small"><?= h(shorten((string)($row['applicant_note'] ?? ''), 80)) ?></td>
    <td class="small"><?= count($proposed) ?> record(s)</td>
    <td class="actions"><a href="change.php?id=<?= (int)$row['id'] ?>">review</a></td>
  </tr>
  <?php endforeach; ?>
</table>

<h3>Bulk decision for the checked rows</h3>
<table>
  <tr>
    <th>Action</th>
    <td>
      <select name="action">
        <option value="approve">Approve and publish</option>
        <option value="reject">Reject</option>
      </select>
    </td>
  </tr>
  <tr>
    <th>Note for the applicant</th>
    <td><input type="text" name="admin_note" maxlength="500" placeholder="optional"></td>
  </tr>
</table>
<p><input type="submit" value="Apply decision"></p>
</form>
<?php endif; ?>

<?= page_links($pager, ['status' => $status]) ?>
<?php
page_footer();
