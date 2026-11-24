<?php
declare(strict_types=1);

/**
 * freedns.my - the GitHub endpoint of the user console.
 *
 *   account/github.php            -> send the visitor to GitHub (sign in)
 *   account/github.php?action=link -> send a signed-in visitor to GitHub (link an account)
 *   account/github.php?code=...&state=... -> the callback GitHub sends the visitor back to
 *
 * Nothing here touches the back office: its accounts, its hidden entry and its pass phrase stay as they
 * are.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$action = strtolower(trim((string)($_GET['action'] ?? '')));

/*
 * GitHub answers on the registered callback URL, which carries no action parameter: a request that
 * brings a code, a state or an error IS the callback. Without this the "already signed in" branch below
 * would hand the visitor straight back to GitHub and the two would ping-pong for ever.
 */
$fromGitHub = isset($_GET['code']) || isset($_GET['state']) || isset($_GET['error'])
    || isset($_GET['error_description']);
if ($fromGitHub) {
    $action = 'callback';
} elseif ($action === '') {
    $action = 'start';
}
$errors = [];

if (!github_available()) {
    $errors[] = setting_bool('github_enabled', false) && !github_columns_ready()
        ? t('val.github_no_table')
        : t('val.github_off');
    page_header(t('account.github_heading'));
    echo error_list($errors);
    echo '<p><a href="' . h(url('account/login.php')) . '">' . h(t('account.login_heading')) . '</a> &middot; '
        . '<a href="' . h(url('account/register.php')) . '">' . h(t('account.register_heading')) . '</a></p>';
    page_footer();
    exit;
}

/* ---------------- step 1: hand the visitor over to GitHub ---------------- */

if ($action === 'start' || $action === 'link') {
    if ($action === 'link' && current_user() === null) {
        flash(t('val.github_signin_first'), 'error');
        redirect('login.php');
    }
    if ($action === 'start' && current_user() !== null) {
        // Already signed in: the visitor probably wants to link.
        redirect('github.php?action=link');
    }

    $returnTo = $action === 'link' ? url('account/profile.php') : url('account/index.php');
    redirect(github_start_url($returnTo));
}

/* ---------------- step 2: GitHub sends the visitor back ---------------- */

if ($action !== 'callback') {
    flash(t('val.github_bad_request'), 'error');
    redirect('login.php');
}

$state = (string)($_GET['state'] ?? '');
$code  = (string)($_GET['code'] ?? '');

if (!github_state_ok($state)) {
    log_action('github_failed', 'user', null, 'state check failed from ' . client_ip());
    flash(t('val.github_state'), 'error');
    redirect('login.php');
}
if ($code === '') {
    $denied = (string)($_GET['error_description'] ?? $_GET['error'] ?? '');
    log_action('github_failed', 'user', null, 'no code: ' . $denied);
    flash(t('val.github_cancelled'), 'error');
    redirect('login.php');
}

$token = github_exchange_code($code);
if (!$token['ok']) {
    log_action('github_failed', 'user', null, $token['error']);
    flash(t('val.github_token', $token['error']), 'error');
    redirect('login.php');
}

$profile = github_api('/user', $token['token']);
if (!$profile['ok']) {
    log_action('github_failed', 'user', null, $profile['error']);
    flash(t('val.github_token', $profile['error']), 'error');
    redirect('login.php');
}

$githubId = (string)($profile['data']['id'] ?? '');
$login    = (string)($profile['data']['login'] ?? '');
if ($githubId === '') {
    log_action('github_failed', 'user', null, 'the profile carried no account id');
    flash(t('val.github_token', 'no account id'), 'error');
    redirect('login.php');
}

$emails = github_api('/user/emails', $token['token']);
$email  = $emails['ok'] ? github_verified_email((array)$emails['data']) : '';

// The token has done its job; it is not stored anywhere.
unset($token);

$signedIn = current_user();

/* ---------------- linking an account that is already signed in ---------------- */

if ($signedIn !== null) {
    $back = github_return_target();
    if ((string)($signedIn['github_id'] ?? '') === $githubId) {
        flash(t('account.github_already_linked', $login), 'info');
        redirect($back);
    }
    if (user_by_github($githubId) !== null) {
        log_action('github_failed', 'user', (int)$signedIn['id'], 'GitHub ' . $login . ' belongs to another account');
        flash(t('val.github_taken'), 'error');
        redirect($back);
    }
    if (!github_link_user((int)$signedIn['id'], $githubId, $login)) {
        flash(t('val.github_taken'), 'error');
        redirect($back);
    }

    flash(t('account.github_linked_ok', $login), 'ok');
    redirect($back);
}

/* ---------------- signing in ---------------- */

$user = user_by_github($githubId);

if ($user === null && $email !== '') {
    // Only a VERIFIED address of the GitHub account may unlock an existing account.
    $candidate = db_one('SELECT * FROM users WHERE email_lower = ?', [strtolower($email)]);
    if ($candidate !== null && user_by_github($githubId) === null
        && trim((string)($candidate['github_id'] ?? '')) === '') {
        if (github_link_user((int)$candidate['id'], $githubId, $login)) {
            $user = db_one('SELECT * FROM users WHERE id = ?', [(int)$candidate['id']]);
        }
    }
}

if ($user === null) {
    if (!setting_bool('github_allow_signup', true)) {
        log_action('github_failed', 'user', null, 'no account for GitHub ' . $login . ' and sign-up is off');
        flash(t('val.github_no_account'), 'error');
        redirect('login.php');
    }
    if ($email === '') {
        log_action('github_failed', 'user', null, 'GitHub ' . $login . ' has no verified address');
        flash(t('val.github_no_email'), 'error');
        redirect('login.php');
    }

    $created = github_create_user($githubId, $login, $email, lang());
    if (!$created['ok'] || $created['user'] === null) {
        flash(t((string)$created['error']), 'error');
        redirect('register.php');
    }
    $user = $created['user'];
    user_sign_in($user);
    flash(t('account.github_welcome', $login, (string)$user['username']), 'ok');
    redirect(github_return_target());
}

if ((string)$user['status'] === 'suspended') {
    log_action('github_failed', 'user', (int)$user['id'], 'suspended account tried to sign in with GitHub');
    flash(t('val.user_suspended'), 'error');
    redirect('login.php');
}

if ((string)$user['status'] !== 'active') {
    log_action('github_failed', 'user', (int)$user['id'], 'unconfirmed account tried to sign in with GitHub');
    flash(t('val.user_pending'), 'error');
    redirect('login.php');
}

user_sign_in($user);
flash(t('account.github_signed_in', (string)$user['username']), 'ok');
redirect(github_return_target());
