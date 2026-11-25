<?php
declare(strict_types=1);

/**
 * freedns.my - the second step of a sign-in.
 *
 * Reached when the password was right but the account uses an authenticator app. Nothing else in the back
 * office or the console is readable until the code is accepted, because both guards (require_admin and
 * require_user) ask this page for the missing step.
 */

require __DIR__ . '/inc/bootstrap.php';

$kind = totp_pending_kind();
$who  = $kind === 'admin' ? totp_pending_admin() : ($kind === 'user' ? totp_pending_user() : null);

if ($kind === '' || $who === null) {
    totp_pending_clear();
    redirect(url('account/login.php'));
}

$secret = (string)($who['totp_secret'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!totp_verify($secret, (string)($_POST['code'] ?? ''))) {
        $errors[] = t('totp.ask_bad');
        log_action('totp_failed', $kind, (int)$who['id'], 'Wrong second factor');
    } else {
        log_action('totp_passed', $kind, (int)$who['id'], 'Second factor accepted');
        totp_pending_clear();

        if ($kind === 'admin') {
            $_SESSION['fd_totp_done_admin'] = (int)$who['id'];
            redirect(url('admin/index.php'));
        }
        $_SESSION['fd_totp_done_user'] = (int)$who['id'];
        redirect(url('account/index.php'));
    }
}

page_header(t('totp.ask'));
?>
<p><?= h(t('totp.ask_intro')) ?></p>
<?= error_list($errors) ?>

<form method="post" action="<?= h(url('totp.php')) ?>">
<?= csrf_field() ?>
<label for="code"><?= h(t('totp.code')) ?></label>
<input type="text" id="code" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code"
       value="" required>
<p><input type="submit" value="<?= h(t('totp.ask_submit')) ?>"></p>
</form>

<p><a href="<?= h(url('account/logout.php')) ?>"><?= h(t('account.logout')) ?></a></p>
<?php
page_footer();
