<?php
declare(strict_types=1);

/**
 * freedns.my - public give-away of names ("domain market", transfer mode).
 *
 * Three blocks on one page, because a holder needs to see all of it at once:
 *   1. list one of my names;
 *   2. the open listings of other holders - apply with a reason;
 *   3. the applications my own listings received - approve one (irreversible) or reject it.
 *
 * Only signed-in holders take part. Ownership is domains.registrant_email, so approving transfers
 * the name by rewriting that address; records stay attached because they hang on domain_id.
 */

require __DIR__ . '/inc/bootstrap.php';

$user = current_user();
if ($user === null) {
    $_SESSION['after_login'] = safe_relative_url((string)($_SERVER['REQUEST_URI'] ?? ''), '/');
    flash(t('account.login_required'), 'error');
    redirect(url('account/login.php'));
}

$zh    = function_exists('lang') && is_zh();
$error = '';
$ok    = '';

/** Tell somebody, if this build has a notifier. Never fatal if it does not. */
$tell = static function (string $email, string $subject, string $body) use ($zh): void {
    foreach (['notify_user', 'notify_email', 'mail_notify', 'send_notice'] as $fn) {
        if (function_exists($fn)) {
            try {
                $fn($email, $subject, $body);
            } catch (Throwable $e) {
                // a broken notifier must never break the transfer
            }

            return;
        }
    }
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'list') {
        [$good, $why] = mt_list(
            (string)($_POST['name'] ?? ''),
            (string)$user['email'],
            (int)$user['id'],
            (string)($_POST['title'] ?? ''),
            (string)($_POST['note'] ?? '')
        );
        if ($good) {
            $ok = $zh ? '已挂牌，其他持有人可以在下方看到并申请。' : 'Listed. Other holders can now apply.';
        } else {
            $error = $why;
        }
    }

    if ($action === 'cancel') {
        $ok = mt_cancel((int)($_POST['listing_id'] ?? 0), (string)$user['email'])
            ? ($zh ? '已撤下挂牌。' : 'Listing withdrawn.')
            : '';
        if ($ok === '') {
            $error = $zh ? '撤下失败（不是你的挂牌）。' : 'Could not withdraw that listing.';
        }
    }

    if ($action === 'apply') {
        $listingId = (int)($_POST['listing_id'] ?? 0);
        [$good, $why] = mt_apply($listingId, $user, trim((string)($_POST['reason'] ?? '')));
        if ($good) {
            $ok = $zh ? '申请已提交，等待原主审批。' : 'Application sent; the holder will decide.';
            $row = db_one('SELECT * FROM mt_listings WHERE id = ? LIMIT 1', [$listingId]);
            if ($row !== null) {
                $tell((string)$row['from_email'],
                    $zh ? '有人申请你挂牌的域名' : 'Somebody applied for your name',
                    $zh ? ((string)$user['username'] . ' 申请了 ' . (string)$row['domain_name'])
                        : ((string)$user['username'] . ' applied for ' . (string)$row['domain_name']));
            }
        } else {
            $error = $why;
        }
    }

    if ($action === 'approve') {
        // Two locks: the checkbox AND the explicit button. Both are checked here as well.
        if (empty($_POST['confirm'])) {
            $error = $zh ? '请先勾选「我知道此操作不可逆」。' : 'Tick the box confirming the transfer cannot be undone.';
        } else {
            [$good, $info] = mt_approve((int)($_POST['application_id'] ?? 0), (string)$user['email']);
            if ($good) {
                $ok = $zh
                    ? ('已转移：' . $info . ' 现在属于申请人账号。此操作已完成，无法撤销。')
                    : ('Transferred: ' . $info . ' now belongs to the applicant. This cannot be undone.');
                $app = db_one('SELECT email FROM mt_applications WHERE id = ? LIMIT 1', [(int)($_POST['application_id'] ?? 0)]);
                if ($app !== null) {
                    $tell((string)$app['email'],
                        $zh ? '你申请的域名已转移给你' : 'The name you applied for is now yours',
                        $zh ? ('域名 ' . $info . ' 已转移到的账号。' ) : ('The name ' . $info . ' has been transferred to your account.'));
                }
            } else {
                $error = $info;
            }
        }
    }

    if ($action === 'reject') {
        $ok = mt_reject((int)($_POST['application_id'] ?? 0), (string)$user['email'])
            ? ($zh ? '已拒绝该申请。' : 'Application rejected.') : '';
        if ($ok === '') {
            $error = $zh ? '拒绝失败。' : 'Could not reject that application.';
        }
    }
}

/* ---- data ------------------------------------------------------------- */
$ready    = mt_table_ready();
$myNames  = [];
$openList = [];
$incoming = [];
$myList   = [];

if ($ready) {
    $myNames = db_all(
        'SELECT name FROM domains WHERE registrant_email = ? AND status = ? ORDER BY name ASC LIMIT 200',
        [(string)$user['email'], 'active']
    );
    $openList = db_all(
        "SELECT l.*, (SELECT COUNT(*) FROM mt_applications a WHERE a.listing_id = l.id AND a.status = 'pending') AS applicants
           FROM mt_listings l
          WHERE l.status = 'open' AND l.from_email <> ?
          ORDER BY l.id DESC LIMIT 100",
        [strtolower((string)$user['email'])]
    );
    $myList = db_all(
        "SELECT l.*, (SELECT COUNT(*) FROM mt_applications a WHERE a.listing_id = l.id AND a.status = 'pending') AS applicants
           FROM mt_listings l
          WHERE l.from_email = ?
          ORDER BY l.id DESC LIMIT 100",
        [strtolower((string)$user['email'])]
    );
    if ($myList !== []) {
        $ids = array_map(static fn($r) => (int)$r['id'], $myList);
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $incoming = db_all(
            "SELECT a.*, l.domain_name FROM mt_applications a
               JOIN mt_listings l ON l.id = a.listing_id
              WHERE a.listing_id IN (" . $in . ")
              ORDER BY a.id DESC LIMIT 200",
            $ids
        );
    }
}

page_header($zh ? '域名赠与' : 'Give a name away');
?>
<h1><?= h($zh ? '域名赠与' : 'Give a name away') ?></h1>
<hr>

<?php if (!$ready): ?>
  <p class="msg error"><?= h($zh
      ? '数据表还没建：请先导入 upgrade-market-transfer.sql。'
      : 'The tables are missing: import upgrade-market-transfer.sql first.') ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>
<?php if ($ok !== ''): ?>
<p class="msg ok"><?= h($ok) ?></p>
<?php endif; ?>

<h2><?= h($zh ? '一、把我的域名挂牌' : '1. List one of my names') ?></h2>
<?php if ($myNames === []): ?>
  <p class="small"><?= h($zh ? '你名下没有已生效的域名。' : 'You hold no active name.') ?></p>
<?php else: ?>
<form method="post" action="<?= h(url('market-giveaway.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="list">
  <p>
    <select name="name">
      <?php foreach ($myNames as $r): ?>
        <option value="<?= h((string)$r['name']) ?>"><?= h((string)$r['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="title" maxlength="190" size="30" placeholder="<?= h($zh ? '一句话题目（可选）' : 'one-line title (optional)') ?>">
  </p>
  <p><textarea name="note" rows="3" cols="60" placeholder="<?= h($zh ? '说明一下（可选）：为什么送、有什么要求' : 'anything to say (optional)') ?>"></textarea></p>
  <p><input type="submit" value="<?= h($zh ? '挂牌' : 'List it') ?>"></p>
</form>
<?php endif; ?>

<hr>
<h2><?= h($zh ? '二、别人在送的域名' : '2. Names other holders are giving away') ?></h2>
<?php if ($openList === []): ?>
  <p class="small"><?= h($zh ? '现在没有公开的挂牌。' : 'No open listing right now.') ?></p>
<?php else: ?>
<table>
<thead><tr>
  <th><?= h($zh ? '域名' : 'Name') ?></th>
  <th><?= h($zh ? '说明' : 'Note') ?></th>
  <th><?= h($zh ? '申请数' : 'Applications') ?></th>
  <th><?= h($zh ? '申请（填理由）' : 'Apply (give a reason)') ?></th>
</tr></thead>
<tbody>
<?php foreach ($openList as $r): ?>
<tr>
  <td><code><?= h((string)$r['domain_name']) ?></code><br>
      <span class="small"><?= h((string)$r['title']) ?></span></td>
  <td class="small"><?= nl2br(h((string)$r['note'])) ?></td>
  <td><?= (int)$r['applicants'] ?></td>
  <td>
    <form method="post" action="<?= h(url('market-giveaway.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="apply">
      <input type="hidden" name="listing_id" value="<?= (int)$r['id'] ?>">
      <textarea name="reason" rows="2" cols="40" required placeholder="<?= h($zh ? '为什么想要它' : 'why you want it') ?>"></textarea><br>
      <input type="submit" value="<?= h($zh ? '提交申请' : 'Apply') ?>">
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<hr>
<h2><?= h($zh ? '三、我挂牌收到的申请' : '3. Applications to my listings') ?></h2>
<?php if ($incoming === []): ?>
  <p class="small"><?= h($zh ? '还没有人申请你的挂牌。' : 'Nobody applied to your listings yet.') ?></p>
<?php else: ?>
<table>
<thead><tr>
  <th><?= h($zh ? '域名' : 'Name') ?></th>
  <th><?= h($zh ? '申请人' : 'Applicant') ?></th>
  <th><?= h($zh ? '理由' : 'Reason') ?></th>
  <th><?= h($zh ? '状态' : 'State') ?></th>
  <th><?= h($zh ? '操作' : 'Action') ?></th>
</tr></thead>
<tbody>
<?php foreach ($incoming as $r): ?>
<tr>
  <td><code><?= h((string)$r['domain_name']) ?></code></td>
  <td class="small"><?= h((string)$r['username']) ?><br><?= h((string)$r['email']) ?></td>
  <td class="small"><?= nl2br(h((string)$r['reason'])) ?></td>
  <td><?= h((string)$r['status']) ?></td>
  <td>
  <?php if ((string)$r['status'] === 'pending'): ?>
    <form method="post" action="<?= h(url('market-giveaway.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="application_id" value="<?= (int)$r['id'] ?>">
      <p class="msg error" style="margin:.3em 0">
        <strong><?= h($zh ? '警告：转移不可逆。' : 'Warning: a transfer cannot be undone.') ?></strong><br>
        <?= h($zh ? '域名会立刻移到对方账号，你和你的记录都不再属于它。' : 'The name moves to that account immediately.') ?>
      </p>
      <label><input type="checkbox" name="confirm" value="1" required>
        <?= h($zh ? '我知道此操作不可逆' : 'I understand this cannot be undone') ?></label><br>
      <input type="submit" value="<?= h($zh ? '确认转移' : 'Transfer now') ?>">
    </form>
    <form method="post" action="<?= h(url('market-giveaway.php')) ?>" style="margin-top:.4em">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="application_id" value="<?= (int)$r['id'] ?>">
      <input type="submit" value="<?= h($zh ? '拒绝' : 'Reject') ?>">
    </form>
  <?php else: ?>
    <span class="small">—</span>
  <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<?php if ($myList !== []): ?>
<hr>
<h2><?= h($zh ? '四、撤下我的挂牌' : '4. Withdraw a listing') ?></h2>
<form method="post" action="<?= h(url('market-giveaway.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="cancel">
  <select name="listing_id">
    <?php foreach ($myList as $r): ?>
      <option value="<?= (int)$r['id'] ?>"><?= h((string)$r['domain_name']) ?> (<?= h((string)$r['status']) ?>, <?= (int)$r['applicants'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <input type="submit" value="<?= h($zh ? '撤下' : 'Withdraw') ?>">
</form>
<?php endif; ?>

<?php page_footer(); ?>