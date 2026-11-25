<?php
declare(strict_types=1);

/**
 * freedns.my - back office: invite codes.
 *
 * Registration is by invitation only. A code is created here (usually after a request in the GitHub
 * issue tracker), mailed to that person by hand, and spent when the account is made. The note field is
 * the trail: put the GitHub handle or the address the code was sent to in it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Invite codes');

$zh = function_exists('admin_lang') ? admin_lang() === 'zh' : false;
$L  = static function (string $en, string $zhText) use ($zh): string {
    return $zh ? $zhText : $en;
};

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        $note  = trim((string)($_POST['note'] ?? ''));
        $count = max(1, min(200, (int)($_POST['count'] ?? 1)));
        $uses  = max(1, min(100, (int)($_POST['max_uses'] ?? 1)));

        $codes = invite_create($note, $uses, $count, (string)$admin['username']);
        if ($codes === []) {
            $errors[] = $L('Nothing was created: is the invites table imported?',
                '没有生成：邀请码数据表导入了吗？');
        } else {
            log_action('invites_created', 'settings', null, count($codes) . ' code(s), note: ' . $note);
            flash($L(count($codes) . ' code(s) created: ' . implode(', ', $codes),
                '已生成 ' . count($codes) . ' 个邀请码：' . implode('、', $codes)), 'ok');
            redirect('invites.php');
        }
    }

    if ($action === 'disable' && $id > 0) {
        db_query('UPDATE invites SET disabled = 1 WHERE id = ?', [$id]);
        log_action('invite_disabled', 'settings', null, 'id ' . $id);
        flash($L('Code withdrawn.', '邀请码已停用。'), 'ok');
        redirect('invites.php');
    }

    if ($action === 'enable' && $id > 0) {
        db_query('UPDATE invites SET disabled = 0 WHERE id = ?', [$id]);
        flash($L('Code enabled again.', '邀请码已恢复。'), 'ok');
        redirect('invites.php');
    }
}

/* ---- csv ---- */
if ((string)($_GET['csv'] ?? '') === '1') {
    $rows = invites_ready()
        ? db_all('SELECT code, note, max_uses, used_count, disabled, expires_at, created_at, last_used_at
                    FROM invites ORDER BY id DESC')
        : [];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="freedns-invites-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "code,note,max_uses,used_count,disabled,expires_at,created_at,last_used_at\n";
    foreach ($rows as $r) {
        echo '"' . str_replace('"', '""', (string)$r['code']) . '",'
            . '"' . str_replace('"', '""', (string)$r['note']) . '",'
            . (int)$r['max_uses'] . ',' . (int)$r['used_count'] . ','
            . (int)$r['disabled'] . ',"' . (string)$r['expires_at'] . '",'
            . '"' . (string)$r['created_at'] . '","' . (string)$r['last_used_at'] . "\"\n";
    }
    exit;
}

$ready   = invites_ready();
$rows    = $ready ? db_all('SELECT * FROM invites ORDER BY (used_count < max_uses) DESC, id DESC LIMIT 300') : [];
$used    = $ready ? (int)db_value('SELECT COUNT(*) FROM invites', [], 0) : 0;
$open    = 0;
foreach ($rows as $r) {
    if ((int)$r['used_count'] < (int)$r['max_uses'] && (int)$r['disabled'] === 0) {
        $open++;
    }
}

page_header($L('Invite codes', '邀请码'));
?>
<h1><?= h($L('Invite codes', '邀请码')) ?></h1>

<p class="small">
  <?= h($L(
      'Registration requires a code. Create one here, put the GitHub handle or the address in the note, '
      . 'mail the code by hand, then answer the GitHub thread telling the person to check that mailbox.',
      '注册需要邀请码。在这里生成，备注里写上 GitHub 用户名或收件邮箱，人工把码发到邮箱，'
      . '然后回 GitHub 那条申请告知对方查收邮件。'
  )) ?>
</p>

<?= error_list($errors) ?>

<?php if (!$ready): ?>

<div class="msg error"><?= h($L('Import upgrade-invites.sql first.', '请先导入 upgrade-invites.sql。')) ?></div>

<?php else: ?>

<h2><?= h($L('1. Create', '一、生成')) ?></h2>
<form method="post" action="<?= h(url('admin/invites.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <p>
    <label for="note"><?= h($L('Note (GitHub handle or e-mail)', '备注（GitHub 用户名或邮箱）')) ?></label>
    <input type="text" id="note" name="note" maxlength="180" placeholder="KevinWhite-387 / someone@example.com">
  </p>
  <p>
    <label for="count"><?= h($L('How many codes', '生成几个')) ?></label>
    <input type="text" id="count" name="count" value="1" maxlength="3">
    <label for="max_uses"><?= h($L('Uses per code', '每个码可用次数')) ?></label>
    <input type="text" id="max_uses" name="max_uses" value="1" maxlength="3">
  </p>
  <p><input type="submit" value="<?= h($L('Create', '生成')) ?>"></p>
</form>

<h2><?= h($L('2. Codes', '二、已生成的邀请码')) ?></h2>
<p class="small">
  <?= h($L($used . ' code(s) in total, ' . $open . ' of them still usable.',
      '共 ' . $used . ' 个，其中 ' . $open . ' 个仍可用。')) ?>
  &middot; <a href="<?= h(url('admin/invites.php?csv=1')) ?>"><?= h($L('Download CSV', '导出 CSV')) ?></a>
</p>

<?php if ($rows === []): ?>
<p class="small"><?= h($L('No codes yet.', '还没有邀请码。')) ?></p>
<?php else: ?>
<table>
  <tr>
    <th><?= h($L('Code', '邀请码')) ?></th>
    <th><?= h($L('Note', '备注')) ?></th>
    <th><?= h($L('Used', '使用')) ?></th>
    <th><?= h($L('Created', '创建时间')) ?></th>
    <th><?= h($L('Last used', '最近使用')) ?></th>
    <th class="actions"></th>
  </tr>
  <?php foreach ($rows as $r): ?>
  <?php $spent = (int)$r['used_count'] >= (int)$r['max_uses']; ?>
  <tr>
    <td><code><?= h((string)$r['code']) ?></code></td>
    <td class="small"><?= h((string)($r['note'] ?? '')) ?></td>
    <td><?= (int)$r['used_count'] ?>/<?= (int)$r['max_uses'] ?>
        <?= (int)$r['disabled'] === 1 ? ' <span class="err">' . h($L('withdrawn', '已停用')) . '</span>'
            : ($spent ? ' <span class="small">' . h($L('spent', '已用完')) . '</span>' : '') ?></td>
    <td class="small"><?= h((string)$r['created_at']) ?></td>
    <td class="small"><?= h((string)($r['last_used_at'] ?? '-')) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="<?= h(url('admin/invites.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="action" value="<?= (int)$r['disabled'] === 1 ? 'enable' : 'disable' ?>">
        <input type="submit" value="<?= h((int)$r['disabled'] === 1 ? $L('enable', '恢复') : $L('withdraw', '停用')) ?>">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h($L('3. Who used which code', '三、谁用了哪个码')) ?></h2>
<?php
$usedList = db_all("SELECT id, username, email, invite_used, created_at FROM users
                     WHERE invite_used IS NOT NULL AND invite_used <> ''
                     ORDER BY id DESC LIMIT 50");
?>
<?php if ($usedList === []): ?>
<p class="small"><?= h($L('Nothing has been used yet.', '还没有人使用邀请码。')) ?></p>
<?php else: ?>
<table>
  <tr><th>#</th><th><?= h($L('Account', '账号')) ?></th><th><?= h($L('E-mail', '邮箱')) ?></th>
      <th><?= h($L('Code', '邀请码')) ?></th><th><?= h($L('Registered', '注册时间')) ?></th></tr>
  <?php foreach ($usedList as $u): ?>
  <tr>
    <td><?= (int)$u['id'] ?></td>
    <td><code><?= h((string)$u['username']) ?></code></td>
    <td class="small"><?= h((string)$u['email']) ?></td>
    <td><code><?= h((string)$u['invite_used']) ?></code></td>
    <td class="small"><?= h((string)$u['created_at']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php endif; ?>
<?php
page_footer();