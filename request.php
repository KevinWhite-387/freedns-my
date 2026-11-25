<?php
declare(strict_types=1);

/**
 * freedns.my - public request form.
 *
 * A request carries one to four records of the requested name itself (@): A, AAAA or CNAME.
 * TXT records and sub-names can be added once the name is active. This is the English page;
 * /zh/request.php includes it with the language forced to Chinese.
 */

require __DIR__ . '/inc/bootstrap.php';

// Applying for a name needs a console account. Instead of bouncing a visitor away, the form explains why
// and offers the two ways in; the address is remembered so that both lead back to this form.
//
// To open the form to visitors again, set the settings row request_requires_login to 0:
//     INSERT INTO settings (name, value) VALUES ('request_requires_login', '0')
//     ON DUPLICATE KEY UPDATE value = '0';
if (setting_bool('request_requires_login', true) && current_user() === null) {
    fd_start_session();
    $_SESSION['after_user_login'] = safe_relative_url((string)($_SERVER['REQUEST_URI'] ?? ''), '/request.php');

    $zh = is_zh();
    $text = $zh ? [
        'title'   => '申请域名',
        'lead'    => '申请域名需要先有一个控制台账号 —— 注册免费，只需要一个能收信的邮箱。',
        'why'     => '为什么要登录？',
        'w1'      => '域名要归属到一个账号：记录自助修改、状态查询、续期提醒都靠它；',
        'w2'      => '每个邮箱有固定的注册配额，配额与你在配额商店获得的奖励都记在账号里；',
        'w3'      => '登录后还能用：动态更新（DDNS）、API 密钥、邮件转发、转让工单、在线咨询。',
        'have'    => '已经有账号了？',
        'no'      => '还没有账号？',
        'note'    => '注册后回到这里即可继续填写申请；申请仍需人工审核，通常几天内处理。',
        'hint'    => '若你只是想先看看，可以先读「说明文件」或到「在线咨询」提问。',
    ] : [
        'title'   => 'Apply for a name',
        'lead'    => 'Applying needs a console account first - registering is free and only needs a mailbox that works.',
        'why'     => 'Why an account?',
        'w1'      => 'a name belongs to an account: self-service records, status lookups and reminders hang off it;',
        'w2'      => 'each address has its own registration allowance, and the rewards you earn in the shop are kept there;',
        'w3'      => 'signed in you also get dynamic updates (DDNS), API tokens, mail forwarding, transfer tickets and the chat.',
        'have'    => 'Already have an account?',
        'no'      => 'No account yet?',
        'note'    => 'After registering you come back here and continue with the form; requests are still reviewed by hand, usually within a few days.',
        'hint'    => 'Just looking around? Read the documentation or ask on the chat page.',
    ];

    page_header($text['title']);
    ?>
    <div class="msg info"><?= h($text['lead']) ?></div>

    <h2><?= h($text['why']) ?></h2>
    <ul>
      <li><?= h($text['w1']) ?></li>
      <li><?= h($text['w2']) ?></li>
      <li><?= h($text['w3']) ?></li>
    </ul>

    <p><strong><?= h($text['no']) ?></strong>
      <a href="<?= h(url('account/register.php')) ?>"><?= h(t('account.register_heading')) ?></a></p>
    <p><strong><?= h($text['have']) ?></strong>
      <a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.login_heading')) ?></a>
      &middot; <a href="<?= h(url('account/forgot.php')) ?>"><?= h(t('account.forgot_heading')) ?></a></p>

    <p class="hint"><?= h($text['note']) ?></p>
    <p class="hint"><?= h($text['hint']) ?></p>
    <p>
      <a href="<?= h(url('doc.php')) ?>"><?= h(t('nav.doc')) ?></a>
      &middot; <a href="<?= h(url('chat.php')) ?>"><?= h(t('nav.chat')) ?></a>
      &middot; <a href="<?= h(url('index.php')) ?>"><?= h(t('nav.home')) ?></a>
    </p>
    <?php
    page_footer();
    exit;
}

// The form carries a CSRF token, so the session must exist before the first byte of output.
fd_start_session();

$openZones = zone_list(true);
$allZones  = zone_list();
$zones     = $openZones !== [] ? $openZones : $allZones;

$errors  = [];
$notices = [];
$done    = false;
$created = null;
$createdRecords = [];
$checkOnly = false;

$form = [
    'label'            => '',
    'registrant_name'  => '',
    'registrant_email' => '',
    'organization'     => '',
    'country'          => '',
    'justification'    => '',
];

// The console links here with ?email=…, so the address does not have to be typed again.
$prefillEmail = trim((string)($_GET['email'] ?? ''));
if ($prefillEmail !== '' && valid_email_addr($prefillEmail)) {
    $form['registrant_email'] = $prefillEmail;
}

$defaultZone  = default_zone();
$selectedZone = $defaultZone === null ? '' : (string)$defaultZone['name'];

/** Rows shown in the form: what was submitted after an error, otherwise four empty rows. */
$rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($form) as $field) {
        $form[$field] = trim((string)($_POST[$field] ?? ''));
    }
    $selectedZone = strtolower(trim((string)($_POST['zone'] ?? '')));

    $zone  = zone_by_name($selectedZone);
    $label = normalize_label($form['label'], $selectedZone);
    $name  = ($label === '' || $selectedZone === '') ? '' : $label . '.' . $selectedZone;

    // A reserved name may only be requested with the reserved-name option, and may be one character.
    $isReserved = isset($_POST['reserved']);
    // The applicant decides whether Cloudflare proxies the name (orange cloud) or not.
    // Publishing through a proxy is gone: the registry always publishes DNS-only.
    $isProxied = false;
    // Shortest label that is accepted in this submission.
    $minLabelLength = $isReserved ? 1 : 2;

    $collected = collect_records_from_post($name, $selectedZone, 4, record_types_for_ui($selectedZone, current_user()), true);
    $records   = $collected['records'];
    $rows      = raw_records_from_post();

    /* ---------------- zone and form state ---------------- */

    if ($zones === []) {
        $errors[] = t('val.no_zone');
    } elseif ($zone === null) {
        $errors[] = t('val.zone_choose');
    } elseif ((int)$zone['is_open'] !== 1) {
        $errors[] = t('val.zone_closed', $selectedZone);
    }

    if (!setting_bool('requests_open', true)) {
        $errors[] = t('val.form_closed');
    }

    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $errors[] = t('val.spam');
    }

    /* ---------------- the requested name ---------------- */

    if ($label === '') {
        $errors[] = t('val.name_required');
    } else {
        if (str_contains($label, '.')) {
            $errors[] = t('val.name_dots', $label, $label);
        }
        if (strlen($label) < $minLabelLength || strlen($label) > 63) {
            $errors[] = $isReserved ? t('val.name_length_reserved') : t('val.name_length');
        }
        if (!valid_label($label)) {
            $errors[] = t('val.name_invalid');
        } elseif (in_array($label, reserved_labels(), true)) {
            // Names the registry never hands out (www, ns1, postmaster, ...).
            $errors[] = t('val.name_reserved', $label);
        } elseif ($isReserved && !reserved_table_ready()) {
            // The reserved list is not installed yet: say so instead of blaming the applicant.
            $errors[] = t('val.reserved_no_table');
        } elseif ($isReserved && !reserved_name_is_listed($selectedZone, $label)) {
            $errors[] = t('val.reserved_not_listed', $label);
        } elseif (!$isReserved && reserved_name_is_listed($selectedZone, $label)) {
            $errors[] = t('val.reserved_use_channel', $label);
        }
    }

    /* ---------------- applicant ---------------- */

    if ($form['registrant_name'] === '') {
        $errors[] = t('val.applicant_required');
    } elseif (strlen($form['registrant_name']) > 190) {
        $errors[] = t('val.applicant_long');
    }

    if ($form['registrant_email'] === '') {
        $errors[] = t('val.email_required');
    } elseif (!valid_email_addr($form['registrant_email'])) {
        $errors[] = t('val.email_invalid');
    }

    if (strlen($form['organization']) > 190) {
        $errors[] = t('val.org_long');
    }
    if (strlen($form['country']) > 64) {
        $errors[] = t('val.country_long');
    }
    if (strlen($form['justification']) > 2000) {
        $errors[] = t('val.purpose_long');
    }
    if ($isReserved && $form['justification'] === '') {
        // A reserved name has to be motivated.
        $errors[] = t('val.reserved_purpose');
    }

    if (!isset($_POST['policy'])) {
        $errors[] = t('val.policy_required');
    }

    foreach ($collected['errors'] as $error) {
        $errors[] = $error;
    }

    /* ---------------- registry duplicates and limits ---------------- */

    $existing = null;
    if ($name !== '') {
        $existing = db_one('SELECT * FROM domains WHERE name = ?', [$name]);
        if ($existing !== null && !in_array((string)$existing['status'], ['rejected', 'deleted'], true)) {
            $errors[] = t(
                'val.dup_domain',
                $name,
                status_text((string)$existing['status']),
                (string)$existing['request_code']
            );
        }
    }

    if ($errors === [] && $zone !== null) {
        $maxPerEmail = zone_max_domains_per_email($zone);
        if (count_domains_for_email($form['registrant_email'], $selectedZone) >= $maxPerEmail) {
            $errors[] = t('val.limit_email', $maxPerEmail, $selectedZone);
        }

        // The total allowance of this e-mail address, counted across every suffix.
        $quota = quota_for_email($form['registrant_email']);
        if (!$quota['unlimited'] && $quota['used'] + 1 > $quota['limit']) {
            $errors[] = t('val.quota_exceeded', (string)$quota['limit'], (string)$quota['used']);
        }

        $maxPerIp = max(1, (int)setting('max_requests_per_ip_per_day', '5'));
        if (count_requests_from_ip(client_ip()) >= $maxPerIp) {
            $errors[] = t('val.limit_ip');
        }
    }

    /* ---------------- is the name already taken at the DNS provider? ---------------- */

    // Off by default: the provider may hold records from another platform for the same name, and
    // refusing on that basis blocks names this registry has never issued. A name is refused here only
    // when the local tables say it is taken; the provider is the last word at publish time.
    $checkOnly = isset($_POST['check']);
    $wantsCheck = $name !== '' && setting_bool('cf_precheck', false) && publish_zone_ready($selectedZone);

    if ($errors === [] && $wantsCheck) {
        $check = publish_name_records($selectedZone, $name);

        if (!$check['ok']) {
            // Strict: without an answer the registry does not accept the request.
            log_action('cf_precheck_failed', 'domain', null, $name . ': ' . $check['error']);
            // Not fatal: the provider may be unreachable or not configured. The request goes
            // through, and the provider is asked again at publish time.
        } elseif (!empty($check['taken'])) {
            log_action(
                'cf_precheck_conflict',
                'domain',
                null,
                $name . ': ' . (count($check['records']) > 0
                    ? count($check['records']) . ' record(s) already in ' . publish_provider_label(publish_provider($selectedZone))
                    : 'the name is already registered with ' . publish_provider_label(publish_provider($selectedZone)))
            );
            if ($check['records'] === []) {
                // DNSHE hands out its own subdomains: the name can be taken without carrying a record yet.
                $errors[] = t('val.name_taken', $name);
            } else {
                $errors[] = t(
                    'val.cf_records_exist',
                    $name,
                    (string)count($check['records']),
                    cf_records_summary($check['records'])
                );
            }
        } else {
            $notices[] = t('request.precheck_ok', $name);
        }
    }

    /* ---------------- save ---------------- */

    if ($errors === [] && !$checkOnly) {
        $code = generate_request_code();

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $params = [
                $name,
                $label,
                $selectedZone,
                lang(),
                $isReserved ? 1 : 0,
                $isProxied ? 1 : 0,
                $code,
                $form['registrant_name'],
                $form['registrant_email'],
                $form['organization'] === '' ? null : $form['organization'],
                $form['country'] === '' ? null : $form['country'],
                $form['justification'] === '' ? null : $form['justification'],
                client_ip(),
            ];

            if ($existing !== null) {
                $domainId = (int)$existing['id'];
                // A recycled record keeps no queued change from the previous attempt.
                drop_pending_changes($domainId);
                db_query(
                    "UPDATE domains SET name = ?, label = ?, zone = ?, language = ?, reserved = ?, proxied = ?,
                        status = 'pending', request_code = ?, registrant_name = ?, registrant_email = ?,
                        organization = ?, country = ?, justification = ?, admin_note = NULL,
                        public_note = NULL, decided_at = NULL, decided_by = NULL, created_ip = ?,
                        created_at = NOW(), updated_at = NOW()
                     WHERE id = ?",
                    array_merge($params, [$domainId])
                );
            } else {
                db_query(
                    "INSERT INTO domains (name, label, zone, language, reserved, proxied, status, request_code,
                        registrant_name, registrant_email, organization, country, justification,
                        created_ip, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    $params
                );
                $domainId = (int)$pdo->lastInsertId();
            }

            replace_records($domainId, $records);
            $pdo->commit();

            $created        = domain_by_id($domainId);
            $createdRecords = domain_records($domainId);

            if ($created !== null) {
                notify_admin_new_request($created, $createdRecords);
                log_action(
                    'request_submitted',
                    'domain',
                    $domainId,
                    'Public request from ' . client_ip() . ($isReserved ? ' (reserved name)' : '')
                );
            }
            $done = true;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = t('val.save_error');
        }
    }
}

if ($rows === []) {
    $rows[] = ['sub' => '', 'type' => 'A', 'value' => ''];
}
while (count($rows) < 4) {
    $rows[] = ['sub' => '', 'type' => 'A', 'value' => ''];
}
$rows = array_slice($rows, 0, 4);

page_header(t('request.heading'));

if ($done && $created !== null):
?>
<div class="msg ok">
  <strong><?= h(t('request.done_title')) ?></strong>
</div>

<p><?= h(t('request.done_keep')) ?></p>

<div class="mono-box">
  <p><?= h(t('request.done_code')) ?> <strong><?= h((string)$created['request_code']) ?></strong></p>
  <p><?= h(t('request.done_domain')) ?> <?= h((string)$created['name']) ?></p>
  <p><?= h(t('request.done_zone')) ?> <?= h((string)$created['zone']) ?></p>
  <p><?= h(t('request.done_status')) ?> <?= h(status_text((string)$created['status'])) ?></p>
  <p><?= h(t('request.done_submitted')) ?> <?= h(format_date((string)$created['created_at'])) ?></p>
</div>

<p><?= h(t('request.done_records')) ?></p>
<pre class="wrap code-block"><?= h(implode("\n", record_zone_lines($createdRecords, (string)$created['name']))) ?></pre>

<p>
<?= t('request.done_review', h((string)$created['zone'])) ?>
<?php if (setting_bool('notify_applicant', true)): ?>
<?= t('request.done_mail', h((string)$created['registrant_email'])) ?>
<?php endif; ?>
</p>

<p><?= t('request.done_manage', '<a href="' . h(url('manage.php')) . '">' . h(t('nav.manage')) . '</a>') ?></p>

<p><?= t('request.done_check', '<a href="' . h(url('status.php')) . '">' . h(t('nav.status')) . '</a>') ?></p>

<p><a href="<?= h(url('index.php')) ?>"><?= h(t('request.back_home')) ?></a></p>
<?php
else:
?>
<p><?= t('request.intro', '<a href="' . h(url('doc.php')) . '">' . h(t('request.policy_link')) . '</a>') ?></p>

<?= error_list($errors) ?>

<?php foreach ($notices as $notice): ?>
<div class="msg ok"><?= h($notice) ?></div>
<?php endforeach; ?>

<?php if (!setting_bool('requests_open', true)): ?>
<div class="msg error"><?= h(t('request.closed')) ?></div>
<?php endif; ?>

<?php if ($zones === []): ?>
<div class="msg error"><?= t('request.no_zone') ?></div>
<?php endif; ?>

<div class="msg info">
  <strong><?= h(function_exists('is_zh') && is_zh() ? '申请前请先阅读常见问题' : 'Please read the FAQ before requesting') ?></strong><br>
  <?= h(function_exists('is_zh') && is_zh()
      ? '大部分问题（审核要多久、记录怎么填、为什么不解析）在常见问题里已有答案，看完再申请会快很多。'
      : 'Most questions - how long approval takes, how to fill records, why a name does not resolve - are answered there; reading it first saves a round trip.') ?>
  <br><a href="<?= h(url('faq.php')) ?>"><?= h(function_exists('is_zh') && is_zh() ? '打开常见问题' : 'Open the FAQ') ?> &rarr;</a>
</div>

<form method="post" action="<?= h(url('request.php')) ?>">
<?= csrf_field() ?>

<h3><?= h(t('request.s1')) ?></h3>
<?php if (count($zones) === 1): $onlyZone = $zones[0]; ?>
<p>
<code><?= h((string)$onlyZone['name']) ?></code><?= zone_title_text($onlyZone) !== ''
    && strcasecmp(zone_title_text($onlyZone), (string)$onlyZone['name']) !== 0
    ? ' &mdash; ' . h(zone_title_text($onlyZone)) : '' ?>
</p>
<input type="hidden" name="zone" value="<?= h((string)$onlyZone['name']) ?>">
<?php if (zone_description_text($onlyZone) !== ''): ?>
<?= text_block(zone_description_text($onlyZone)) ?>
<?php endif; ?>
<?php else: ?>
<table>
  <?php foreach ($zones as $index => $zoneOption): ?>
  <tr>
    <td>
      <input type="radio" id="zone<?= $index ?>" name="zone"
             value="<?= h((string)$zoneOption['name']) ?>"
             <?= ((int)$zoneOption['is_open'] !== 1) ? 'disabled' : '' ?>
             <?= strcasecmp((string)$zoneOption['name'], $selectedZone) === 0 ? 'checked' : '' ?>>
    </td>
    <td>
      <label class="inline" for="zone<?= $index ?>"><code><?= h((string)$zoneOption['name']) ?></code></label>
      <?php if (zone_title_text($zoneOption) !== ''
          && strcasecmp(zone_title_text($zoneOption), (string)$zoneOption['name']) !== 0): ?>
      &mdash; <?= h(zone_title_text($zoneOption)) ?>
      <?php endif; ?>
      <?php if ((int)$zoneOption['is_open'] !== 1): ?>
      <span class="err"><?= h(t('request.zone_closed')) ?></span>
      <?php endif; ?>
      <?php if (zone_description_text($zoneOption) !== ''): ?>
      <div class="small"><?= h(shorten(zone_description_text($zoneOption), 200)) ?></div>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h3><?= h(t('request.s2')) ?></h3>
<label for="label"><?= h(t('request.label')) ?></label>
<input type="text" id="label" name="label" value="<?= h($form['label']) ?>" maxlength="63" autocomplete="off" required>
<p class="hint"><?= t('request.label_hint', h($selectedZone !== '' ? $selectedZone : 'zone')) ?></p>

<p>
  <input type="checkbox" id="reserved" name="reserved" value="1"<?= isset($_POST['reserved']) ? ' checked' : '' ?>>
  <label class="inline" for="reserved"><?= h(t('request.reserved_toggle')) ?></label>
</p>
<p class="hint"><?= t('request.reserved_hint') ?></p>


<h3><?= h(t('request.s3')) ?></h3>
<label for="registrant_name"><?= h(t('request.name')) ?></label>
<input type="text" id="registrant_name" name="registrant_name" value="<?= h($form['registrant_name']) ?>" maxlength="190" required>

<label for="registrant_email"><?= h(t('request.email')) ?></label>
<input type="text" id="registrant_email" name="registrant_email" value="<?= h($form['registrant_email']) ?>" maxlength="190" required>
<p class="hint"><?= h(t('request.email_hint')) ?></p>

<label for="organization"><?= h(t('request.org')) ?></label>
<input type="text" id="organization" name="organization" value="<?= h($form['organization']) ?>" maxlength="190">

<label for="country"><?= h(t('request.country')) ?></label>
<input type="text" id="country" name="country" value="<?= h($form['country']) ?>" maxlength="64">
<p class="hint"><?= t('request.country_hint') ?></p>

<h3><?= h(t('request.s4')) ?></h3>
<p class="hint"><?= t('request.records_hint') ?></p>
<div class="msg info"><?= t('request.records_psl') ?></div>

<table>
  <tr>
    <th><?= h(t('request.col_name')) ?></th>
    <th><?= h(t('request.col_type')) ?></th>
    <th><?= h(t('request.col_value')) ?></th>
  </tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td>
      <input type="text" name="new_sub[]" value="<?= h(record_sub_display((string)$row['sub'])) ?>"
             maxlength="63" placeholder="<?= h(t('request.name_placeholder')) ?>">
    </td>
    <td>
      <select name="new_type[]">
        <?php foreach (record_types_for_ui($selectedZone, current_user()) as $type): ?>
        <option value="<?= h($type) ?>"<?= strtoupper((string)$row['type']) === $type ? ' selected' : '' ?>>
          <?= h($type) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </td>
    <td>
      <input type="text" name="new_value[]" value="<?= h((string)$row['value']) ?>" maxlength="255"
             placeholder="<?= h(t('request.value_placeholder')) ?>">
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= t('request.name_hint') ?></p>

<h3><?= h(t('request.s5')) ?></h3>
<label for="justification"><?= h(t('request.purpose')) ?></label>
<textarea id="justification" name="justification" rows="5" maxlength="2000"><?= h($form['justification']) ?></textarea>
<p class="hint"><?= h(t('request.purpose_hint')) ?></p>
<p class="hint"><?= t('request.purpose_reserved_hint') ?></p>

<h3><?= h(t('request.s6')) ?></h3>
<p>
  <input type="checkbox" id="policy" name="policy" value="1"<?= isset($_POST['policy']) ? ' checked' : '' ?>>
  <label class="inline" for="policy"><?= t(
      'request.policy_accept',
      '<a href="' . h(url('doc.php')) . '" target="_blank">' . h(t('request.policy_link')) . '</a>'
  ) ?></label>
</p>

<div style="position:absolute;left:-9999px" aria-hidden="true">
  <label for="website">Leave this field empty</label>
  <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
</div>

<p><input type="submit" value="<?= h(t('request.submit')) ?>"></p>
</form>

<?php if (publish_zone_ready($selectedZone)): ?>
<hr>
<h3><?= h(t('request.check_heading')) ?></h3>
<p class="hint"><?= t('request.check_hint', h($selectedZone)) ?></p>
<form method="post" action="<?= h(url('request.php')) ?>">
<?= csrf_field() ?>
<?php foreach ($form as $field => $value): ?>
<input type="hidden" name="<?= h($field) ?>" value="<?= h($value) ?>">
<?php endforeach; ?>
<input type="hidden" name="zone" value="<?= h($selectedZone) ?>">
<?php if (isset($_POST['reserved'])): ?><input type="hidden" name="reserved" value="1"><?php endif; ?>
<?php if (isset($_POST['policy'])): ?><input type="hidden" name="policy" value="1"><?php endif; ?>
<?php foreach ($rows as $index => $row): ?>
<input type="hidden" name="new_sub[]" value="<?= h(record_sub_display((string)$row['sub'])) ?>">
<input type="hidden" name="new_type[]" value="<?= h((string)$row['type']) ?>">
<input type="hidden" name="new_value[]" value="<?= h((string)$row['value']) ?>">
<?php endforeach; ?>
<p><input type="submit" name="check" value="<?= h(t('request.check_submit')) ?>"></p>
</form>
<?php endif; ?>
<?php
endif;

page_footer();
