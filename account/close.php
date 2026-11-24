<?php
declare(strict_types=1);

/**
 * freedns.my - console: take my data with me, or close the account.
 *
 * Export gives one plain-text file with everything the account holds. Closing is final: the account data
 * is removed, its names are suspended and their published records are taken off the provider. The holder
 * has to type a phrase and give the current password (and, when it is switched on, a code from the
 * authenticator app), so a stolen session cannot do it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];

$tables = static function (string $name): bool {
    try {
        return db_one("SHOW TABLES LIKE '" . str_replace('_', '\\_', $name) . "'") !== null;
    } catch (Throwable $e) {
        return false;
    }
};

$opt = static function (string $name) use ($tables): array {
    return $tables($name) ? (array)db_one('SELECT 1') : [];
};

/* ------------------------------------------------------------------ *
 * Export                                                              *
 * ------------------------------------------------------------------ */

if (isset($_GET['export'])) {
    $lines = [];
    $lines[] = t('close.export_title', (string)setting('site_title', 'freedns.my'));
    $lines[] = t('close.export_when', date('Y-m-d H:i:s'));
    $lines[] = str_repeat('-', 60);
    $lines[] = '';
    $lines[] = t('close.export_account');
    $lines[] = '  username : ' . (string)$user['username'];
    $lines[] = '  email    : ' . (string)$user['email'];
    $lines[] = '  created  : ' . (string)($user['created_at'] ?? '');
    $quota   = quota_for_email((string)$user['email']);
    $lines[] = '  quota    : ' . ($quota['unlimited'] ? 'unlimited' : $quota['limit'])
        . ' (used ' . $quota['used'] . ', bonus ' . (int)($user['quota_bonus'] ?? 0) . ')';
    $lines[] = '';

    $lines[] = t('close.export_domains');
    foreach (user_domains($user) as $domain) {
        $lines[] = '  ' . (string)$domain['name'] . '  [' . (string)$domain['status'] . ']  zone '
            . (string)$domain['zone'] . '  ' . (string)($domain['created_at'] ?? '');
        foreach (domain_records((int)$domain['id']) as $record) {
            $lines[] = '      ' . record_sub_display(normalize_record_sub((string)($record['name'] ?? ''),
                (string)$domain['name'])) . '  ' . (string)($record['type'] ?? '') . '  '
                . (string)($record['value'] ?? '') . '  ttl ' . (string)($record['ttl'] ?? '');
        }
    }
    $lines[] = '';

    if ($tables('transfer_requests')) {
        $lines[] = t('close.export_tickets');
        foreach (db_all('SELECT * FROM transfer_requests WHERE requester_email = ? OR recipient_email = ?',
            [(string)$user['email'], (string)$user['email']]) as $row) {
            $lines[] = '  #' . (int)$row['id'] . '  ' . (string)$row['status'] . '  ' . (string)$row['created_at'];
        }
        $lines[] = '';
    }

    if ($tables('chat_threads')) {
        $lines[] = t('close.export_chat');
        $thread = chat_thread_for_user($user, false);
        if ($thread !== null) {
            foreach (chat_messages((int)$thread['id']) as $message) {
                $lines[] = '  [' . (string)$message['created_at'] . '] '
                    . chat_author_text((string)$message['author'], (string)($message['author_name'] ?? ''));
                $lines[] = '    ' . str_replace("\n", "\n    ", (string)$message['body']);
            }
        }
        $lines[] = '';
    }

    if ($tables('market_listings')) {
        $lines[] = t('close.export_market');
        foreach (db_all('SELECT * FROM market_listings WHERE user_id = ?', [(int)$user['id']]) as $row) {
            $lines[] = '  ' . (string)$row['domain'] . '  ' . (string)$row['status'] . '  '
                . (string)$row['price'] . ' ' . (string)$row['currency'];
        }
        $lines[] = '';
    }

    if ($tables('shop_promos')) {
        $lines[] = t('close.export_shop');
        foreach (shop_promos_of((int)$user['id']) as $row) {
            $lines[] = '  #' . (int)$row['id'] . '  ' . (string)$row['status'] . '  +' . (int)$row['bonus']
                . '  ' . (string)$row['url'];
        }
        $lines[] = '';
    }

    log_action('account_export', 'user', (int)$user['id'], 'Account bundle downloaded');
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="freedns-account-' . (int)$user['id'] . '.txt"');
    echo implode("\n", $lines) . "\n";
    exit;
}

/* ------------------------------------------------------------------ *
 * Closing the account                                                 *
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'close') {
    csrf_verify();

    $phrase = trim((string)($_POST['phrase'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $code     = (string)($_POST['code'] ?? '');

    if ($phrase !== 'DELETE MY ACCOUNT') {
        $errors[] = t('close.bad_phrase');
    }
    if (!password_verify($password, (string)$user['password_hash'])) {
        $errors[] = t('close.bad_password');
    }
    if (totp_columns_ready() && totp_user_on($user) && !totp_verify(totp_user_secret($user), $code)) {
        $errors[] = t('close.bad_code');
    }

    if ($errors === []) {
        $domains = user_domains($user);
        $stopped = 0;
        foreach ($domains as $domain) {
            // Take our records off the provider and keep the name suspended, so nothing stays published.
            $sync = publish_sync_domain((int)$domain['id'], (string)$domain['name'], (string)$domain['zone'], [], false);
            if (!empty($sync['ok'])) {
                publish_forget_mapping((int)$domain['id']);
                $stopped++;
            }
            db_query("UPDATE domains SET status = 'suspended', registrant_email = NULL, updated_at = NOW()
                      WHERE id = ?", [(int)$domain['id']]);
        }

        foreach (['market_listings' => 'user_id', 'shop_promos' => 'user_id', 'api_tokens' => 'user_id',
            'mail_routes' => 'user_id'] as $table => $column) {
            if ($tables($table)) {
                db_query('DELETE FROM ' . $table . ' WHERE ' . $column . ' = ?', [(int)$user['id']]);
            }
        }
        if ($tables('chat_threads')) {
            foreach (db_all('SELECT id FROM chat_threads WHERE user_id = ?', [(int)$user['id']]) as $row) {
                db_query('DELETE FROM chat_messages WHERE thread_id = ?', [(int)$row['id']]);
            }
            db_query('DELETE FROM chat_threads WHERE user_id = ?', [(int)$user['id']]);
        }

        log_action('account_closed', 'user', (int)$user['id'],
            (string)$user['username'] . ': ' . count($domains) . ' name(s) suspended, ' . $stopped . ' unpublished');

        db_query('DELETE FROM users WHERE id = ?', [(int)$user['id']]);
        fd_start_session();
        session_destroy();

        flash(t('close.done'), 'ok');
        redirect(url('index.php'));
    }
}

$mine = user_domains($user);
$on   = totp_columns_ready() && totp_user_on($user);

page_header(t('close.heading'));
?>
<p><?= h(t('close.intro')) ?></p>
<?= error_list($errors) ?>

<h2><?= h(t('close.export_heading')) ?></h2>
<p><?= h(t('close.export_hint')) ?></p>
<p><a href="<?= h(url('account/close.php?export=1')) ?>"><?= h(t('close.export_link')) ?></a></p>

<h2><?= h(t('close.close_heading')) ?></h2>
<div class="msg error"><?= h(t('close.warning', (string)count($mine))) ?></div>
<table>
  <tr><th><?= h(t('close.what')) ?></th><th><?= h(t('close.effect')) ?></th></tr>
  <tr><td><?= h(t('close.account')) ?></td><td><?= h(t('close.effect_account')) ?></td></tr>
  <tr><td><?= h(t('close.names')) ?></td><td><?= h(t('close.effect_names', (string)count($mine))) ?></td></tr>
  <tr><td><?= h(t('close.other')) ?></td><td><?= h(t('close.effect_other')) ?></td></tr>
</table>

<form method="post" action="<?= h(url('account/close.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="close">
<label for="password"><?= h(t('close.password')) ?></label>
<input type="password" id="password" name="password" value="" autocomplete="current-password">
<?php if ($on): ?>
<label for="code"><?= h(t('totp.code')) ?></label>
<input type="text" id="code" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" value="">
<?php endif; ?>
<label for="phrase"><?= h(t('close.phrase_label')) ?></label>
<input type="text" id="phrase" name="phrase" value="" placeholder="DELETE MY ACCOUNT">
<p class="hint"><?= h(t('close.phrase_hint')) ?></p>
<p><input type="submit" value="<?= h(t('close.submit')) ?>"></p>
</form>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();
