<?php
declare(strict_types=1);

/**
 * freedns.my - administration: site texts and registry settings.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Settings, the announcement and the policy texts');

$textKeys = ['site_title', 'site_subtitle', 'contact_email',
    'max_domains_per_email', 'max_requests_per_ip_per_day', 'max_records_per_domain', 'default_ttl',
    'listing_page_size', 'max_user_registrations_per_ip_per_day', 'register_daily_limit', 'invite_github_url', 'admin_gate_slug',
    'whois_external_per_hour', 'whois_cache_minutes', 'github_client_id', 'quota_per_email',
    'star_repo', 'star_bonus_amount', 'star_cache_minutes',
    'announcement', 'policy', 'documentation', 'manage_notice', 'changelog',
    'site_title_zh', 'site_subtitle_zh', 'announcement_zh', 'policy_zh', 'documentation_zh',
    'manage_notice_zh', 'changelog_zh'];

$boolKeys = ['requests_open', 'self_service', 'trust_proxy', 'user_registration_open', 'admin_gate_enabled',
    'whois_enabled', 'whois_external', 'github_enabled', 'github_allow_signup', 'manage_closed',
    'quota_enabled', 'star_bonus_enabled', 'invite_required'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // "Generate a secret address" is a separate action inside the same form.
    if (isset($_POST['star_clear_token'])) {
        set_setting('star_token', '');
        star_bonus_forget_cache();
        log_action('settings_updated', 'settings', null, 'star bonus: token removed');
        flash('The stored GitHub token was removed.', 'ok');
        redirect('settings.php');
    }
    if (isset($_POST['quota_grandfather'])) {
        if (!quota_columns_ready()) {
            $errors[] = 'The users table has no quota columns yet. Import upgrade-quota.sql first: '
                . 'ALTER TABLE users ADD COLUMN quota_unlimited TINYINT(1) NOT NULL DEFAULT 0 '
                . 'AFTER github_linked_at, ADD COLUMN quota_limit SMALLINT UNSIGNED DEFAULT NULL '
                . 'AFTER quota_unlimited;';
        } else {
            $freed = quota_grandfather_existing();
            flash(
                'Every account that exists now has an unlimited quota ('
                . (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 1', [], 0)
                . ' account(s), ' . $freed . ' changed just now). New accounts are limited.',
                'ok'
            );
            redirect('settings.php');
        }
    }
    if (isset($_POST['generate_slug'])) {
        $slug = bin2hex(random_bytes(8));
        set_setting('admin_gate_slug', $slug);
        log_action('settings_updated', 'settings', null, 'admin entry: new secret address');
        flash('New secret address generated. Open ' . url('admin/login.php') . '?k=' . $slug
            . ' to reach the back office, and store it somewhere safe.', 'ok');
        redirect('settings.php');
    }

    foreach (['max_domains_per_email', 'max_requests_per_ip_per_day', 'max_records_per_domain', 'default_ttl',
        'listing_page_size', 'max_user_registrations_per_ip_per_day', 'whois_external_per_hour'] as $key) {
        $value = (string)($_POST[$key] ?? '');
        if (!ctype_digit($value) || (int)$value < 1 || (int)$value > 100000) {
            $errors[] = 'The value of ' . str_replace('_', ' ', $key) . ' must be a positive number.';
        }
    }
    $fdDaily = trim((string)($_POST['register_daily_limit'] ?? '45'));
    if ($fdDaily === '' || !ctype_digit($fdDaily) || (int)$fdDaily > 10000) {
        $errors[] = 'The daily registration limit must be 0 (no limit) or a number up to 10000.';
    }

    foreach (['contact_email'] as $key) {
        $value = trim((string)($_POST[$key] ?? ''));
        if ($value !== '' && !valid_email_addr($value)) {
            $errors[] = 'The address in ' . str_replace('_', ' ', $key) . ' is not a valid e-mail address.';
        }
    }

    // The hidden back-office entry: a secret address parameter and a pass phrase.
    $newSlug = trim((string)($_POST['admin_gate_slug'] ?? ''));
    if ($newSlug !== '' && !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $newSlug)) {
        $errors[] = 'The secret address parameter must be 8 to 64 characters of a-z, A-Z, 0-9, "-" or "_".';
    }
    if (isset($_POST['admin_gate_enabled']) && $newSlug === '' && admin_gate_slug() === '') {
        $errors[] = 'Set the secret address parameter before switching the hidden entry on - otherwise '
            . 'the back office cannot be reached at all.';
    }
    $newPass = (string)($_POST['admin_gate_pass'] ?? '');
    if ($newPass !== '' && strlen($newPass) < 8) {
        $errors[] = 'The pass phrase must be at least 8 characters long.';
    }

    // GitHub: the secret is only replaced when a new one is typed, so switching on needs both values.
    $postedGithubId     = trim((string)($_POST['github_client_id'] ?? ''));
    $postedGithubSecret = trim((string)($_POST['github_client_secret'] ?? ''));
    $githubSecretAfter  = $postedGithubSecret !== '' ? $postedGithubSecret : github_client_secret();
    if (isset($_POST['github_enabled']) && ($postedGithubId === '' || $githubSecretAfter === '')) {
        $errors[] = 'Store the GitHub client id and client secret before switching "Sign in with GitHub" '
            . 'on - without them the button cannot work.';
    }
    if ($postedGithubSecret !== '' && strlen($postedGithubSecret) < 20) {
        $errors[] = 'That GitHub client secret looks too short to be real.';
    }

    if ($errors === []) {
        $changed = [];
        foreach ($textKeys as $key) {
            $value = (string)($_POST[$key] ?? '');
            if (!in_array($key, ['documentation', 'announcement', 'policy',
                'documentation_zh', 'announcement_zh', 'policy_zh',
                'manage_notice', 'manage_notice_zh'], true)) {
                $value = trim($value);
            }
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                $changed[] = $key;
            }
        }
        foreach ($boolKeys as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            if ((string)setting($key, '0') !== $value) {
                set_setting($key, $value);
                $changed[] = $key;
            }
        }
        // An empty pass phrase field keeps the stored one.
        if ($newPass !== '') {
            set_setting('admin_gate_pass', password_hash($newPass, PASSWORD_DEFAULT));
            $changed[] = 'admin_gate_pass';
        }

        // An empty GitHub secret field keeps the stored one as well.
        if ($postedGithubSecret !== '') {
            set_setting('github_client_secret', $postedGithubSecret);
            $changed[] = 'github_client_secret';
        }

        // Same for the token that reads the stargazer list.
        $postedStarToken = trim((string)($_POST['star_token'] ?? ''));
        if ($postedStarToken !== '') {
            set_setting('star_token', $postedStarToken);
            $changed[] = 'star_token';
            star_bonus_forget_cache();
        }

        // The star check button lives inside this form as well: save the values first, then check, so a
        // token pasted in the same submission is really used.
        $starTest = '';
        if (isset($_POST['star_check'])) {
            star_bonus_forget_cache();
            $list     = github_stargazers(true);
            $starTest = $list['ok']
                ? ' The repository ' . $list['full_name'] . ' is public and reports ' . (int)$list['stars']
                    . ' star(s); this check read ' . $list['count'] . ' account(s).'
                : ' The repository could not be read: ' . $list['error'];
            log_action('star_check', 'settings', null, $list['ok']
                ? 'read ' . $list['count'] . ' stargazer(s) of ' . $list['full_name']
                : $list['error']);
        }

        log_action('settings_updated', 'settings', null, $changed === [] ? 'no change' : implode(', ', $changed));
        flash(
            ($changed === [] ? 'Nothing changed.' : 'Settings saved (' . count($changed) . ' value(s) updated).')
            . $starTest,
            str_contains($starTest, 'could not be read') ? 'error' : 'ok'
        );
        redirect('settings.php');
    }
}

page_header('Settings');
?>
<p>
These values drive the public pages. Announcement, policy and documentation are plain text:
line breaks are preserved and HTML is displayed literally, so no markup can be injected.
</p>

<?= error_list($errors) ?>

<form method="post" action="settings.php">
<?= csrf_field() ?>

<h2>Identity</h2>
<label for="site_title">Site title</label>
<input type="text" id="site_title" name="site_title" value="<?= h((string)setting('site_title', '')) ?>" maxlength="120">

<label for="site_subtitle">Subtitle</label>
<input type="text" id="site_subtitle" name="site_subtitle" value="<?= h((string)setting('site_subtitle', '')) ?>" maxlength="190">

<label for="zone_name">Zones</label>
<p class="hint">
The suffixes names are handed out under (for example <code>freedns.my</code> and <code>asia.ci</code>) are
maintained on the <a href="zones.php">Zones</a> page, together with their per-zone limits, whether they
accept requests, and whether sub-names are allowed.
</p>

<label for="contact_email">Public contact e-mail</label>
<input type="text" id="contact_email" name="contact_email" value="<?= h((string)setting('contact_email', '')) ?>" maxlength="190">

<p class="hint">
Sending mail (SMTP server, mailbox, sender address, who gets notified) is configured on the
<a href="mail.php">Mail</a> page.
</p>

<h2>Behaviour</h2>
<p>
  <input type="checkbox" id="requests_open" name="requests_open" value="1"<?= setting_bool('requests_open', true) ? ' checked' : '' ?>>
  <label class="inline" for="requests_open">Accept new requests</label>
</p>
<p>
  <input type="checkbox" id="self_service" name="self_service" value="1"<?= setting_bool('self_service', true) ? ' checked' : '' ?>>
  <label class="inline" for="self_service">Let registrants propose record changes themselves (they queue for
  review)</label>
</p>
<p class="hint">
Applicant notifications and the mail transport live on the <a href="mail.php">Mail</a> page.
</p>
<p>
  <input type="checkbox" id="trust_proxy" name="trust_proxy" value="1"<?= setting_bool('trust_proxy') ? ' checked' : '' ?>>
  <label class="inline" for="trust_proxy">Trust reverse-proxy headers for the visitor address</label>
</p>
<p class="hint">Enable the last option only when the site really runs behind Cloudflare or a proxy that
overwrites <code>X-Forwarded-For</code>. Otherwise the visitor address could be forged.</p>

<label for="max_domains_per_email">Default maximum names per e-mail address and zone (0 is not allowed here;
zones can override it)</label>
<input type="text" id="max_domains_per_email" name="max_domains_per_email" value="<?= h((string)setting('max_domains_per_email', '5')) ?>" maxlength="6">

<label for="max_requests_per_ip_per_day">Maximum requests per visitor address per day</label>
<input type="text" id="max_requests_per_ip_per_day" name="max_requests_per_ip_per_day" value="<?= h((string)setting('max_requests_per_ip_per_day', '5')) ?>" maxlength="6">

<label for="max_records_per_domain">Maximum DNS records per name</label>
<input type="text" id="max_records_per_domain" name="max_records_per_domain" value="<?= h((string)setting('max_records_per_domain', '20')) ?>" maxlength="4">

<label for="default_ttl">TTL used in the zone export (seconds)</label>
<input type="text" id="default_ttl" name="default_ttl" value="<?= h((string)setting('default_ttl', '3600')) ?>" maxlength="7">

<label for="listing_page_size">Domains per page in the public list</label>
<input type="text" id="listing_page_size" name="listing_page_size" value="<?= h((string)setting('listing_page_size', '200')) ?>" maxlength="6">

<h2>User console (/account/)</h2>
<p class="hint">
Registrants may open an account with user name, e-mail and password; names are matched by the e-mail
address they were requested with, and the old "request code + e-mail" channel keeps working.
</p>
<p>
  <input type="checkbox" id="user_registration_open" name="user_registration_open" value="1"<?= setting_bool('user_registration_open', true) ? ' checked' : '' ?>>
  <label class="inline" for="user_registration_open">Accept new accounts</label>
</p>
<p>
  <input type="checkbox" id="invite_required" name="invite_required" value="1"<?= setting_bool('invite_required', true) ? ' checked' : '' ?>>
  <label class="inline" for="invite_required">Registration requires an invite code</label>
</p>
<label for="invite_github_url">Where to request an invite code (shown on the register page)</label>
<input type="text" id="invite_github_url" name="invite_github_url" maxlength="190"
       value="<?= h((string)setting('invite_github_url', 'https://github.com/KevinWhite-387/freedns-my/issues/new?template=invite-request.yml')) ?>">
<label for="max_user_registrations_per_ip_per_day">Maximum accounts per visitor address per day</label>
<input type="text" id="max_user_registrations_per_ip_per_day" name="max_user_registrations_per_ip_per_day"
       value="<?= h((string)setting('max_user_registrations_per_ip_per_day', '3')) ?>" maxlength="3">
<label for="register_daily_limit">Daily new-account limit for the whole site (0 = no limit)</label>
<input type="text" id="register_daily_limit" name="register_daily_limit"
       value="<?= h((string)setting('register_daily_limit', '45')) ?>" maxlength="5">

<h2>WHOIS page</h2>
<p class="hint">
The public lookup page answers names of this registry from the register (no personal data) and looks
other domains up in RDAP, with the classic port 43 service as a fallback.
</p>
<p>
  <input type="checkbox" id="whois_enabled" name="whois_enabled" value="1"<?= setting_bool('whois_enabled', true) ? ' checked' : '' ?>>
  <label class="inline" for="whois_enabled">Offer the WHOIS lookup page</label>
</p>
<p>
  <input type="checkbox" id="whois_external" name="whois_external" value="1"<?= setting_bool('whois_external', true) ? ' checked' : '' ?>>
  <label class="inline" for="whois_external">Also look up domains that belong to other registries</label>
</p>
<label for="whois_external_per_hour">External lookups allowed per visitor address and hour</label>
<input type="text" id="whois_external_per_hour" name="whois_external_per_hour" maxlength="5"
       value="<?= (string)max(1, (int)setting('whois_external_per_hour', '20')) ?>">

<label for="whois_cache_minutes">Cache external answers for (minutes, 0 = never)</label>
<input type="text" id="whois_cache_minutes" name="whois_cache_minutes" maxlength="4"
       value="<?= (string)max(0, (int)setting('whois_cache_minutes', '15')) ?>">

<h2>GitHub sign-in (user console only)</h2>
<p class="hint">
Create an OAuth App at GitHub (<em>Settings &rarr; Developer settings &rarr; OAuth Apps &rarr; New OAuth
App</em>) and paste the two values below. The back office never uses GitHub: it keeps its own accounts, its
hidden entry and its pass phrase.
</p>
<table>
  <tr><th>Homepage URL</th><td><code><?= h(rtrim(app_base_url(), '/')) ?>/</code></td></tr>
  <tr><th>Authorization callback URL</th><td><code><?= h(github_callback_url()) ?></code></td></tr>
</table>
<p class="hint">
Paste the callback URL exactly as shown, also when the console is used in Chinese: the app always calls
back to this one canonical address, whatever language the visitor is reading.
</p>
<p>
  <input type="checkbox" id="github_enabled" name="github_enabled" value="1"<?= setting_bool('github_enabled', false) ? ' checked' : '' ?>>
  <label class="inline" for="github_enabled">Offer "Sign in with GitHub" in the console</label>
</p>
<p>
  <input type="checkbox" id="github_allow_signup" name="github_allow_signup" value="1"<?= setting_bool('github_allow_signup', true) ? ' checked' : '' ?>>
  <label class="inline" for="github_allow_signup">Create an account for a visitor whose GitHub address is new</label>
</p>
<label for="github_client_id">Client ID</label>
<input type="text" id="github_client_id" name="github_client_id" maxlength="64"
       value="<?= h(github_client_id()) ?>">

<label for="github_client_secret">Client secret</label>
<input type="password" id="github_client_secret" name="github_client_secret" maxlength="190" value=""
       autocomplete="new-password">
<p class="hint">
<?= github_client_secret() === ''
    ? 'No secret is stored yet. Paste the value GitHub shows once when the app is created.'
    : 'A secret is stored; leave this field empty to keep it. If you rotate the secret at GitHub, paste the new one here.' ?>
</p>
<p class="hint">
Only <code>read:user user:email</code> is requested, so nothing in the visitor's repositories is ever
touched. The access token is used once to read the profile and is then dropped; only the numeric GitHub id
and the login name are stored. Linking to an existing account happens only when GitHub reports a
<strong>verified</strong> address that matches that account.
</p>

<h2>Manage Records page</h2>
<p class="hint">
The page where an applicant enters request code and e-mail to change records. It can be closed for
visitors who are <strong>not signed in</strong>: those then see the notice below and a sign-in link, while
signed-in console accounts keep using the page as before. The notice is shown on that page only, and always
- open or closed.
</p>
<p>
  <input type="checkbox" id="manage_closed" name="manage_closed" value="1"<?= setting_bool('manage_closed', false) ? ' checked' : '' ?>>
  <label class="inline" for="manage_closed">Close Manage Records for visitors who are not signed in</label>
</p>
<label for="manage_notice">Important notice (English)</label>
<textarea id="manage_notice" name="manage_notice" rows="4"><?= h((string)setting('manage_notice', '')) ?></textarea>

<label for="manage_notice_zh">Important notice (中文)</label>
<textarea id="manage_notice_zh" name="manage_notice_zh" rows="4"><?= h((string)setting('manage_notice_zh', '')) ?></textarea>
<p class="hint">
Leave the Chinese one empty to show the English text on the Chinese pages as well. Plain text; line breaks
are kept.
</p>

<h2>Registration quota (per e-mail address)</h2>
<p class="hint">
One total per e-mail address, counted <strong>across all suffixes</strong>: names that are still held
(pending, active or suspended) count, a rejected or deleted one frees a place again. The per-suffix limit
under <em>Behaviour</em> stays in force as well, so both have to allow the request.
</p>
<p>
  <input type="checkbox" id="quota_enabled" name="quota_enabled" value="1"<?= setting_bool('quota_enabled', false) ? ' checked' : '' ?>>
  <label class="inline" for="quota_enabled">Limit how many names one e-mail address may hold in total</label>
</p>
<label for="quota_per_email">Names allowed per e-mail address (total)</label>
<input type="text" id="quota_per_email" name="quota_per_email" maxlength="4"
       value="<?= (int)quota_default_limit() ?>">
<p class="hint">
Accounts may deviate from this: on <a href="users.php">Users</a> a single account can be set to
<em>unlimited</em> or to its own number.
</p>

<table>
  <tr><th>Accounts with unlimited quota</th><td>
      <?= (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 1', [], 0) ?>
      of <?= (int)db_value('SELECT COUNT(*) FROM users', [], 0) ?></td></tr>
  <tr><th>Accounts with their own number</th><td>
      <?= (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 0 AND quota_limit IS NOT NULL', [], 0) ?></td></tr>
  <tr><th>Existing accounts were freed</th><td>
      <?= trim((string)setting('quota_grandfather_at', '')) === ''
          ? '<span class="err">not yet - press the button below before switching the quota on, otherwise '
            . 'your present users lose their allowance</span>'
          : h((string)setting('quota_grandfather_at', '')) ?></td></tr>
</table>
<p>
  <input type="submit" name="quota_grandfather" value="Give every account that exists now an unlimited quota">
</p>
<p class="hint">
This is the migration step: press it once <strong>before</strong> switching the quota on, so the accounts
you already have keep registering as before. It only ever raises a quota.
</p>

<h2>GitHub star bonus</h2>
<p class="hint">
Starring a GitHub repository gives an account an extra registration allowance. The repository must be
<strong>public</strong> (GitHub does not let anybody star a private one) - it may well be a repository that
only carries a README, so no source code has to be published. GitHub has no "did this user star it"
endpoint, so the stargazer list is fetched and compared with the account's <strong>linked GitHub id</strong>;
an account therefore has to link GitHub before it can claim the bonus.
</p>
<table>
  <tr><th>Stargazers read</th><td>
      <?= trim((string)setting('star_cache_at', '')) === ''
          ? 'no list fetched yet'
          : h((string)setting('star_cache_repo', (string)setting('star_repo', ''))) . ' &middot; '
            . h((string)setting('star_cache_at', '')) . ' ('
            . count((array)json_decode((string)setting('star_cache_ids', ''), true)) . ' account(s), GitHub '
            . 'reports ' . (int)setting('star_cache_stars', '0') . ')' ?>
  </td></tr>
  <tr><th>Repository page</th><td>
      <?= star_bonus_repo() === ''
          ? 'not configured yet'
          : '<a href="' . h(star_bonus_url()) . '" rel="noopener">' . h(star_bonus_url()) . '</a> &middot; '
            . '<span class="small">open it to check that it loads and shows a Star button</span>' ?>
  </td></tr>
  <tr><th>Accounts that collected the bonus</th><td>
      <?= (int)db_value('SELECT COUNT(*) FROM users WHERE star_bonus_at IS NOT NULL', [], 0) ?></td></tr>
</table>
<p>
  <input type="checkbox" id="star_bonus_enabled" name="star_bonus_enabled" value="1"<?= setting_bool('star_bonus_enabled', false) ? ' checked' : '' ?>>
  <label class="inline" for="star_bonus_enabled">Offer an extra allowance for starring the repository</label>
</p>
<label for="star_repo">Repository (owner/name)</label>
<input type="text" id="star_repo" name="star_repo" maxlength="190"
       value="<?= h((string)setting('star_repo', '')) ?>" placeholder="yourname/freedns-my">

<label for="star_bonus_amount">Extra names per account</label>
<input type="text" id="star_bonus_amount" name="star_bonus_amount" maxlength="3"
       value="<?= (int)star_bonus_amount() ?>">

<label for="star_token">GitHub token for reading the list (optional but recommended)</label>
<input type="password" id="star_token" name="star_token" maxlength="190" value="" autocomplete="new-password">
<p class="hint">
<?= trim((string)setting('star_token', '')) === ''
    ? 'Without a token GitHub allows only 60 anonymous requests an hour for the whole server; a token with '
      . 'public read access raises that to 5000.'
    : 'A token is stored; leave the field empty to keep it.' ?>
 A classic token with <strong>no scopes</strong> is enough, or a fine-grained token with
 <em>Public Repositories (read-only)</em>.
<br>
Seeing <code>Resource not accessible by personal access token</code>? Then the fine-grained token cannot see
this repository: set its <em>Repository access</em> to <strong>All public repositories</strong> (or tick this
repository under <em>Only select repositories</em>) and check that the <em>Resource owner</em> is the account
or organisation that owns it. A classic token with no scopes avoids the problem entirely.
</p>
<label for="star_cache_minutes">Cache the stargazer list for (minutes)</label>
<input type="text" id="star_cache_minutes" name="star_cache_minutes" maxlength="4"
       value="<?= (int)max(0, (int)setting('star_cache_minutes', '10')) ?>">
<p>
  <input type="submit" name="star_check" value="Check the repository now">  <input type="submit" name="star_clear_token" value="Forget the stored token">
</p>

<h2>Admin entry (hidden back office)</h2>
<p class="hint">
When this is switched on, every address under <code>/admin/</code> answers <em>404</em> unless the visitor
first opens the secret address below and enters the pass phrase. There is no link to the back office
anywhere on the public pages. Keep the secret address and the pass phrase somewhere safe: if they are
lost, set <code>admin_gate_enabled</code> to <code>0</code> in the <code>settings</code> table (or delete
that row) with phpMyAdmin to get back in.
</p>
<p>
  <input type="checkbox" id="admin_gate_enabled" name="admin_gate_enabled" value="1"<?= admin_gate_enabled() ? ' checked' : '' ?>>
  <label class="inline" for="admin_gate_enabled">Hide the back office behind a secret address and a pass
  phrase</label>
</p>

<label for="admin_gate_slug">Secret address parameter</label>
<input type="text" id="admin_gate_slug" name="admin_gate_slug" maxlength="64"
       value="<?= h(admin_gate_slug()) ?>" placeholder="for example 7f3a91c2d4e5b6a7">
<p class="hint">
The address is
<?= admin_gate_slug() === ''
    ? '<em>not set yet</em>'
    : '<code>' . h(admin_gate_url()) . '</code>' ?>
&mdash; type it into the address bar to open the back office. Use 12 random characters or more.
</p>
<p><input type="submit" name="generate_slug" value="Generate a secret address"></p>

<label for="admin_gate_pass">Pass phrase (asked before the sign-in form)</label>
<input type="password" id="admin_gate_pass" name="admin_gate_pass" maxlength="200" value="" autocomplete="new-password">
<p class="hint">
<?= admin_gate_has_pass()
    ? 'A pass phrase is stored. Leave this field empty to keep it, or type a new one to replace it.'
    : 'No pass phrase is stored yet: the secret address alone opens the sign-in form.' ?>
</p>

<h2>Announcement (home page)</h2>
<textarea id="announcement" name="announcement" rows="8"><?= h((string)setting('announcement', '')) ?></textarea>

<h2>Registration policy</h2>
<textarea id="policy" name="policy" rows="16"><?= h((string)setting('policy', '')) ?></textarea>

<h2>Documentation</h2>
<textarea id="documentation" name="documentation" rows="20"><?= h((string)setting('documentation', '')) ?></textarea>

<h2>Chinese pages (/zh/)</h2>
<p class="hint">
These texts are shown on <code>/zh/index.php</code>, <code>/zh/request.php</code> and
<code>/zh/doc.php</code>. Leave a field empty to fall back to the English text above. The Chinese
translations of the page furniture (labels, buttons, messages) live in
<code>inc/lang/zh.php</code>.
</p>

<label for="site_title_zh">Chinese site title</label>
<input type="text" id="site_title_zh" name="site_title_zh" maxlength="120"
       value="<?= h((string)setting('site_title_zh', '')) ?>">

<label for="site_subtitle_zh">Chinese subtitle</label>
<input type="text" id="site_subtitle_zh" name="site_subtitle_zh" maxlength="190"
       value="<?= h((string)setting('site_subtitle_zh', '')) ?>">

<label for="announcement_zh">中文公告（首页）</label>
<textarea id="announcement_zh" name="announcement_zh" rows="8"><?= h((string)setting('announcement_zh', '')) ?></textarea>

<label for="policy_zh">中文注册政策</label>
<textarea id="policy_zh" name="policy_zh" rows="16"><?= h((string)setting('policy_zh', '')) ?></textarea>

<label for="documentation_zh">中文使用文档</label>
<textarea id="documentation_zh" name="documentation_zh" rows="20"><?= h((string)setting('documentation_zh', '')) ?></textarea>

<p><input type="submit" value="Save settings"></p>
</form>

<h2>Maintenance notes</h2>
<ul>
  <li>Each zone is served by this registry itself: approved names are published as
      <code>A</code>, <code>AAAA</code>, <code>CNAME</code> and <code>TXT</code> records. Use
      <em>Export</em> to get the zone file of a zone and paste it into the DNS server that hosts it.</li>
  <li>Record changes proposed by registrants wait in <a href="changes.php">Changes</a> until an operator
      approves them; approving replaces the live records of that name.</li>
  <li>Name-server delegation cannot be offered yet: the zones are not listed in the Public Suffix List.</li>
  <li>The public site exists in English (site root) and Chinese (<code>/zh/</code>). Requests remember the
      language they were submitted in, and the decision mail is written in that language.</li>
  <li>Mail is sent through the transport configured on the <a href="mail.php">Mail</a> page: an SMTP
      mailbox (recommended) or the PHP <code>mail()</code> function. The registry itself works without
      mail; failed messages are recorded in the audit log.</li>
  <li>The public pages contain no scripts and no external resources. The home page, the domain list
      and the policy page set no cookie; the request form, the status page, the manage page and the back
      office use a session cookie (form protection and sign-in).</li>
  <li>Backups: export the database from aaPanel (Databases &rarr; Backup) and copy the site directory.</li>
</ul>
<?php
page_footer();
