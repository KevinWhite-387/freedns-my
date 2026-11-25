<?php
declare(strict_types=1);

/**
 * freedns.my - publishing approved records to Cloudflare, configuration and repair.
 *
 * The registry keeps reviewing by hand; this page only decides how an approved name reaches
 * Cloudflare, and offers the buttons that put a drifted zone right again.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Cloudflare publishing');

$errors  = [];
$notices = [];
$zoneList = null;
$failureLog = [];

$textKeys = ['cf_token', 'cf_failure_policy'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save') {
        foreach ($textKeys as $key) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = trim((string)$_POST[$key]);
            if ($key === 'cf_failure_policy' && !in_array($value, ['strict', 'warn'], true)) {
                $value = 'strict';
            }
            if ($key === 'cf_token' && $value === '') {
                // An empty token field keeps the stored one.
                continue;
            }
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                log_action('settings_updated', 'settings', null, 'dns: ' . $key);
            }
        }

        $value = isset($_POST['cf_enabled']) ? '1' : '0';
        if ((string)setting('cf_enabled', '0') !== $value) {
            set_setting('cf_enabled', $value);
            log_action('settings_updated', 'settings', null, 'dns: cf_enabled');
        }

        $value = isset($_POST['cf_precheck']) ? '1' : '0';
        if ((string)setting('cf_precheck', '1') !== $value) {
            set_setting('cf_precheck', $value);
            log_action('settings_updated', 'settings', null, 'dns: cf_precheck');
        }

        // DNSHE has its own form and action below ("dnshe_save"), so nothing about it is touched here.

        // The test button lives inside this form, so the values above have just been saved: try the
        // credentials now and report the outcome together with the save.
        if (isset($_POST['dnshe_check'])) {
            $check = dnshe_request('subdomains', 'list', ['per_page' => 5], 'GET', '');
            log_action('dnshe_check', 'settings', null,
                $check['ok'] ? 'credentials accepted' : $check['error']);

            if ($check['ok']) {
                flash(
                    'DNS settings saved. DNSHE accepted the credentials and the account holds '
                    . (int)($check['data']['count'] ?? 0) . ' subdomain(s) on the first page.',
                    'ok'
                );
            } else {
                flash('DNS settings saved, but DNSHE refused the request: ' . $check['error'], 'error');
            }
            redirect('dns.php');
        }

        flash('DNS settings saved.', 'ok');
        redirect('dns.php');
    }

    /* ---------------- the DNSHE section (its own form on the page) ---------------- */

    if ($action === 'dnshe_save') {
        $value = isset($_POST['dnshe_enabled']) ? '1' : '0';
        if ((string)setting('dnshe_enabled', '0') !== $value) {
            set_setting('dnshe_enabled', $value);
            log_action('settings_updated', 'settings', null, 'dns: dnshe_enabled');
        }

        $postedKey    = trim((string)($_POST['dnshe_key'] ?? ''));
        $postedSecret = trim((string)($_POST['dnshe_secret'] ?? ''));
        if ($postedKey !== '') {
            set_setting('dnshe_key', $postedKey);
            log_action('settings_updated', 'settings', null, 'dns: dnshe_key');
        }
        if ($postedSecret !== '') {
            set_setting('dnshe_secret', $postedSecret);
            log_action('settings_updated', 'settings', null, 'dns: dnshe_secret');
        }

        if (isset($_POST['dnshe_clear'])) {
            set_setting('dnshe_key', '');
            set_setting('dnshe_secret', '');
            log_action('settings_updated', 'settings', null, 'dns: DNSHE credentials removed');
            flash('The stored DNSHE credentials were removed.', 'ok');
            redirect('dns.php');
        }

        if (isset($_POST['dnshe_check'])) {
            $check = dnshe_request('subdomains', 'list', ['per_page' => 5], 'GET', '');
            log_action('dnshe_check', 'settings', null,
                $check['ok'] ? 'credentials accepted' : $check['error']);

            if ($check['ok']) {
                flash(
                    'DNSHE accepted the credentials: the account holds '
                    . (int)($check['data']['count'] ?? 0) . ' subdomain(s) on the first page.',
                    'ok'
                );
            } else {
                flash('DNS settings saved, but DNSHE refused the request: ' . $check['error'], 'error');
            }
            redirect('dns.php');
        }

        flash('DNSHE settings saved.', 'ok');
        redirect('dns.php');
    }

    if ($action === 'dnshe_check') {
        // Kept for a direct call; the button in the DNSHE form is handled by "dnshe_save" below.
        $check = dnshe_request('subdomains', 'list', ['per_page' => 5], 'GET', '');
        if ($check['ok']) {
            flash('DNSHE accepted the credentials; this account holds '
                . (int)($check['data']['count'] ?? 0) . ' subdomain(s).', 'ok');
        } else {
            flash('DNSHE refused the request: ' . $check['error'], 'error');
        }
        redirect('dns.php');
    }

    if ($action === 'clear_token') {
        set_setting('cf_token', '');
        log_action('settings_updated', 'settings', null, 'dns: token removed');
        flash('The stored API token was removed.', 'ok');
        redirect('dns.php');
    }

    if ($action === 'new_cron_key') {
        set_setting('cf_cron_key', bin2hex(random_bytes(16)));
        log_action('settings_updated', 'settings', null, 'dns: new cron key');
        flash('A new key for the cron entry point was generated.', 'ok');
        redirect('dns.php');
    }

    if ($action === 'save_zones') {
        $saved = 0;
        foreach (zone_list() as $zone) {
            $zoneName = (string)$zone['name'];
            $zoneId   = trim((string)($_POST['zone_id'][$zoneName] ?? ''));
            $zoneTok  = trim((string)($_POST['zone_token'][$zoneName] ?? ''));

            if ($zoneId !== '' && !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $zoneId)) {
                $errors[] = 'The zone id for ' . $zoneName . ' does not look like a zone id.';
                continue;
            }
            if ($zoneTok !== '' && strlen($zoneTok) < 30) {
                $errors[] = 'The token for ' . $zoneName . ' looks too short to be an API token.';
                continue;
            }

            $changed = false;

            // Which service publishes this suffix (empty = not published automatically).
            if (dnshe_columns_ready()) {
                $provider = strtolower(trim((string)($_POST['provider'][$zoneName] ?? '')));
                if (!in_array($provider, ['', 'cloudflare', 'dnshe', 'spaceship', 'anyns'], true)) {
                    $provider = '';
                }
                if ((string)($zone['provider'] ?? '') !== $provider) {
                    db_query('UPDATE zones SET provider = ?, updated_at = NOW() WHERE id = ?',
                        [$provider === '' ? null : $provider, (int)$zone['id']]);
                    log_action('settings_updated', 'zone', (int)$zone['id'],
                        $zoneName . ': publisher ' . ($provider === '' ? 'none' : $provider));
                    $changed = true;
                }

                // Per-suffix DNSHE credentials (empty = keep, "clear" = remove).
                $zoneKey    = trim((string)($_POST['dnshe_zone_key'][$zoneName] ?? ''));
                $zoneSecret = trim((string)($_POST['dnshe_zone_secret'][$zoneName] ?? ''));
                if (isset($_POST['dnshe_zone_clear'][$zoneName])) {
                    db_query('UPDATE zones SET dnshe_key = NULL, dnshe_secret = NULL, updated_at = NOW() WHERE id = ?',
                        [(int)$zone['id']]);
                    log_action('settings_updated', 'zone', (int)$zone['id'], $zoneName . ': DNSHE credentials removed');
                    $changed = true;
                } else {
                    if ($zoneKey !== '' && $zoneKey !== (string)($zone['dnshe_key'] ?? '')) {
                        db_query('UPDATE zones SET dnshe_key = ?, updated_at = NOW() WHERE id = ?',
                            [$zoneKey, (int)$zone['id']]);
                        log_action('settings_updated', 'zone', (int)$zone['id'], $zoneName . ': DNSHE key stored');
                        $changed = true;
                    }
                    if ($zoneSecret !== '') {
                        db_query('UPDATE zones SET dnshe_secret = ?, updated_at = NOW() WHERE id = ?',
                            [$zoneSecret, (int)$zone['id']]);
                        log_action('settings_updated', 'zone', (int)$zone['id'], $zoneName . ': DNSHE secret stored');
                        $changed = true;
                    }
                }
            }

            if ((string)($zone['cf_zone_id'] ?? '') !== $zoneId) {
                db_query('UPDATE zones SET cf_zone_id = ?, updated_at = NOW() WHERE id = ?', [
                    $zoneId === '' ? null : $zoneId,
                    (int)$zone['id'],
                ]);
                log_action('settings_updated', 'zone', (int)$zone['id'],
                    $zoneName . ': Cloudflare zone id ' . ($zoneId === '' ? 'removed' : 'set'));
                $changed = true;
            }

            // An empty token field keeps the stored one; "clear" removes it. Without the column the
            // whole token block stays untouched and the banner below asks for the upgrade.
            if (!cf_token_column_ready()) {
                if (trim((string)($_POST['zone_token'][$zoneName] ?? '')) !== ''
                    || isset($_POST['zone_token_clear'][$zoneName])) {
                    $errors[] = 'The zones table has no "cf_token" column yet, so a token for ' . $zoneName
                        . ' cannot be stored. Import upgrade-cf-tokens.sql first: '
                        . 'ALTER TABLE zones ADD COLUMN cf_token VARCHAR(190) DEFAULT NULL AFTER cf_zone_id;';
                }
            } elseif (isset($_POST['zone_token_clear'][$zoneName])) {
                if (trim((string)($zone['cf_token'] ?? '')) !== '') {
                    db_query('UPDATE zones SET cf_token = NULL, updated_at = NOW() WHERE id = ?', [(int)$zone['id']]);
                    log_action('settings_updated', 'zone', (int)$zone['id'], $zoneName . ': own token removed');
                    $changed = true;
                }
            } elseif ($zoneTok !== '' && $zoneTok !== (string)($zone['cf_token'] ?? '')) {
                db_query('UPDATE zones SET cf_token = ?, updated_at = NOW() WHERE id = ?', [$zoneTok, (int)$zone['id']]);
                log_action('settings_updated', 'zone', (int)$zone['id'], $zoneName . ': own token stored');
                $changed = true;
            }

            if ($changed) {
                $saved++;
            }
        }
        if ($errors === []) {
            flash($saved === 1 ? 'One zone saved.' : $saved . ' zone(s) saved.', 'ok');
            redirect('dns.php');
        }
    }

    if ($action === 'test_zone') {
        $zoneName = strtolower(trim((string)($_POST['zone_name'] ?? '')));
        $token    = cf_zone_token($zoneName);
        if ($token === '') {
            $errors[] = 'No token is stored for ' . $zoneName . '.';
        } else {
            $check = cf_verify_token($token);
            if (!$check['ok']) {
                $errors[] = $zoneName . ': the token was refused: ' . $check['error'];
            } else {
                $notices[] = $zoneName . ': the token is valid (status: '
                    . ($check['status'] === '' ? 'active' : $check['status']) . ').';
                $zoneList  = cf_list_zones($token);
                if (!$zoneList['ok']) {
                    $errors[] = $zoneName . ': listing the zones failed: ' . $zoneList['error'];
                } else {
                    $notices[] = $zoneName . ': this token sees ' . count($zoneList['zones'])
                        . ' zone(s), listed below - copy the matching id into the table.';
                }
            }
        }
    }

    if ($action === 'test') {
        $check = cf_verify_token();
        if ($check['ok']) {
            $notices[] = 'The API token is valid (status: ' . ($check['status'] === '' ? 'active' : $check['status']) . ').';
            $zoneList  = cf_list_zones();
        } else {
            $errors[] = 'The API token was refused: ' . $check['error'];
        }
    }

    if ($action === 'list_zones') {
        $zoneList = cf_list_zones();
        if (!$zoneList['ok']) {
            $errors[] = 'Listing the zones failed: ' . $zoneList['error'];
        }
    }

    if ($action === 'reconcile') {
        $summary = publish_reconcile_all();
        $notices[] = 'Checked ' . $summary['checked'] . ' active name(s): ' . $summary['changed']
            . ' needed a change, ' . $summary['failed'] . ' failed.';
        foreach (array_slice($summary['messages'], 0, 10) as $message) {
            $errors[] = $message;
        }
    }

    if ($action === 'sync_one' || $action === 'withdraw_one') {
        $domain = domain_by_id((int)($_POST['id'] ?? 0));
        if ($domain === null) {
            $errors[] = 'That domain does not exist.';
        } else {
            $domainId = (int)$domain['id'];
            if ($action === 'sync_one') {
                $result = publish_sync_domain(
                    $domainId,
                    (string)$domain['name'],
                    (string)$domain['zone'],
                    publish_records_of(domain_records($domainId)),
                    !empty($domain['proxied'])
                );
                if ($result['ok']) {
                    publish_store_mapping($domainId, $result['rows']);
                    $notices[] = (string)$domain['name'] . ': ' . $result['created'] . ' created, '
                        . $result['updated'] . ' updated, ' . $result['deleted'] . ' removed.';
                    log_action('cf_synced', 'domain', $domainId, (string)$domain['name'] . ' (manual sync)');
                } else {
                    $errors[] = (string)$domain['name'] . ': ' . $result['error'];
                    log_action('cf_sync_failed', 'domain', $domainId, (string)$domain['name'] . ': ' . $result['error']);
                }
            } else {
                $result = publish_remove_domain($domainId, (string)$domain['zone'], false);
                if ($result['ok']) {
                    publish_forget_mapping($domainId);
                    $notices[] = (string)$domain['name'] . ': ' . $result['deleted'] . ' record(s) removed.';
                    log_action('cf_withdrawn', 'domain', $domainId, (string)$domain['name'] . ' (manual removal)');
                } else {
                    $errors[] = (string)$domain['name'] . ': ' . $result['error'];
                }
            }
        }
    }
}

$zones    = zone_list();
$mapped   = 0;
$tokened  = 0;
foreach ($zones as $zone) {
    if (trim((string)($zone['cf_zone_id'] ?? '')) !== '') {
        $mapped++;
    }
    if (cf_zone_token((string)$zone['name']) !== '') {
        $tokened++;
    }
}

$pushed = publish_mapping_ready()
    ? db_all('SELECT d.id, d.name, d.zone, d.status, COUNT(r.id) AS pushed
              FROM domains d LEFT JOIN dns_records r ON r.domain_id = d.id
              GROUP BY d.id ORDER BY d.status ASC, d.name ASC')
    : [];

$recent = db_all(
    "SELECT * FROM audit_log WHERE action LIKE 'cf\\_%' ORDER BY id DESC LIMIT 15"
);

page_header('DNS (Cloudflare)');
?>
<p>
Approved names can be published in Cloudflare automatically: the review stays manual, but the moment you
press <em>Approve</em> (or approve a record change), the registry calls the Cloudflare API for you.
Problems are written to the audit log, and the buttons below put a drifted zone right again.
</p>

<table>
  <tr><th>Automatic publishing</th><td><strong><?= cf_enabled() ? 'on' : 'off' ?></strong></td></tr>
  <tr><th>API token</th><td>
      <?= cf_global_token() === '' ? 'no general token' : 'a general token is stored' ?>;
      <?= $tokened ?> of <?= count($zones) ?> suffix(es) have a token of their own
  </td></tr>
  <tr><th>Failure policy</th><td><?= cf_strict() ? 'strict - a failed call aborts the approval' : 'warn - the approval goes through, the failure is only reported' ?></td></tr>
  <tr><th>DNSHE</th><td>
      <?= setting_bool('dnshe_enabled', false)
          ? (dnshe_global_key() === '' || dnshe_global_secret() === ''
              ? '<span class="err">switched on, but no general credentials are stored</span>'
              : 'switched on, general credentials stored')
          : 'switched off' ?>
      <?= dnshe_columns_ready() ? '' : ' &middot; <span class="err">the provider columns are missing - import upgrade-dnshe.sql</span>' ?>
  </td></tr>
  <tr><th>Zones bound</th><td><?= $mapped ?> of <?= count($zones) ?> have a zone id, <?= $tokened ?> have a token</td></tr>
  <tr><th>Records table</th><td><?= publish_mapping_ready() ? 'dns_records is installed' : '<span class="err">dns_records is missing - import upgrade-cloudflare.sql</span>' ?></td></tr>
</table>

<?= error_list($errors) ?>

<?php foreach ($notices as $notice): ?>
<div class="msg ok"><?= h($notice) ?></div>
<?php endforeach; ?>

<?php if ($zoneList !== null && $zoneList['ok']): ?>
<h2>Zones this token can see</h2>
<p>Copy the id of every suffix you offer into the table further down.</p>
<table>
  <tr><th>Zone</th><th>Id</th><th>Status</th></tr>
  <?php foreach ($zoneList['zones'] as $item): ?>
  <tr>
    <td><code><?= h($item['name']) ?></code></td>
    <td><code><?= h($item['id']) ?></code></td>
    <td><?= h($item['status']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<form method="post" action="dns.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<h2>General API token (fallback)</h2>
<p class="hint">
Create a token in Cloudflare under <em>My Profile &rarr; API Tokens &rarr; Create Token</em> with the
permission <code>Zone &rarr; DNS &rarr; Edit</code>, limited to the zones you hand out names under
(<code>freedns.my</code>, <code>asia.ci</code>, ...). Nothing else is needed - the token cannot touch
billing, page rules or other zones. <strong>Suffixes in different Cloudflare accounts each need their own
token</strong> (see the table below); this one is used only where no own token is stored.
</p>

<label for="cf_token">General token</label>
<input type="password" id="cf_token" name="cf_token" maxlength="190" value="" autocomplete="new-password">
<p class="hint">
<?= cf_global_token() === ''
    ? 'No general token is stored yet.'
    : 'A general token is stored. Leave this field empty to keep it, or paste a new one to replace it.' ?>
</p>

<p>
  <input type="checkbox" id="cf_enabled" name="cf_enabled" value="1"<?= cf_enabled() ? ' checked' : '' ?>>
  <label class="inline" for="cf_enabled">Publish approved names in Cloudflare automatically</label>
</p>

<label for="cf_failure_policy">When a Cloudflare call fails</label>
<select id="cf_failure_policy" name="cf_failure_policy">
  <option value="strict"<?= cf_strict() ? ' selected' : '' ?>>Strict - refuse the approval, change nothing</option>
  <option value="warn"<?= cf_strict() ? '' : ' selected' ?>>Warn - approve locally and log the failure</option>
</select>
<p class="hint">
Strict keeps the registry and the zone in step: a name is never marked ACTIVE while its records are
missing. Warn keeps working during a Cloudflare outage, at the price of drift that you repair with
<em>Sync everything</em>.
</p>

<p>
  <input type="checkbox" id="cf_precheck" name="cf_precheck" value="1"<?= setting_bool('cf_precheck', true) ? ' checked' : '' ?>>
  <label class="inline" for="cf_precheck">Check every request against Cloudflare before accepting it</label>
</p>
<p class="hint">
A name that the zone already answers for is refused with the records it holds, and the applicant can
press <em>Check the DNS records first</em> on the form. A suffix without a zone id or token is not
checked - it counts as free. When Cloudflare cannot be reached the request is refused as well, so the
registry never accepts a name it could not verify.
</p>

<p><input type="submit" value="Save DNS settings"></p>
</form>

<form method="post" action="dns.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test">
  <input type="submit" value="Verify the token">
</form>
<form method="post" action="dns.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="list_zones">
  <input type="submit" value="List the zones of this token">
</form>
<form method="post" action="dns.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="reconcile">
  <input type="submit" value="Sync everything now">
</form>
<form method="post" action="dns.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="clear_token">
  <input type="submit" value="Forget the stored token">
</form>

<h2>DNSHE (second publishing service)</h2>
<p class="hint">
Suffixes that are not hosted in Cloudflare can be published through <strong>DNSHE</strong>
(<code>api005.dnshe.com</code>). Choose the service per suffix in the zone table below. DNSHE works one layer
deeper than Cloudflare: the registry <em>registers</em> the subdomain (for example <code>myname</code> under
<code>us.ci</code>) when a request is approved, then writes the records under it. Suspending a name removes
only the records; deleting it also releases a subdomain the registry registered itself - one that was
already there is never touched.
</p>
<form method="post" action="dns.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="dnshe_save">
<p>
  <input type="checkbox" id="dnshe_enabled" name="dnshe_enabled" value="1"<?= setting_bool('dnshe_enabled', false) ? ' checked' : '' ?>>
  <label class="inline" for="dnshe_enabled">Publish approved names through DNSHE</label>
</p>
<label for="dnshe_key">DNSHE API key (general, used when a suffix has none)</label>
<input type="text" id="dnshe_key" name="dnshe_key" maxlength="190" value="" autocomplete="off"
       placeholder="<?= dnshe_global_key() === '' ? 'cfsd_xxxxxxxxxx' : 'a key is stored' ?>">

<label for="dnshe_secret">DNSHE API secret</label>
<input type="password" id="dnshe_secret" name="dnshe_secret" maxlength="190" value=""
       autocomplete="new-password" placeholder="<?= dnshe_global_secret() === '' ? 'not stored' : 'a secret is stored' ?>">
<p class="hint">
Create the pair in the DNSHE client area under <em>Free Domain Management &rarr; API Management &rarr; Create
API Key</em>; the secret is shown once. Leave a field empty to keep the stored value. The API allows 30
requests a minute, which is plenty for the review queue.
</p>
<p>
  <input type="submit" value="Save the DNSHE settings">
  <input type="submit" name="dnshe_check" value="Test the DNSHE credentials">
  <input type="submit" name="dnshe_clear" value="Forget the DNSHE credentials">
</p>
</form>

<h2>Zone bindings and tokens</h2>
<p class="hint">
A Cloudflare API token belongs to <strong>one account</strong>, and its <em>Zone Resources</em> can only
list zones of that account. Suffixes that live in <strong>different Cloudflare accounts</strong> therefore
need <strong>their own token</strong>; the general token above is only a fallback for suffixes that have
none. Leave a token field empty to keep the stored one, or tick <em>clear</em> to remove it.
</p>
<form method="post" action="dns.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save_zones">
<table>
  <tr><th>Zone</th><th>Service</th><th>Cloudflare zone id</th><th>Own API token</th><th>DNSHE key / secret</th>
      <th>Names</th></tr>
  <?php foreach ($zones as $zone): ?>
  <?php
  $zoneName = (string)$zone['name'];
  $current  = publish_provider($zoneName);
  $chosen   = strtolower(trim((string)($zone['provider'] ?? '')));
  ?>
  <tr>
    <td><code><?= h($zoneName) ?></code></td>
    <td>
      <select name="provider[<?= h($zoneName) ?>]">
        <option value=""<?= $chosen === '' && $current === '' ? ' selected' : '' ?>>not published</option>
        <option value="cloudflare"<?= $chosen === 'cloudflare' || ($chosen === '' && $current === 'cloudflare') ? ' selected' : '' ?>>Cloudflare</option>
        <option value="dnshe"<?= $chosen === 'dnshe' ? ' selected' : '' ?>>DNSHE</option>
        <option value="spaceship"<?= $chosen === 'spaceship' || ($chosen === '' && $current === 'spaceship') ? ' selected' : '' ?>>Spaceship</option>
        <option value="anyns"<?= $chosen === 'anyns' || ($chosen === '' && $current === 'anyns') ? ' selected' : '' ?>>ANYNS</option>
      </select>
      <div class="small"><?= h(publish_provider_label($current)) ?>
        <?= publish_zone_ready($zoneName) ? '<span class="ok">ready</span>' : '<span class="err">not ready</span>' ?></div>
    </td>
    <td>
      <input type="text" name="zone_id[<?= h($zoneName) ?>]" maxlength="64"
             value="<?= h((string)($zone['cf_zone_id'] ?? '')) ?>" placeholder="32 hex characters">
    </td>
    <td>
      <input type="password" name="zone_token[<?= h($zoneName) ?>]" maxlength="190" value=""
             autocomplete="new-password"
             placeholder="<?= trim((string)($zone['cf_token'] ?? '')) === ''
                 ? (cf_global_token() === '' ? 'no token yet' : 'using the general token')
                 : 'a token is stored' ?>">
      <?php if (trim((string)($zone['cf_token'] ?? '')) !== ''): ?>
      <label class="inline"><input type="checkbox" name="zone_token_clear[<?= h($zoneName) ?>]" value="1">
        clear</label>
      <?php endif; ?>
    </td>
    <td>
      <input type="text" name="dnshe_zone_key[<?= h($zoneName) ?>]" maxlength="190" value=""
             autocomplete="off"
             placeholder="<?= trim((string)($zone['dnshe_key'] ?? '')) === ''
                 ? (dnshe_global_key() === '' ? 'no key yet' : 'using the general key')
                 : 'a key is stored' ?>">
      <input type="password" name="dnshe_zone_secret[<?= h($zoneName) ?>]" maxlength="190" value=""
             autocomplete="new-password"
             placeholder="<?= trim((string)($zone['dnshe_secret'] ?? '')) === '' ? 'no secret' : 'a secret is stored' ?>">
      <?php if (trim((string)($zone['dnshe_key'] ?? '')) !== '' || trim((string)($zone['dnshe_secret'] ?? '')) !== ''): ?>
      <label class="inline"><input type="checkbox" name="dnshe_zone_clear[<?= h($zoneName) ?>]" value="1">
        clear</label>
      <?php endif; ?>
    </td>
    <td><?= (int)db_value('SELECT COUNT(*) FROM domains WHERE zone = ?', [$zoneName], 0) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">
A suffix without a zone id (or without any token) is not published automatically; its names keep being
exported by hand.
</p>
<p><input type="submit" value="Save zone bindings and tokens"></p>
</form>

<h2>Check one token</h2>
<p class="hint">
Verifies the token of a suffix and lists the zones that token can see, which is the quickest way to find
the right zone id for each Cloudflare account.
</p>
<?php foreach ($zones as $zone): ?>
<?php $zoneName = (string)$zone['name']; ?>
<form method="post" action="dns.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test_zone">
  <input type="hidden" name="zone_name" value="<?= h($zoneName) ?>">
  <input type="submit" value="Test the token of <?= h($zoneName) ?>">
</form>
<?php endforeach; ?>

<?php if (!cf_token_column_ready()): ?>
<div class="msg error">
  The <code>zones</code> table has no <code>cf_token</code> column yet, so a suffix cannot keep its own
  API token (every suffix then uses the general token above). Import <code>upgrade-cf-tokens.sql</code> or
  run:
  <pre class="wrap code-block">ALTER TABLE zones ADD COLUMN cf_token VARCHAR(190) DEFAULT NULL AFTER cf_zone_id;</pre>
</div>
<?php endif; ?>

<h2>Names and what is pushed</h2>
<?php if (!publish_mapping_ready()): ?>
<div class="msg error">The <code>dns_records</code> table is missing; import <code>upgrade-cloudflare.sql</code>.</div>
<?php elseif ($pushed === []): ?>
<p>No names yet.</p>
<?php else: ?>
<table>
  <tr><th>Name</th><th>Zone</th><th>Status</th><th>Pushed records</th><th>Action</th></tr>
  <?php foreach ($pushed as $row): ?>
  <tr>
    <td><a href="edit.php?id=<?= (int)$row['id'] ?>"><?= h((string)$row['name']) ?></a></td>
    <td><?= h((string)$row['zone']) ?></td>
    <td><?= h(status_text((string)$row['status'])) ?></td>
    <td><?= (int)$row['pushed'] ?></td>
    <td class="actions">
      <?php if (publish_zone_ready((string)$row['zone'])): ?>
      <form method="post" action="dns.php" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sync_one">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Sync now">
      </form>
      <?php if ((int)$row['pushed'] > 0): ?>
      <form method="post" action="dns.php" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="withdraw_one">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Remove from Cloudflare">
      </form>
      <?php endif; ?>
      <?php else: ?>
      <span class="small">not published automatically</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Periodic reconciliation</h2>
<p class="hint">
Add an aaPanel cron job (or a real cron entry) that requests this address regularly; it republishes
every active name and repairs anything that was changed by hand in the Cloudflare dashboard.
</p>
<p>
  <code><?= h(url('admin/dns-cron.php')) ?>?key=<?= h((string)setting('cf_cron_key', '') === ''
      ? '(generate a key first)' : (string)setting('cf_cron_key', '')) ?></code>
</p>
<form method="post" action="dns.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="new_cron_key">
  <input type="submit" value="Generate a new cron key">
</form>
<p class="hint">
A daily run is plenty. The endpoint answers plain text (<code>ok</code> plus a summary) so the cron log
stays readable, and every run is written to the audit log.
</p>

<h2>Recent DNS activity</h2>
<?php if ($recent === []): ?>
<p>Nothing recorded yet.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Action</th><th>Detail</th></tr>
  <?php foreach ($recent as $entry): ?>
  <tr>
    <td class="small"><?= h((string)$entry['created_at']) ?></td>
    <td><code><?= h((string)$entry['action']) ?></code></td>
    <td class="small"><?= h(shorten((string)($entry['detail'] ?? ''), 160)) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();
