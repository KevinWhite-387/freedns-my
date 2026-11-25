<?php
declare(strict_types=1);

/**
 * freedns.my - mail settings and test message.
 *
 * The registry sends mail when a request is decided and when a record change is reviewed.
 * Two transports are supported: a plain socket SMTP client (recommended, no server mail
 * service needed) and the PHP mail() function.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Mail settings');

$errors  = [];
$notice  = '';
$log     = [];

$textKeys = ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_timeout',
    'mail_from', 'notify_admin_email', 'mail_transport'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save') {
        foreach ($textKeys as $key) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = trim((string)$_POST[$key]);
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                log_action('settings_updated', 'settings', null, 'mail: ' . $key);
            }
        }

        // The password is never echoed back: an empty field means "keep the stored one".
        $password = (string)($_POST['smtp_pass'] ?? '');
        if ($password !== '') {
            set_setting('smtp_pass', $password);
            log_action('settings_updated', 'settings', null, 'mail: smtp_pass');
        }

        $value = isset($_POST['notify_applicant']) ? '1' : '0';
        if ((string)setting('notify_applicant', '1') !== $value) {
            set_setting('notify_applicant', $value);
            log_action('settings_updated', 'settings', null, 'mail: notify_applicant');
        }
        $value = isset($_POST['smtp_insecure']) ? '1' : '0';
        if ((string)setting('smtp_insecure', '0') !== $value) {
            set_setting('smtp_insecure', $value);
            log_action('settings_updated', 'settings', null, 'mail: smtp_insecure');
        }

        flash('Mail settings saved.', 'ok');
        redirect('mail.php');
    }

    if ($action === 'test') {
        $recipient = trim((string)($_POST['test_to'] ?? ''));
        $settings  = mail_settings();

        if ($recipient === '') {
            $errors[] = 'Enter the address the test message should go to.';
        } elseif (!valid_email_addr($recipient)) {
            $errors[] = 'That is not a valid e-mail address.';
        } elseif (mail_transport() === 'smtp' && $settings['host'] === '') {
            $errors[] = 'No SMTP host is configured.';
        } else {
            // A test run leaves the saved settings alone but shows the SMTP conversation.
            $previous = mail_transport();
            $body = "This is a test message from the freedns.my registry.\n\n"
                . 'Transport : ' . $previous . "\n"
                . 'Server    : ' . ($settings['host'] === ''
                    ? 'PHP mail()'
                    : $settings['host'] . ':' . $settings['port'] . ' (' . $settings['secure'] . ')') . "\n"
                . 'Sender    : ' . mail_from_address() . "\n"
                . 'Recipient : ' . $recipient . "\n"
                . 'Sent at   : ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ")\n\n"
                . "If you can read this, the registry can send mail.\n";

            $sent = send_mail($recipient, '[' . (string)setting('site_title', 'freedns.my') . '] test message', $body);
            $log  = $GLOBALS['FD_SMTP_LOG'] ?? [];

            if ($sent) {
                $notice = 'The test message was accepted by the mail server for ' . $recipient
                    . '. Check the mailbox (and the spam folder) in a minute.';
                log_action('mail_test', 'mail', null, 'test message to ' . $recipient . ' via ' . $previous);
            } else {
                $errors[] = 'The test message failed: ' . (string)($GLOBALS['FD_MAIL_ERROR'] ?? 'unknown error');
            }
        }
    }
}

$settings = mail_settings();
$transport = mail_transport();

page_header('Mail');
?>
<p>
The registry mails the applicant when a request is decided and when a record change is reviewed,
and it can mail you about every new request or change. Sending needs a working transport:
either <strong>SMTP</strong> (recommended - enter the mailbox your provider gave you) or the
PHP <code>mail()</code> function, which needs a mail service on the server.
</p>

<?php if ($errors !== []): ?>
<div class="msg error">
  <strong>Please check the following:</strong>
  <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($notice !== ''): ?>
<div class="msg ok"><?= h($notice) ?></div>
<?php endif; ?>

<table>
  <tr><th>Transport in use</th><td><strong><?= h(strtoupper($transport)) ?></strong>
      <?= $transport === 'smtp'
          ? '(SMTP ' . h($settings['host'] . ':' . $settings['port']) . ')'
          : '(PHP mail(); configure SMTP below if no mail arrives)' ?></td></tr>
  <tr><th>Sender address</th><td><?= h(mail_from_address()) ?></td></tr>
  <tr><th>Applicant notifications</th><td><?= setting_bool('notify_applicant', true) ? 'on' : 'off' ?></td></tr>
</table>

<form method="post" action="mail.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<h2>Transport</h2>
<label for="mail_transport">How should mail be sent?</label>
<select id="mail_transport" name="mail_transport">
  <?php foreach ([
      'auto' => 'Automatic - SMTP when a server is set, PHP mail() otherwise',
      'smtp' => 'SMTP only',
      'mail' => 'PHP mail() only',
  ] as $value => $label): ?>
  <option value="<?= h($value) ?>"<?= $settings['transport'] === $value ? ' selected' : '' ?>>
    <?= h($label) ?>
  </option>
  <?php endforeach; ?>
</select>

<h2>SMTP server of your mail provider</h2>
<label for="smtp_host">Server (host name)</label>
<input type="text" id="smtp_host" name="smtp_host" maxlength="190"
       value="<?= h($settings['host']) ?>" placeholder="smtp.exmail.qq.com">
<p class="hint">
Examples of enterprise mailboxes: <code>smtp.exmail.qq.com</code> (Tencent Exmail),
<code>smtp.qiye.aliyun.com</code> (Alibaba Mail), <code>smtphz.qiye.163.com</code> (NetEase enterprise).
Ask your provider for the exact host, port and encryption.
</p>

<label for="smtp_port">Port</label>
<input type="text" id="smtp_port" name="smtp_port" maxlength="5" value="<?= (int)$settings['port'] ?>">

<label for="smtp_secure">Encryption</label>
<select id="smtp_secure" name="smtp_secure">
  <option value="ssl"<?= $settings['secure'] === 'ssl' ? ' selected' : '' ?>>SSL / implicit TLS (usually port 465)</option>
  <option value="tls"<?= $settings['secure'] === 'tls' ? ' selected' : '' ?>>STARTTLS (usually port 587)</option>
  <option value="none"<?= $settings['secure'] === 'none' ? ' selected' : '' ?>>none (port 25, not recommended)</option>
</select>

<label for="smtp_user">Mailbox (user name)</label>
<input type="text" id="smtp_user" name="smtp_user" maxlength="190"
       value="<?= h($settings['user']) ?>" placeholder="hostmaster@freedns.my">
<p class="hint">Usually the complete e-mail address.</p>

<label for="smtp_pass">Password / authorisation code</label>
<input type="password" id="smtp_pass" name="smtp_pass" maxlength="190" value="" autocomplete="new-password">
<p class="hint">
<?= $settings['pass'] === ''
    ? 'No password is stored yet.'
    : 'A password is stored. Leave this field empty to keep it, or type a new one to replace it.' ?>
Most providers require a separate "client password" or "authorisation code" instead of the web password.
</p>

<label for="smtp_timeout">Timeout (seconds)</label>
<input type="text" id="smtp_timeout" name="smtp_timeout" maxlength="2" value="<?= (int)$settings['timeout'] ?>">

<p>
  <input type="checkbox" id="smtp_insecure" name="smtp_insecure" value="1"<?= $settings['insecure'] ? ' checked' : '' ?>>
  <label class="inline" for="smtp_insecure">Skip TLS certificate verification</label>
</p>
<p class="hint">
Leave this off. Switch it on only when the server reports a certificate error although the host name
is correct (an outdated CA bundle on the server), and switch it back off when the mail works.
</p>

<h2>Addresses and notifications</h2>
<label for="mail_from">Sender address (From)</label>
<input type="text" id="mail_from" name="mail_from" maxlength="190"
       value="<?= h((string)setting('mail_from', '')) ?>" placeholder="<?= h(mail_from_address()) ?>">
<p class="hint">
Leave empty to use the SMTP mailbox, or a <code>no-reply@</code> address of the first zone. Many
providers only accept a sender of the authenticated mailbox (or an alias of it).
</p>

<label for="notify_admin_email">Notify this address about new requests and changes</label>
<input type="text" id="notify_admin_email" name="notify_admin_email" maxlength="190"
       value="<?= h((string)setting('notify_admin_email', '')) ?>">
<p class="hint">Leave empty to switch those notifications off.</p>

<p>
  <input type="checkbox" id="notify_applicant" name="notify_applicant" value="1"<?= setting_bool('notify_applicant', true) ? ' checked' : '' ?>>
  <label class="inline" for="notify_applicant">E-mail the applicant when a request is decided or a
  record change is reviewed</label>
</p>

<p><input type="submit" value="Save mail settings"></p>
</form>

<hr>
<h2>Send a test message</h2>
<p>Sends one message with the settings above and shows the conversation with the mail server.</p>
<form method="post" action="mail.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="test">
<label for="test_to">Send the test to</label>
<input type="text" id="test_to" name="test_to" maxlength="190"
       value="<?= h((string)setting('notify_admin_email', '') !== ''
           ? (string)setting('notify_admin_email', '')
           : (string)$admin['email']) ?>">
<p><input type="submit" value="Send test message"></p>
</form>

<?php if ($log !== []): ?>
<h3>Conversation with the mail server</h3>
<pre class="wrap code-block"><?= h(implode("\n", $log)) ?></pre>
<?php endif; ?>

<?php if (!empty($GLOBALS['FD_MAIL_ERROR'])): ?>
<h3>Last error</h3>
<pre class="wrap code-block"><?= h((string)$GLOBALS['FD_MAIL_ERROR']) ?></pre>
<?php endif; ?>

<h2>Notes</h2>
<ul>
  <li>The SMTP password is stored in the <code>settings</code> table of the database, like the other
      registry settings. Keep database backups and the back office protected.</li>
  <li>Messages are <code>text/plain; charset=UTF-8</code>; Chinese subjects are MIME encoded, so they
      arrive readable in every mail client.</li>
  <li>A rejected message is written to the <a href="logs.php">audit log</a> with the reason from the
      mail server - useful when a provider refuses the sender address.</li>
  <li>If the host refuses connections although the settings look right, check whether the server blocks
      outgoing port 465/587 (aaPanel &rarr; Security &rarr; firewall, and your provider's firewall).</li>
  <li>Without SMTP you can also install a mail service (or <code>msmtp</code>) on the server and keep the
      <em>PHP mail()</em> transport.</li>
</ul>
<?php
page_footer();
