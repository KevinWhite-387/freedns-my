<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the developer queue.
 *
 * Two kinds of row land here (told apart by record_type):
 *   '*'      asking for the developer status - approving flips users.developer
 *   other    asking for one specific gated record - approving writes and publishes it
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

require_owner('Developer requests');

$zh     = function_exists('lang') && is_zh();
$admin  = current_admin();
$who    = is_array($admin) ? (string)($admin['username'] ?? 'admin') : 'admin';
$notice = '';
$error  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));

    if ($id > 0 && ($action === 'approve' || $action === 'reject')) {
        $res = $action === 'approve'
            ? developer_approve($id, $who, $note)
            : developer_reject($id, $who, $note);
        if (!empty($res['ok'])) {
            $notice = ($zh ? ($action === 'approve' ? '已批准 #' : '已驳回 #') : ($action === 'approve' ? 'Approved #' : 'Rejected #')) . $id;
        } else {
            $error = (string)($res['error'] ?? '');
        }
    }
}

$pending  = developer_requests_queue('pending', 200);
$approved = developer_requests_queue('approved', 20);
$rejected = developer_requests_queue('rejected', 20);

$devCount = 0;
try {
    $devCount = (int)db_value('SELECT COUNT(*) FROM users WHERE developer = 1', [], 0);
} catch (Throwable $e) {
    $devCount = 0;
}

page_header($zh ? '开发者申请' : 'Developer requests');
?>
<h1><?= h($zh ? '开发者申请' : 'Developer requests') ?></h1>
<hr>

<?php if ($notice !== ''): ?>
<p class="msg ok"><?= h($notice) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!developer_requests_ready()): ?>
<p class="msg error"><?= h($zh
    ? '数据库还缺表：请先导入 upgrade-record-types.sql。'
    : 'The developer_requests table is missing: import upgrade-record-types.sql first.') ?></p>
<?php endif; ?>

<h2><?= h($zh ? '概况' : 'Overview') ?></h2>
<table>
<tr><th><?= h($zh ? '待批' : 'Pending') ?></th><td><?= count($pending) ?></td></tr>
<tr><th><?= h($zh ? '现有开发者' : 'Developers') ?></th><td><?= $devCount ?></td></tr>
<tr><th><?= h($zh ? '公开类型' : 'Open types') ?></th><td><code><?= h(implode(' ', record_types_open())) ?></code></td></tr>
</table>

<h2><?= h($zh ? '待批队列' : 'Waiting') ?></h2>
<?php if ($pending === []): ?>
<p class="hint"><?= h($zh ? '队列是空的。' : 'Nothing waiting.') ?></p>
<?php else: ?>
<table>
<tr>
  <th>#</th><th><?= h($zh ? '申请人' : 'User') ?></th><th><?= h($zh ? '类型' : 'Type') ?></th>
  <th><?= h($zh ? '域名 / 值' : 'Name / value') ?></th><th><?= h($zh ? '理由' : 'Reason') ?></th>
  <th><?= h($zh ? '时间' : 'Sent') ?></th><th><?= h($zh ? '操作' : 'Decide') ?></th>
</tr>
<?php foreach ($pending as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><code><?= h((string)$r['username']) ?></code><br><span class="small"><?= h((string)$r['email']) ?></span></td>
  <td><code><?= h((string)$r['record_type'] === '*' ? ($zh ? '开发者身份' : 'DEVELOPER') : (string)$r['record_type']) ?></code></td>
  <td class="small">
    <?= h((string)$r['domain_name']) ?><br>
    <?php if ((string)$r['record_type'] !== '*'): ?>
      <code><?= h((string)$r['record_sub'] === '' ? '@' : (string)$r['record_sub']) ?></code>
      <code><?= h((string)$r['record_value']) ?></code>
    <?php endif; ?>
  </td>
  <td class="small"><?= h((string)($r['reason'] ?? '')) ?></td>
  <td class="small"><?= h((string)$r['created_at']) ?></td>
  <td>
    <form method="post" action="<?= h(url('admin/developers.php')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="text" name="note" maxlength="190" placeholder="<?= h($zh ? '备注（可选）' : 'note (optional)') ?>"
             style="width:150px">
      <p style="margin:4px 0 0 0">
        <button class="btn" type="submit" name="action" value="approve"><?= h($zh ? '批准' : 'Approve') ?></button>
        <button class="btn" type="submit" name="action" value="reject"><?= h($zh ? '驳回' : 'Reject') ?></button>
      </p>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h($zh ? '最近已批准' : 'Recently approved') ?></h2>
<?php if ($approved === []): ?>
<p class="hint">—</p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '申请人' : 'User') ?></th><th><?= h($zh ? '类型' : 'Type') ?></th>
    <th><?= h($zh ? '域名' : 'Name') ?></th><th><?= h($zh ? '决定' : 'Decided') ?></th><th><?= h($zh ? '备注' : 'Note') ?></th></tr>
<?php foreach ($approved as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= h((string)$r['username']) ?></td>
  <td><code><?= h((string)$r['record_type'] === '*' ? ($zh ? '开发者身份' : 'DEVELOPER') : (string)$r['record_type']) ?></code></td>
  <td><?= h((string)$r['domain_name']) ?></td>
  <td class="small"><?= h((string)($r['decided_at'] ?? '')) ?> · <?= h((string)($r['decided_by'] ?? '')) ?></td>
  <td class="small"><?= h((string)($r['admin_note'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h($zh ? '最近已驳回' : 'Recently rejected') ?></h2>
<?php if ($rejected === []): ?>
<p class="hint">—</p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '申请人' : 'User') ?></th><th><?= h($zh ? '类型' : 'Type') ?></th>
    <th><?= h($zh ? '域名' : 'Name') ?></th><th><?= h($zh ? '决定' : 'Decided') ?></th><th><?= h($zh ? '备注' : 'Note') ?></th></tr>
<?php foreach ($rejected as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= h((string)$r['username']) ?></td>
  <td><code><?= h((string)$r['record_type'] === '*' ? ($zh ? '开发者身份' : 'DEVELOPER') : (string)$r['record_type']) ?></code></td>
  <td><?= h((string)$r['domain_name']) ?></td>
  <td class="small"><?= h((string)($r['decided_at'] ?? '')) ?> · <?= h((string)($r['decided_by'] ?? '')) ?></td>
  <td class="small"><?= h((string)($r['admin_note'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '批准「开发者身份」只会把这个账号标记成开发者，不会写入任何记录。批准「具体记录」会立刻写入并发布。'
    : 'Approving a developer request only flags the account. Approving a record request writes and publishes it at once.') ?></p>

<?php page_footer(); ?>
