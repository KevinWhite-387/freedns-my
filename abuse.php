<?php
declare(strict_types=1);

/** freedns.my - report abuse on a name (open to everybody; no account needed). */

require __DIR__ . '/inc/bootstrap.php';

$errors = [];
$done   = false;

$kinds = ['phishing', 'spam', 'malware', 'trademark', 'illegal', 'other'];

$tableReady = false;
try {
    $tableReady = db_one("SHOW TABLES LIKE 'abuse\\_reports'") !== null;
} catch (Throwable $e) {
    $tableReady = false;
}

// Pre-fill the name when somebody arrives from a WHOIS lookup.
$given = strtolower(trim((string)($_GET['domain'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    csrf_verify();

    $domain   = strtolower(trim((string)($_POST['domain'] ?? '')));
    $reporter = trim((string)($_POST['reporter'] ?? ''));
    $kind     = (string)($_POST['kind'] ?? 'other');
    $message  = trim((string)($_POST['message'] ?? ''));

    if (!whois_valid_domain($domain)) {
        $errors[] = t('val.abuse_domain');
    }
    if (!valid_email_addr($reporter)) {
        $errors[] = t('val.email_invalid');
    }
    if (!in_array($kind, $kinds, true)) {
        $kind = 'other';
    }
    if (strlen($message) < 20) {
        $errors[] = t('val.abuse_short');
    }

    if ($errors === []) {
        db_query(
            'INSERT INTO abuse_reports (domain, reporter, kind, message, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, \'open\', NOW(), NOW())',
            [$domain, $reporter, $kind, $message]
        );
        $id    = (int)db()->lastInsertId();
        $done  = true;
        log_action('abuse_report', 'domain', null, $domain . ' (' . $kind . ') from ' . $reporter);

        $admin = trim((string)setting('notify_admin_email', ''));
        if ($admin !== '') {
            $previous = lang_switch('en');
            send_mail($admin, t('mail.abuse_subject', $domain), implode("\n", [
                t('mail.dear', $admin), '',
                t('mail.abuse_body', $domain, $kind, $reporter, $message), '',
                rtrim(app_base_url(), '/') . '/admin/abuse.php',
            ]));
            lang_switch($previous);
        }
    }
    $given = $domain;
}

page_header(t('abuse.heading'));
?>
<div class="msg info"><?= h(t('abuse.intro')) ?></div>

<?php if (!$tableReady): ?>
<div class="msg error"><?= h(t('abuse.no_table')) ?></div>
<?php elseif ($done): ?>
<div class="msg ok"><?= h(t('abuse.thanks')) ?></div>
<?php else: ?>
<?= error_list($errors) ?>
<form method="post" action="<?= h(url('abuse.php')) ?>">
<?= csrf_field() ?>
<label for="domain"><?= h(t('abuse.domain')) ?></label>
<input type="text" id="domain" name="domain" maxlength="190" value="<?= h($given) ?>" required>
<label for="reporter"><?= h(t('abuse.reporter')) ?></label>
<input type="text" id="reporter" name="reporter" maxlength="190" value="" required>
<p class="hint"><?= h(t('abuse.reporter_hint')) ?></p>
<label for="kind"><?= h(t('abuse.kind')) ?></label>
<select id="kind" name="kind">
  <?php foreach ($kinds as $kind): ?>
  <option value="<?= h($kind) ?>"><?= h(t('abuse.kind_' . $kind)) ?></option>
  <?php endforeach; ?>
</select>
<label for="message"><?= h(t('abuse.message')) ?></label>
<textarea id="message" name="message" rows="8" required></textarea>
<p class="hint"><?= h(t('abuse.message_hint')) ?></p>
<p><input type="submit" value="<?= h(t('abuse.submit')) ?>"></p>
</form>
<?php endif; ?>

<p class="hint"><?= h(t('abuse.policy')) ?></p>
<p>
<a href="<?= h(url('legal.php?doc=terms')) ?>"><?= h(t('legal.terms')) ?></a> &middot;
<a href="<?= h(url('legal.php?doc=privacy')) ?>"><?= h(t('legal.privacy')) ?></a>
</p>
<?php
page_footer();
