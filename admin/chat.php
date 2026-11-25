<?php
declare(strict_types=1);

/** freedns.my - back office: the support chat (owner only). */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('The support chat');
$errors = [];

if (!chat_table_ready()) {
    page_header('Chat');
    echo '<div class="msg error">The <code>chat_threads</code> table is missing; import '
        . '<code>upgrade-chat.sql</code> (the <a href="' . h(url('selftest.php')) . '">self test</a> '
        . 'prints the SQL).</div>';
    page_footer();
    exit;
}

chat_sweep();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $thread = chat_thread_by_id($id);

    if ($thread === null) {
        $errors[] = 'That conversation does not exist.';
    } elseif ($action === 'reply') {
        $posted = chat_post($thread, 'staff', (string)$admin['username'], (string)($_POST['message'] ?? ''), true);
        if ($posted['ok']) {
            flash('Sent. The other side was also notified by mail.', 'ok');
            redirect('chat.php?id=' . $id);
        }
        $errors[] = $posted['error'];
    } elseif ($action === 'archive' || $action === 'reopen') {
        chat_set_status($id, $action === 'archive' ? 'archived' : 'open');
        flash($action === 'archive' ? 'The conversation is archived.' : 'The conversation is open again.', 'ok');
        redirect('chat.php?id=' . $id);
    }
}

$id     = (int)($_GET['id'] ?? 0);
$thread = $id > 0 ? chat_thread_by_id($id) : null;

if ($thread !== null) {
    chat_mark_seen($thread, true);
    $thread   = chat_thread_by_id($id) ?? $thread;
    $messages = chat_messages((int)$thread['id']);
    // No automatic refresh: it interrupts whatever is being typed. The operator presses "Check for answers".
    if (!headers_sent() && $_SERVER['REQUEST_METHOD'] !== 'POST'
        && setting_bool('chat_auto_refresh', false)) {
        header('Refresh: ' . max(5, (int)setting('chat_refresh_seconds', '8')));
    }
    if (isset($_GET['export']) && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="chat-' . (int)$thread['id'] . '.txt"');
        echo chat_export($thread);
        exit;
    }
}

$rows = chat_threads_for_staff();

page_header('Chat');
?>
<p>
Conversations of the support chat. A signed-in account gets a private thread; a visitor can leave a message
with a name and an address. Answers are mailed out as well, so the other side is reached when it is not on
the page; messages cannot be deleted, only exported.
</p>
<?= error_list($errors) ?>

<?php if ($thread !== null): ?>
<h2><?= h((string)($thread['display_name'] ?? '')) ?> &middot; <?= h((string)$thread['email']) ?></h2>
<table>
  <tr><th>State</th><td><?= h(chat_status_text((string)$thread['status'])) ?>
      <?= $thread['last_message_at'] === null ? '' : ' &middot; last ' . h((string)$thread['last_message_at']) ?></td></tr>
  <tr><th>Account</th><td><?= $thread['user_id'] === null
      ? '<span class="small">guest (no console account)</span>'
      : '<a href="users.php?edit=' . (int)$thread['user_id'] . '">account #' . (int)$thread['user_id'] . '</a>' ?></td></tr>
  <tr><th>Language</th><td><?= h((string)$thread['language']) ?></td></tr>
</table>

<?php foreach ($messages as $message): ?>
<div class="msg <?= (string)$message['author'] === 'staff' ? 'info' : '' ?>">
  <strong><?= h(chat_author_text((string)$message['author'], (string)($message['author_name'] ?? ''))) ?></strong>
  <span class="small"><?= h((string)$message['created_at']) ?></span>
  <div><?= nl2br(h((string)$message['body'])) ?></div>
</div>
<?php endforeach; ?>
<?php if ($messages === []): ?><p>Nothing written yet.</p><?php endif; ?>

<h3>Answer</h3>
<form method="post" action="chat.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="reply">
<input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
<textarea name="message" rows="5" maxlength="<?= (int)FD_CHAT_MAX_LENGTH ?>"></textarea>
<p><input type="submit" value="Send the answer"></p>
</form>

<form class="inline" method="post" action="chat.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="<?= (string)$thread['status'] === 'archived' ? 'reopen' : 'archive' ?>">
  <input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
  <input type="submit" value="<?= (string)$thread['status'] === 'archived' ? 'Reopen' : 'Archive' ?>">
</form>
<a href="chat.php?id=<?= (int)$thread['id'] ?>&amp;export=1">export</a>
&middot; <a href="chat.php">all conversations</a>

<?php else: ?>
<h2>Conversations (<?= count($rows) ?>)</h2>
<?php if ($rows === []): ?>
<p>Nobody wrote yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Who</th><th>State</th><th>Last message</th><th>Unread</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)($row['display_name'] ?? '')) ?><br><span class="small"><?= h((string)$row['email']) ?></span></td>
    <td><?= h(chat_status_text((string)$row['status'])) ?></td>
    <td class="small"><?= h((string)($row['last_message_at'] ?? $row['created_at'])) ?>
        <?= (string)$row['last_from'] === 'staff' ? '<br><span class="small">you answered last</span>' : '' ?></td>
    <td><?= (int)$row['unread_staff'] > 0
        ? '<span class="err">' . (int)$row['unread_staff'] . '</span>' : '0' ?></td>
    <td class="actions"><a href="chat.php?id=<?= (int)$row['id'] ?>">open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>
<?php
page_footer();
