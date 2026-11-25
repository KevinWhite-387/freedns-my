<?php
declare(strict_types=1);

/**
 * freedns.my - support chat page (signed-in accounts and visitors who leave a message).
 *
 * The page refreshes itself with a meta refresh and needs no JavaScript. Messages cannot be deleted; the
 * holder can export them instead.
 */

require __DIR__ . '/inc/bootstrap.php';

fd_start_session();
$user   = current_user();
$errors = [];
$thread = null;

if (!chat_table_ready()) {
    page_header(t('chat.heading'));
    echo '<div class="msg error">' . h(t('chat.no_table')) . '</div>';
    page_footer();
    exit;
}

chat_sweep();

$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($action === 'guest') {
        $email = trim((string)($_POST['email'] ?? ''));
        $name  = trim((string)($_POST['name'] ?? ''));
        $first = trim((string)($_POST['message'] ?? ''));
        if (!valid_email_addr($email)) {
            $errors[] = t('val.email_invalid');
        } elseif ($name === '') {
            $errors[] = t('chat.need_name');
        } else {
            $thread = chat_thread_for_guest($email, $name, true);
            if ($thread === null) {
                $errors[] = t('chat.no_table');
            } else {
                $posted = chat_post($thread, 'guest', $name, $first, true);
                if ($posted['ok']) {
                    $_SESSION['fd_chat_email'] = strtolower($email);
                    flash(t('chat.sent'), 'ok');
                    redirect(url('chat.php'));
                }
                $errors[] = $posted['error'];
            }
        }
    }

    if ($action === 'send' && $user !== null) {
        $thread = chat_thread_for_user($user, true);
        if ($thread === null) {
            $errors[] = t('chat.no_table');
        } else {
            $posted = chat_post($thread, 'user', (string)$user['username'],
                (string)($_POST['message'] ?? ''), false);
            if ($posted['ok']) {
                redirect(url('chat.php'));
            }
            $errors[] = $posted['error'];
        }
    }
}

if ($user !== null) {
    $thread = chat_thread_for_user($user, true);
} elseif (($_SESSION['fd_chat_email'] ?? '') !== '') {
    $thread = chat_thread_for_guest((string)$_SESSION['fd_chat_email'], '', false);
}

if ($thread !== null) {
    chat_mark_seen($thread, false);
    // Who am I in this thread?
    $mine = $user !== null ? 'user' : 'guest';
} else {
    $mine = $user !== null ? 'user' : 'guest';
}

$messages = $thread === null ? [] : chat_messages((int)$thread['id']);

// Export before anything is printed.
if ($thread !== null && isset($_GET['export']) && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="chat-' . (int)$thread['id'] . '.txt"');
    echo chat_export($thread);
    exit;
}

// The page refreshes only when it is asked to. An automatic refresh used to wipe half-typed messages, so it
// is off by default; the settings row chat_auto_refresh = 1 brings it back for anybody who wants it.
if ($thread !== null && !headers_sent() && $_SERVER['REQUEST_METHOD'] !== 'POST'
    && setting_bool('chat_auto_refresh', false)) {
    header('Refresh: ' . max(5, (int)setting('chat_refresh_seconds', '8')));
}

page_header(t('chat.heading'));
?>
<p><?= h(t('chat.intro')) ?></p>
<p class="hint"><?= h(t('chat.refresh_manual_hint')) ?></p>

<?php if ($thread === null && $user === null): ?>
<?= error_list($errors) ?>
<h2><?= h(t('chat.leave_heading')) ?></h2>
<p><?= t('chat.leave_intro', '<a href="' . h(url('account/login.php')) . '">'
    . h(t('account.login_heading')) . '</a>') ?></p>
<form method="post" action="<?= h(url('chat.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="guest">
<label for="name"><?= h(t('chat.your_name')) ?></label>
<input type="text" id="name" name="name" maxlength="190" value="" required>
<label for="email"><?= h(t('chat.your_email')) ?></label>
<input type="text" id="email" name="email" maxlength="190" value="" required>
<p class="hint"><?= h(t('chat.email_hint')) ?></p>
<label for="message"><?= h(t('chat.your_message')) ?></label>
<textarea id="message" name="message" rows="5" maxlength="<?= (int)FD_CHAT_MAX_LENGTH ?>"></textarea>
<p><input type="submit" value="<?= h(t('chat.send')) ?>"></p>
</form>

<?php elseif ($thread === null): ?>
<div class="msg error"><?= h(t('chat.no_table')) ?></div>

<?php else: ?>
<?php if ((string)$thread['status'] === 'archived'): ?>
<div class="msg info"><?= h(t('chat.archived_note')) ?></div>
<?php endif; ?>

<?= error_list($errors) ?>

<h2><?= h(t('chat.thread', (string)(int)$thread['id'])) ?></h2>
<table>
  <tr><th><?= h(t('chat.state')) ?></th><td><?= h(chat_status_text((string)$thread['status'])) ?>
      <?= $thread['last_message_at'] === null ? '' : ' &middot; ' . h((string)$thread['last_message_at']) ?></td></tr>
  <tr><th><?= h(t('chat.addressed')) ?></th><td><?= h((string)$thread['email']) ?></td></tr>
  <tr><th><?= h(t('chat.keep')) ?></th><td><?= h(t('chat.keep_value')) ?></td></tr>
</table>

<?php if ($messages === []): ?>
<p><?= h(t('chat.no_messages')) ?></p>
<?php else: ?>
<div class="chat">
<?php foreach ($messages as $message): ?>
<div class="msg <?= (string)$message['author'] === 'staff' ? 'info' : '' ?>">
  <strong><?= h(chat_author_text((string)$message['author'], (string)($message['author_name'] ?? ''))) ?></strong>
  <span class="small"><?= h((string)$message['created_at']) ?></span>
  <div><?= nl2br(h((string)$message['body'])) ?></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<h3><?= h(t('chat.write')) ?></h3>
<form method="post" action="<?= h(url('chat.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="send">
<label for="new_message"><?= h(t('chat.your_message')) ?></label>
<textarea id="new_message" name="message" rows="4" maxlength="<?= (int)FD_CHAT_MAX_LENGTH ?>"></textarea>
<p><input type="submit" value="<?= h(t('chat.send')) ?>"></p>
</form>

<p class="hint"><?= h(t('chat.rate_note', (string)FD_CHAT_MIN_SECONDS, (string)FD_CHAT_MAX_LENGTH)) ?></p>
<p>
  <a href="<?= h(url('chat.php') . '?export=1') ?>"><?= h(t('chat.export')) ?></a>
  &middot; <a href="<?= h(url('chat.php')) ?>"><?= h(t('chat.refresh')) ?></a>
</p>
<?php endif; ?>

<p><a href="<?= h(url('index.php')) ?>"><?= h(t('nav.home')) ?></a>
<?php if ($user !== null): ?>
&middot; <a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a>
<?php endif; ?></p>
<?php
page_footer();
