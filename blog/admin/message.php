<?php
declare(strict_types=1);

/**
 * freedns.my - blog admin: send a system message.
 *
 * blog_messages has no sender column on purpose - only the registry writes here, so this page is
 * the single door. It can reach one account or every active account; the latter is capped and the
 * page says how many it will reach before it does it.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

require_owner('Blog messages');

$zh    = function_exists('lang') && is_zh();
$admin = current_admin();
$who   = is_array($admin) ? (string)($admin['username'] ?? 'admin') : 'admin';
$notice = '';
$error  = '';

if (!blog_enabled()) {
    page_header('Send a message');
    echo '<h1>Send a message</h1><hr><p class="msg error">'
        . h($zh ? '博客还没准备好：请先导入 upgrade-blog.sql。' : 'The blog is not ready: import upgrade-blog.sql first.')
        . '</p>';
    page_footer();
    exit;
}

$maxBroadcast = 5000;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $target  = (string)($_POST['target'] ?? 'one');
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body    = trim((string)($_POST['body'] ?? ''));
    $kind    = trim((string)($_POST['kind'] ?? 'system'));
    $link    = trim((string)($_POST['link'] ?? ''));
    $needle  = trim((string)($_POST['needle'] ?? ''));

    if ($subject === '' || $body === '') {
        $error = $zh ? '标题和内容都要填。' : 'Subject and body are both required.';
    } elseif ($link !== '' && !preg_match('#^(https?://|/)#', $link)) {
        $error = $zh ? '链接必须以 / 或 http(s):// 开头。' : 'The link must start with / or http(s)://.';
    } else {
        $ids = [];
        if ($target === 'all') {
            $ids = array_map(static fn(array $r): int => (int)$r['id'],
                db_all("SELECT id FROM users WHERE status = 'active' ORDER BY id LIMIT " . $maxBroadcast));
        } else {
            /* One account, by id or by name or by e-mail. */
            $row = null;
            if ($needle !== '') {
                if (ctype_digit($needle)) {
                    $row = db_one('SELECT id FROM users WHERE id = ? LIMIT 1', [(int)$needle]);
                }
                if ($row === null) {
                    $row = db_one('SELECT id FROM users WHERE username_lower = ? LIMIT 1', [strtolower($needle)]);
                }
                if ($row === null) {
                    $row = db_one('SELECT id FROM users WHERE email_lower = ? LIMIT 1', [strtolower($needle)]);
                }
            }
            if ($row === null) {
                $error = $zh ? '找不到这个账号（可以填用户名、邮箱或数字 id）。' : 'No such account (username, e-mail or numeric id).';
            } else {
                $ids = [(int)$row['id']];
            }
        }

        if ($error === '' && $ids !== []) {
            $sent = 0;
            foreach ($ids as $uid) {
                blog_message_send($uid, $subject, $body, $kind === '' ? 'system' : $kind,
                    $link === '' ? null : $link, $who);
                $sent++;
            }
            log_action('blog_message_sent', 'settings', null, 'to ' . $sent . ' account(s)');
            $notice = ($zh ? '已发送给 ' : 'Sent to ') . $sent . ($zh ? ' 个账号 ✓' : ' account(s) ✓');
        }
    }
}

$activeUsers = 0;
try {
    $activeUsers = (int)db_value("SELECT COUNT(*) FROM users WHERE status = 'active'", [], 0);
} catch (Throwable $e) {
    $activeUsers = 0;
}

$recent = [];
try {
    $recent = db_all(
        'SELECT m.*, u.username FROM blog_messages m JOIN users u ON u.id = m.user_id
         ORDER BY m.id DESC LIMIT 25'
    );
} catch (Throwable $e) {
    $recent = [];
}

page_header($zh ? '发站内信' : 'Send a message');
?>
<p class="small">
<a href="<?= h(url('blog/admin/index.php')) ?>">← <?= h($zh ? '博客管理' : 'Blog admin') ?></a> &middot;
<a href="<?= h(url('blog/')) ?>"><?= h($zh ? '博客首页' : 'Blog') ?></a>
</p>
<h1><?= h($zh ? '发站内信' : 'Send a message') ?></h1>
<hr>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<p class="small"><?= h($zh
    ? '站内信只能由系统发出（没有"发件人"字段），用户之间不能互发。当前活跃账号：' 
    : 'Only the registry writes these (there is no sender field). Active accounts: ') ?><?= (int)$activeUsers ?></p>

<form method="post" action="<?= h(url('blog/admin/message.php')) ?>">
  <?= csrf_field() ?>

  <p><label for="target"><?= h($zh ? '收件人' : 'Recipients') ?></label><br>
  <select id="target" name="target">
    <option value="one"><?= h($zh ? '单个账号（下面填）' : 'one account (fill in below)') ?></option>
    <option value="all"><?= h($zh ? '全部活跃账号' : 'every active account') ?></option>
  </select>
  <span class="small"><?= h($zh ? '「全部」最多一次 ' : 'broadcast is capped at ') ?><?= $maxBroadcast ?><?= h($zh ? ' 个' : '') ?></span></p>

  <p><label for="needle"><?= h($zh ? '账号（用户名 / 邮箱 / 数字 id）' : 'Account (username / e-mail / id)') ?></label><br>
  <input type="text" id="needle" name="needle" maxlength="190" style="width:100%;max-width:380px" autocomplete="off"></p>

  <p><label for="subject"><?= h($zh ? '标题' : 'Subject') ?></label><br>
  <input type="text" id="subject" name="subject" maxlength="190" style="width:100%;max-width:640px" required></p>

  <p><label for="body"><?= h($zh ? '内容（纯文本，空行分段）' : 'Body (plain text)') ?></label><br>
  <textarea id="body" name="body" rows="8" style="width:100%;max-width:760px" required></textarea></p>

  <p><label for="kind"><?= h($zh ? '类型（只作分类用）' : 'Kind (a label only)') ?></label><br>
  <input type="text" id="kind" name="kind" maxlength="32" value="system" style="width:160px"></p>

  <p><label for="link"><?= h($zh ? '附带链接（可选，用户点信里的按钮会去这里）' : 'Link (optional)') ?></label><br>
  <input type="text" id="link" name="link" maxlength="255" style="width:100%;max-width:480px"
         placeholder="<?= h(url('blog/console.php')) ?>"></p>

  <p><input type="submit" value="<?= h($zh ? '发送' : 'Send') ?>"></p>
</form>

<h2><?= h($zh ? '最近发出的站内信' : 'Recently sent') ?></h2>
<?php if ($recent === []): ?>
<p class="hint">—</p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '收件人' : 'To') ?></th><th><?= h($zh ? '标题' : 'Subject') ?></th>
    <th><?= h($zh ? '已读' : 'Read') ?></th><th><?= h($zh ? '时间' : 'When') ?></th></tr>
<?php foreach ($recent as $m): ?>
<tr>
  <td><?= (int)$m['id'] ?></td>
  <td><code><?= h((string)$m['username']) ?></code></td>
  <td><?= h((string)$m['subject']) ?></td>
  <td><?= (string)($m['read_at'] ?? '') !== '' ? '<span class="ok">✓</span>' : '<span class="hint">—</span>' ?></td>
  <td class="small"><?= h((string)$m['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '用户会在博客控制台看到这些信；未读数量会显示在博客导航上。'
    : 'Users read these in the blog console; the unread count shows in the blog navigation.') ?></p>

<?php page_footer(); ?>
