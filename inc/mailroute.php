<?php
declare(strict_types=1);

/**
 * freedns.my - mail forwarding through Cloudflare Email Routing.
 *
 * Only names whose suffix is published by Cloudflare can receive mail: the registry switches Email Routing
 * on for the zone, asks Cloudflare to send a confirmation to the address the mail should go to, and then
 * creates one rule per address of the name. Everything is done with the zone's own Cloudflare token.
 */

function mailroute_table_ready(): bool
{
    if (isset($GLOBALS['FD_MAILROUTE_TABLE'])) {
        return (bool)$GLOBALS['FD_MAILROUTE_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'mail\\_routes'") !== null
            && db_one("SHOW COLUMNS FROM zones LIKE 'cf_account_id'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_MAILROUTE_TABLE'] = $ready;

    return $ready;
}

function mailroute_enabled(): bool
{
    return mailroute_table_ready() && setting_bool('mailroute_enabled', true);
}

/** Mail forwarding exists only where Cloudflare publishes the zone. */
function mailroute_zone_ok(string $zoneName): bool
{
    return publish_provider($zoneName) === 'cloudflare'
        && cf_zone_id($zoneName) !== ''
        && cf_zone_token($zoneName) !== '';
}

/** The Cloudflare account of a zone, fetched once and remembered. */
function mailroute_account_id(string $zoneName): string
{
    $memory = $GLOBALS['FD_CF_ACCOUNT'][$zoneName] ?? null;
    if (is_string($memory) && $memory !== '') {
        return $memory;
    }

    $known = (string)db_value('SELECT cf_account_id FROM zones WHERE name = ?', [$zoneName], '');
    if ($known !== '') {
        $GLOBALS['FD_CF_ACCOUNT'][$zoneName] = $known;

        return $known;
    }

    $zoneId = cf_zone_id($zoneName);
    if ($zoneId === '') {
        return '';
    }
    $answer = cf_request('GET', '/zones/' . rawurlencode($zoneId), null, cf_zone_token($zoneName));
    if (empty($answer['ok'])) {
        return '';
    }
    $id = (string)($answer['data']['result']['account']['id'] ?? '');
    if ($id !== '') {
        db_query('UPDATE zones SET cf_account_id = ? WHERE name = ?', [$id, $zoneName]);
        $GLOBALS['FD_CF_ACCOUNT'][$zoneName] = $id;
    }

    return $id;
}

/** Turns Email Routing on for the zone (Cloudflare adds the MX/TXT records itself). */
function mailroute_zone_enable(string $zoneName): array
{
    $zoneId = cf_zone_id($zoneName);
    $token  = cf_zone_token($zoneName);
    if ($zoneId === '' || $token === '') {
        return ['ok' => false, 'error' => 'That suffix has no Cloudflare zone or token.'];
    }

    $status = cf_request('GET', '/zones/' . rawurlencode($zoneId) . '/email/routing', null, $token);
    if (empty($status['ok'])) {
        return ['ok' => false, 'error' => cf_error_text($status)];
    }
    $state = (string)($status['data']['result']['enabled'] ?? '');
    $name  = (string)($status['data']['result']['name'] ?? '');
    if ($state === 'enabled' || $name === 'enabled') {
        return ['ok' => true, 'error' => ''];
    }

    $answer = cf_request('POST', '/zones/' . rawurlencode($zoneId) . '/email/routing/enable', null, $token);
    if (empty($answer['ok'])) {
        return ['ok' => false, 'error' => cf_error_text($answer)];
    }

    log_action('mailroute_enabled', 'zone', null, 'Email Routing enabled for ' . $zoneName);

    return ['ok' => true, 'error' => ''];
}

/** Asks Cloudflare to send the confirmation mail to a destination address. */
function mailroute_destination_ask(string $zoneName, string $email): array
{
    $account = mailroute_account_id($zoneName);
    if ($account === '') {
        return ['ok' => false, 'error' => 'The Cloudflare account of that suffix could not be read.'];
    }
    $token = cf_zone_token($zoneName);

    $answer = cf_request('POST', '/accounts/' . rawurlencode($account) . '/email/routing/addresses',
        ['email' => $email], $token);
    if (!empty($answer['ok'])) {
        return ['ok' => true, 'error' => ''];
    }

    // "already exists" is not a failure: the address may simply be confirmed already.
    $text = cf_error_text($answer);
    if (stripos($text, 'already') !== false || stripos($text, 'exist') !== false) {
        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => false, 'error' => $text];
}

function cf_error_text(array $answer): string
{
    $errors = $answer['data']['errors'] ?? null;
    if (is_array($errors) && $errors !== []) {
        $first = $errors[0];
        if (is_array($first) && isset($first['message'])) {
            return (string)$first['message'];
        }
    }

    return (string)($answer['error'] ?? $answer['status'] ?? 'Cloudflare refused the request.');
}

/** A short, human sentence from a Cloudflare answer. */
function mailroute_routes_of(string $zoneName): array
{
    $zoneId = cf_zone_id($zoneName);
    $token  = cf_zone_token($zoneName);
    if ($zoneId === '' || $token === '') {
        return [];
    }

    $answer = cf_request('GET', '/zones/' . rawurlencode($zoneId) . '/email/routing/rules?per_page=50', null, $token);
    if (empty($answer['ok'])) {
        return [];
    }

    $out = [];
    foreach ((array)($answer['data']['result'] ?? []) as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $out[(string)($rule['id'] ?? '')] = [
            'to'      => (string)($rule['matchers'][0]['value'] ?? ''),
            'forward' => (string)($rule['actions'][0]['value'][0] ?? $rule['actions'][0]['value'] ?? ''),
            'enabled' => !empty($rule['enabled']),
        ];
    }

    return $out;
}

/** The stored routes of one name. */
function mailroute_of_domain(int $domainId): array
{
    if (!mailroute_table_ready()) {
        return [];
    }

    return db_all('SELECT * FROM mail_routes WHERE domain_id = ? ORDER BY local_part ASC', [$domainId]);
}

function mailroute_of_user(int $userId): array
{
    if (!mailroute_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT r.*, d.name, d.zone FROM mail_routes r JOIN domains d ON d.id = r.domain_id
         WHERE r.user_id = ? ORDER BY d.name ASC, r.local_part ASC LIMIT 200',
        [$userId]
    );
}

/**
 * Creates one forwarding address: rules at Cloudflare plus a row here.
 *
 * @return array{ok: bool, error: string, status: string}
 */
function mailroute_add(array $domain, array $user, string $local, string $forwardTo): array
{
    if (!mailroute_enabled()) {
        return ['ok' => false, 'error' => t('val.mail_off'), 'status' => 'error'];
    }
    $zone = (string)$domain['zone'];
    if (!mailroute_zone_ok($zone)) {
        return ['ok' => false, 'error' => t('val.mail_zone'), 'status' => 'error'];
    }
    if ((string)$domain['status'] !== 'active') {
        return ['ok' => false, 'error' => t('val.mail_active'), 'status' => 'error'];
    }
    if (ns_domain_delegated((int)$domain['id'])) {
        return ['ok' => false, 'error' => t('val.mail_delegated'), 'status' => 'error'];
    }

    /*
     * Forwarding is granted by the registry, not taken: an account needs an approved request, and the number
     * of addresses it may keep is what the operator set when approving it (users.forward_quota, 0 = nothing
     * granted). Checking it here means every way of creating a route obeys the same rule.
     */
    $fdZh   = function_exists('lang') && is_zh();
    $quota  = (int)db_value('SELECT forward_quota FROM users WHERE id = ?', [(int)$user['id']], 0);
    $inUse  = (int)db_value('SELECT COUNT(*) FROM mail_routes WHERE user_id = ?', [(int)$user['id']], 0);
    $isNew  = db_one('SELECT id FROM mail_routes WHERE domain_id = ? AND local_part = ?',
        [(int)$domain['id'], strtolower(trim($local))]) === null;
    // The operator hands the right out himself (the Cloudflare page in the back office, or the approval
    // page): when he is the one asking, the gate below does not apply to him - he IS the one who grants it.
    $fdOperator = function_exists('is_admin') && is_admin();

    if ($isNew && !$fdOperator && $quota <= 0) {
        return ['ok' => false, 'status' => 'error', 'error' => $fdZh
            ? '邮件转发需要先在控制台提交申请，注册局审核通过后才能创建转发地址。'
            : 'Mail forwarding has to be requested first: send a request from your console, and the registry '
              . 'grants it.'];
    }
    if ($isNew && !$fdOperator && $inUse >= $quota) {
        return ['ok' => false, 'status' => 'error', 'error' => $fdZh
            ? '你已经用满了 ' . $quota . ' 个转发地址；需要更多请提交新的申请。'
            : 'All ' . $quota . ' forwarding address(es) you were granted are in use; ask for more first.'];
    }

    $local = strtolower(trim($local));
    if ($local === '' || $local === '@') {
        $local = 'info';
    }
    if (preg_match('/^[a-z0-9]([a-z0-9._-]{0,62}[a-z0-9])?$/', $local) !== 1) {
        return ['ok' => false, 'error' => t('val.mail_local'), 'status' => 'error'];
    }
    if (!valid_email_addr($forwardTo)) {
        return ['ok' => false, 'error' => t('val.email_invalid'), 'status' => 'error'];
    }
    if (str_ends_with(strtolower($forwardTo), '.' . strtolower((string)$domain['name']))) {
        return ['ok' => false, 'error' => t('val.mail_loop'), 'status' => 'error'];
    }

    $existing = db_one('SELECT id FROM mail_routes WHERE domain_id = ? AND local_part = ?',
        [(int)$domain['id'], $local]);
    if ($existing !== null) {
        return ['ok' => false, 'error' => t('val.mail_taken', $local), 'status' => 'error'];
    }

    $opened = mailroute_zone_enable($zone);
    if (!$opened['ok']) {
        return ['ok' => false, 'error' => $opened['error'], 'status' => 'error'];
    }
    $asked = mailroute_destination_ask($zone, $forwardTo);
    if (!$asked['ok']) {
        return ['ok' => false, 'error' => $asked['error'], 'status' => 'error'];
    }

    $address = $local . '@' . (string)$domain['name'];
    $answer  = cf_request('POST', '/zones/' . rawurlencode(cf_zone_id($zone)) . '/email/routing/rules', [
        'enabled'  => true,
        'name'     => $local . ' at ' . (string)$domain['name'],
        'matchers' => [['type' => 'literal', 'field' => 'to', 'value' => $address]],
        'actions'  => [['type' => 'forward', 'value' => [$forwardTo]]],
        'priority' => 0,
    ], cf_zone_token($zone));

    if (empty($answer['ok'])) {
        $text = cf_error_text($answer);
        db_query(
            'INSERT INTO mail_routes (domain_id, user_id, local_part, forward_to, status, note, created_at, updated_at)
             VALUES (?, ?, ?, ?, \'error\', ?, NOW(), NOW())',
            [(int)$domain['id'], (int)$user['id'], $local, $forwardTo, $text]
        );

        return ['ok' => false, 'error' => $text, 'status' => 'error'];
    }

    $ruleId = (string)($answer['data']['result']['id'] ?? '');
    db_query(
        'INSERT INTO mail_routes (domain_id, user_id, local_part, forward_to, cf_rule_id, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, \'active\', NOW(), NOW())',
        [(int)$domain['id'], (int)$user['id'], $local, $forwardTo, $ruleId]
    );
    log_action('mailroute_added', 'domain', (int)$domain['id'],
        $address . ' -> ' . $forwardTo);

    return ['ok' => true, 'error' => '', 'status' => 'active'];
}

function mailroute_remove(array $route, array $domain): array
{
    $zone = (string)$domain['zone'];
    $rule = (string)($route['cf_rule_id'] ?? '');

    if ($rule !== '' && mailroute_zone_ok($zone)) {
        $answer = cf_request('DELETE',
            '/zones/' . rawurlencode(cf_zone_id($zone)) . '/email/routing/rules/' . rawurlencode($rule),
            null, cf_zone_token($zone));
        if (empty($answer['ok'])) {
            // A rule that is already gone is fine; anything else is reported.
            $text = cf_error_text($answer);
            if (stripos($text, 'not found') === false) {
                return ['ok' => false, 'error' => $text];
            }
        }
    }

    db_query('DELETE FROM mail_routes WHERE id = ?', [(int)$route['id']]);
    log_action('mailroute_removed', 'domain', (int)$domain['id'],
        (string)$route['local_part'] . '@' . (string)$domain['name'] . ' removed');

    return ['ok' => true, 'error' => ''];
}

function mailroute_status_text(string $status): string
{
    return t('mail.status_' . $status);
}

function mailroute_example(string $name, string $local): string
{
    return $local . '@' . $name;
}
