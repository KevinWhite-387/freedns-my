<?php
declare(strict_types=1);

/**
 * freedns.my - bound cloud servers (RainYun API v2).
 *
 * Endpoints used (documented at api.rainyun.com, base https://api.v2.rainyun.com):
 *   GET  /product/rcs/                 the instance list
 *   GET  /product/rcs/{id}/            one instance (state, specs, expiry)
 *   POST /product/rcs/{id}/start       power on
 *   POST /product/rcs/{id}/stop        power off
 *   POST /product/rcs/{id}/reboot      restart
 *   POST /product/rcs/{id}/changeos    reinstall / change the operating system (destroys data)
 *   GET  /product/rcs/os-templates     the images that can be installed
 * Auth: the header "x-api-key".
 *
 * The holder's own key is stored with AES-256-GCM; the key that encrypts it lives in the settings table,
 * never in a file, so a copy of the site alone cannot read anybody's API key. A holder's key is only ever
 * used for his own instances, and every action is written to server_actions.
 */

const FD_RAINYUN_API = 'https://api.v2.rainyun.com';

function rainyun_tables_ready(): bool
{
    if (isset($GLOBALS['FD_RAINYUN_TABLES'])) {
        return (bool)$GLOBALS['FD_RAINYUN_TABLES'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'server\\_bindings'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_RAINYUN_TABLES'] = $ready;

    return $ready;
}

function rainyun_enabled(): bool
{
    return rainyun_tables_ready() && setting_bool('rainyun_enabled', true);
}

/* ------------------------------------------------------------------ *
 * Encryption of the holders' keys                                     *
 * ------------------------------------------------------------------ */

/** The site key used to encrypt stored API keys: created on first use, kept in settings. */
function rainyun_secret_key(): string
{
    $stored = (string)setting('secret_key', '');
    if ($stored !== '') {
        $raw = base64_decode($stored, true);
        if (is_string($raw) && strlen($raw) === 32) {
            return $raw;
        }
    }

    if (!function_exists('openssl_encrypt')) {
        return '';
    }

    $raw = random_bytes(32);
    set_setting('secret_key', base64_encode($raw));
    log_action('secret_key_created', 'settings', null, 'Site key for encrypted credentials created');

    return $raw;
}

function rainyun_crypto_ready(): bool
{
    return function_exists('openssl_encrypt') && function_exists('openssl_decrypt')
        && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
}

/** @return string base64(iv | tag | ciphertext), or '' when it cannot be encrypted. */
function rainyun_encrypt(string $plain, string $context): string
{
    $key = rainyun_secret_key();
    if ($key === '' || !rainyun_crypto_ready()) {
        return '';
    }

    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context, 16);
    if (!is_string($cipher)) {
        return '';
    }

    return base64_encode($iv . $tag . $cipher);
}

function rainyun_decrypt(?string $blob, string $context): string
{
    $key = rainyun_secret_key();
    if ($key === '' || $blob === null || $blob === '' || !rainyun_crypto_ready()) {
        return '';
    }

    $raw = base64_decode((string)$blob, true);
    if (!is_string($raw) || strlen($raw) < 29) {
        return '';
    }

    $iv     = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain  = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context);

    return is_string($plain) ? $plain : '';
}

/* ------------------------------------------------------------------ *
 * Talking to RainYun                                                  *
 * ------------------------------------------------------------------ */

/**
 * @return array{ok: bool, status: int, data: array, error: string}
 */
function rainyun_request(string $method, string $path, ?array $body, string $apiKey, int $timeout = 20): array
{
    $result = ['ok' => false, 'status' => 0, 'data' => [], 'error' => ''];
    if ($apiKey === '') {
        $result['error'] = 'No API key is available for this instance.';

        return $result;
    }

    $url     = FD_RAINYUN_API . $path;
    $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
    $headers = [
        'x-api-key: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    $raw    = '';
    $status = 0;

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }
        $answer = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($answer === false) {
            $result['error'] = 'The request failed: ' . curl_error($handle);
            curl_close($handle);

            return $result;
        }
        $raw = (string)$answer;
        curl_close($handle);
    } else {
        $context = stream_context_create(['http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $payload ?? '',
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $answer = @file_get_contents($url, false, $context);
        if ($answer === false) {
            $result['error'] = 'The request failed (no cURL and allow_url_fopen seems to be off).';

            return $result;
        }
        $raw = (string)$answer;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                $status = (int)$match[1];
            }
        }
    }

    $result['status'] = $status;
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $result['error'] = 'The API answered something that is not JSON (HTTP ' . $status . ').';
        $result['data']  = ['raw' => substr($raw, 0, 500)];

        return $result;
    }
    $result['data'] = $data;

    if ($status >= 200 && $status < 300) {
        $result['ok'] = true;

        return $result;
    }

    // RainYun reports a problem in "message"/"msg"/"error"; show whatever is there.
    foreach (['message', 'msg', 'error', 'detail'] as $key) {
        if (!empty($data[$key]) && is_string($data[$key])) {
            $result['error'] = (string)$data[$key];
            break;
        }
    }
    if ($result['error'] === '') {
        $result['error'] = 'The API refused the request (HTTP ' . $status . ').';
    }

    return $result;
}

/**
 * The "options" query value the API insists on for the instance list, but never shows an example of.
 *
 * The documented list call is "GET /product/rcs/?options=string" with options marked as required, so a
 * call without it is answered with "invalid parameters". The right value is unknown, therefore the
 * candidates below are tried in order and the first one that works is remembered in the settings - after
 * that every later call uses it directly and no probing happens again.
 *
 * @return array<int, string>
 */
function rainyun_option_candidates(): array
{
    $known = trim((string)setting('rainyun_options', ''));
    $list  = ['{}', '{"page":1,"limit":50}', 'all', '1', 'list', ''];
    if ($known !== '') {
        array_unshift($list, $known);
    }

    return array_values(array_unique($list));
}

/**
 * Reads the instance list, finding out the "options" value on the first run.
 *
 * @return array{ok: bool, error: string, rows: array, options: string}
 */
function rainyun_list_instances(string $apiKey, int $timeout = 15): array
{
    $tried = [];
    foreach (rainyun_option_candidates() as $candidate) {
        $path = '/product/rcs/';
        if ($candidate !== '') {
            $path .= '?options=' . rawurlencode($candidate);
        }

        $answer = rainyun_request('GET', $path, null, $apiKey, $timeout);
        if ($answer['ok']) {
            if ($candidate !== '' && (string)setting('rainyun_options', '') !== $candidate) {
                set_setting('rainyun_options', $candidate);
                log_action('rainyun_options', 'settings', null,
                    'the instance list accepts options=' . $candidate);
            }

            return ['ok' => true, 'error' => '', 'rows' => rainyun_rows($answer['data']), 'options' => $candidate];
        }

        $tried[] = ($candidate === '' ? '(without options)' : 'options=' . $candidate) . ' -> ' . $answer['error'];
    }

    return ['ok' => false, 'error' => implode("\n", $tried), 'rows' => [], 'options' => ''];
}

/** The instance rows out of a list answer, whatever wrapper the API uses. */function rainyun_rows(array $data): array
{
    foreach (['data', 'result', 'list', 'items', 'rows'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $inner = $data[$key];
            foreach (['data', 'list', 'items', 'rows'] as $deeper) {
                if (isset($inner[$deeper]) && is_array($inner[$deeper])) {
                    return array_values(array_filter($inner[$deeper], 'is_array'));
                }
            }
            if (array_is_list($inner ?? [])) {
                return array_values(array_filter($inner, 'is_array'));
            }

            return [$inner];
        }
    }

    return array_is_list($data) ? array_values(array_filter($data, 'is_array')) : [$data];
}

/** The first value that exists among several spellings (the API is not consistent about names). */
function rainyun_pick(array $row, array $names): string
{
    $lower = [];
    foreach ($row as $key => $value) {
        $lower[strtolower((string)$key)] = $value;
    }
    foreach ($names as $name) {
        $key = strtolower($name);
        if (isset($lower[$key]) && $lower[$key] !== null && $lower[$key] !== '') {
            return is_scalar($lower[$key]) ? (string)$lower[$key] : '';
        }
    }

    return '';
}

/** Name, specs, state and expiry date out of one instance row. */
function rainyun_parse(array $row): array
{
    $id = rainyun_pick($row, ['id', 'instance_id', 'server_id', 'rcs_id']);
    $name = rainyun_pick($row, ['name', 'instance_name', 'hostname', 'title']);
    $status = strtolower(rainyun_pick($row, ['status', 'state', 'power_status', 'instance_status']));

    $expiresRaw = rainyun_pick($row, [
        'expire_at', 'expired_at', 'expire_time', 'expiration', 'expiration_time', 'expiry',
        'due_date', 'end_time', 'end_at', 'expire_date', 'expire',
    ]);
    $expires = '';
    if ($expiresRaw !== '') {
        $stamp = is_numeric($expiresRaw) ? (int)$expiresRaw : strtotime($expiresRaw);
        if ($stamp !== false && $stamp > 0) {
            $expires = date('Y-m-d H:i:s', $stamp);
        }
    }

    $specs = [];
    foreach ([
        'cpu' => ['cpu', 'cpu_num', 'vcpu', 'cores'],
        'mem' => ['memory', 'mem', 'ram', 'memory_size'],
        'disk' => ['disk', 'disk_size', 'storage', 'disk_space'],
        'bw' => ['bandwidth', 'bw', 'net_limit', 'flow'],
    ] as $label => $candidates) {
        $value = rainyun_pick($row, $candidates);
        if ($value !== '') {
            $specs[] = $label . ' ' . $value;
        }
    }
    $region = rainyun_pick($row, ['region', 'area', 'zone', 'node', 'region_name']);
    if ($region !== '') {
        array_unshift($specs, $region);
    }

    return [
        'id'         => $id,
        'name'       => $name === '' ? $id : $name,
        'status'     => $status,
        'expires_at' => $expires,
        'specs'      => implode(' / ', $specs),
        'raw'        => $row,
    ];
}

/* ------------------------------------------------------------------ *
 * Bindings                                                            *
 * ------------------------------------------------------------------ */

function rainyun_binding_by_id(int $id): ?array
{
    if (!rainyun_tables_ready() || $id <= 0) {
        return null;
    }

    return db_one('SELECT * FROM server_bindings WHERE id = ?', [$id]);
}

function rainyun_bindings_of(int $userId): array
{
    // Only the instances that were assigned to this account: an instance the operator registered but did
    // not hand over belongs to nobody and must never show up in a console.
    if (!rainyun_tables_ready() || $userId <= 0) {
        return [];
    }

    return db_all(
        'SELECT * FROM server_bindings WHERE user_id = ? AND assigned_at IS NOT NULL
         ORDER BY (expires_at IS NULL), expires_at ASC, id ASC',
        [$userId]
    );
}

/**
 * Is the cloud server area in read-only maintenance mode?
 *
 * The registry can freeze the whole feature while it is being rebuilt: holders then see their servers and their
 * state, but nothing can be changed - no binding, no power action, no backup, no firewall, no reinstall. The
 * pages hide the buttons, and every write path checks this again for itself, because hiding a button is not a
 * security measure.
 *
 * Setting: srv_readonly - '1' (default) = frozen, '0' = working as usual.
 */
function rainyun_readonly(): bool
{
    return setting_bool('srv_readonly', true);
}

/** The wording shown while the feature is frozen. */
function rainyun_readonly_note(): string
{
    $custom = trim((string)setting_lang('srv_readonly_note'));
    if ($custom !== '') {
        return $custom;
    }

    $zh = function_exists('lang') && is_zh();

    return $zh
        ? '出于安全原因，雨云服务器功能正在进行全新升级维护，暂时为只读状态：你仍可查看已绑定的服务器与状态，但绑定、开关机、重启、重装、备份与防火墙等操作暂时不可用。请您耐心等待通知。'
        : 'For security reasons the cloud server feature is being rebuilt and is read-only for the moment: you can still see your bound servers and their state, but binding, power actions, reinstall, backups and the firewall are unavailable. Please wait for an announcement.';
}

/**
 * The key used for calls about one instance.
 *
 * Two ways of binding live side by side: the operator may register an instance with the PLATFORM key and assign
 * it, or a holder may bind his own instance with HIS OWN key. The binding's own key therefore wins when there
 * is one, and the platform key stays the fallback - which keeps every existing call working unchanged.
 */
function rainyun_key_of(array $binding): string
{
    $own = trim((string)rainyun_decrypt($binding['key_cipher'] ?? null, 'binding:' . (int)$binding['id']));
    if ($own !== '') {
        return $own;
    }

    return trim((string)setting('rainyun_platform_key', ''));
}

/** Does this binding carry the holder's own key? */
function rainyun_has_own_key(array $binding): bool
{
    return trim((string)($binding['key_cipher'] ?? '')) !== '';
}

/**
 * Binds an instance with the holder's OWN RainYun API key (the self-service route).
 *
 * He must have an approved request first - the registry decides who may use the feature - and then his key is
 * checked against the provider BEFORE anything is stored, kept encrypted, and never shown again: only the last
 * four characters stay as a hint.
 *
 * @return array{ok: bool, error: string, id: int}
 */
function rainyun_bind_self(array $user, string $apiKey, string $remoteId, string $label): array
{
    if (!rainyun_tables_ready()) {
        return ['ok' => false, 'error' => t('val.srv_off'), 'id' => 0];
    }
    $apiKey   = trim($apiKey);
    $remoteId = trim($remoteId);

    if ($apiKey === '' || $remoteId === '') {
        return ['ok' => false, 'error' => t('val.srv_need_key'), 'id' => 0];
    }
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $remoteId) !== 1) {
        return ['ok' => false, 'error' => t('val.srv_bad_id'), 'id' => 0];
    }
    if (db_one('SELECT id FROM server_bindings WHERE provider = ? AND remote_id = ?',
            ['rainyun', $remoteId]) !== null) {
        return ['ok' => false, 'error' => t('val.srv_taken'), 'id' => 0];
    }
    // The approved request is the gate.
    if (db_one("SELECT id FROM server_requests WHERE user_id = ? AND status = 'approved'",
            [(int)$user['id']]) === null) {
        return ['ok' => false, 'error' => t('val.srv_need_approval'), 'id' => 0];
    }

    // Prove the key works before storing anything.
    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode($remoteId) . '/', null, $apiKey);
    if (!$answer['ok']) {
        return ['ok' => false, 'error' => t('val.srv_key_refused') . ' ' . $answer['error'], 'id' => 0];
    }
    $parsed = rainyun_parse(rainyun_rows($answer['data'])[0] ?? []);

    db_query(
        "INSERT INTO server_bindings (user_id, access_level, label, provider, remote_id, name, specs, status,
            expires_at, detail, key_hint, last_synced_at, created_at, updated_at)
         VALUES (?, 'full', ?, 'rainyun', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())",
        [
            (int)$user['id'], $label === '' ? null : $label, $remoteId, $parsed['name'], $parsed['specs'],
            $parsed['status'], $parsed['expires_at'] === '' ? null : $parsed['expires_at'],
            json_encode($parsed['raw'], JSON_UNESCAPED_UNICODE),
            substr($apiKey, -4),
        ]
    );
    $id     = (int)db()->lastInsertId();
    $cipher = rainyun_encrypt($apiKey, 'binding:' . $id);
    db_query('UPDATE server_bindings SET key_cipher = ?, assigned_by = ?, assigned_at = NOW() WHERE id = ?',
        [$cipher, (string)($user['username'] ?? ''), $id]);

    rainyun_audit($id, (int)$user['id'], 'bind_self', 'ok',
        'bound with the holder\'s own key by ' . (string)($user['username'] ?? ''));

    return ['ok' => true, 'error' => '', 'id' => $id];
}

function rainyun_bindings_all(int $limit = 300): array
{
    if (!rainyun_tables_ready()) {
        return [];
    }

    return db_all(
        'SELECT b.*, u.username, u.email FROM server_bindings b
         LEFT JOIN users u ON u.id = b.user_id
         ORDER BY (b.expires_at IS NULL), b.expires_at ASC LIMIT ' . max(1, $limit)
    );
}

function rainyun_actions(int $limit = 100): array
{
    if (!rainyun_tables_ready()) {
        return [];
    }

    return db_all(
        'SELECT a.*, u.username FROM server_actions a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT ' . max(1, $limit)
    );
}

function rainyun_audit(?int $bindingId, ?int $userId, string $action, string $result, string $message = ''): void
{
    if (!rainyun_tables_ready()) {
        return;
    }

    db_query(
        'INSERT INTO server_actions (binding_id, user_id, action, result, message, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())',
        [$bindingId, $userId, $action, $result, substr($message, 0, 500)]
    );
    log_action('server_' . $action, 'server', $bindingId, $result . ': ' . $message);
}

/*
 * NOTE: the old "platform key only" version of rainyun_key_of() used to sit here. It was removed when the
 * holder's own key came back (the two would otherwise be one function declared twice, which takes the whole
 * site down with "Cannot redeclare"). The single definition now lives further down, next to
 * rainyun_has_own_key() and rainyun_bind_self().
 */

/** May this holder do something with the instance, or only look at it? */
function rainyun_may(array $binding, string $need = 'read'): bool
{
    if ($need === 'read') {
        return true;
    }
    if ($need === 'full') {
        return strtolower((string)($binding['access_level'] ?? 'read')) === 'full';
    }

    return false;
}

/**
 * Registers an instance in the back office (no holder yet).
 *
 * @return array{ok: bool, error: string, id: int}
 */
function rainyun_attach(string $remoteId, string $label, string $staff): array
{
    if (!rainyun_tables_ready()) {
        return ['ok' => false, 'error' => t('val.srv_off'), 'id' => 0];
    }
    $key = trim((string)setting('rainyun_platform_key', ''));
    if ($key === '') {
        return ['ok' => false, 'error' => 'Fill in the platform key first.', 'id' => 0];
    }
    $remoteId = trim($remoteId);
    if ($remoteId === '') {
        return ['ok' => false, 'error' => 'Give the instance id.', 'id' => 0];
    }
    if (db_one('SELECT id FROM server_bindings WHERE provider = ? AND remote_id = ?', ['rainyun', $remoteId]) !== null) {
        return ['ok' => false, 'error' => 'That instance is already registered (assign it instead).', 'id' => 0];
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode($remoteId) . '/', null, $key);
    if (!$answer['ok']) {
        return ['ok' => false, 'error' => $answer['error'], 'id' => 0];
    }
    $parsed = rainyun_parse(rainyun_rows($answer['data'])[0] ?? []);

    db_query(
        "INSERT INTO server_bindings (user_id, access_level, label, provider, remote_id, name, specs, status,
            expires_at, detail, last_synced_at, created_at, updated_at)
         VALUES (NULL, 'read', ?, 'rainyun', ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())",
        [
            $label === '' ? null : $label, $remoteId, $parsed['name'], $parsed['specs'], $parsed['status'],
            $parsed['expires_at'] === '' ? null : $parsed['expires_at'],
            json_encode($parsed['raw'], JSON_UNESCAPED_UNICODE),
        ]
    );
    $id = (int)db()->lastInsertId();
    rainyun_audit($id, null, 'attach', 'ok', $remoteId . ' registered by ' . $staff);

    return ['ok' => true, 'error' => '', 'id' => $id];
}

/** Assigns an instance to an account (or takes it away with $userId = 0), and sets the level. */
function rainyun_assign(array $binding, int $userId, string $level, string $staff, ?int $requestId = null): array
{
    $level = $level === 'full' ? 'full' : 'read';
    if ($userId > 0 && db_one('SELECT id FROM users WHERE id = ?', [$userId]) === null) {
        return ['ok' => false, 'error' => 'That account does not exist.'];
    }

    db_query(
        'UPDATE server_bindings SET user_id = ?, access_level = ?, assigned_by = ?, assigned_at = NOW(),
            request_id = ?, updated_at = NOW() WHERE id = ?',
        [
            $userId > 0 ? $userId : null, $level, $userId > 0 ? $staff : null,
            $requestId, (int)$binding['id'],
        ]
    );
    rainyun_audit((int)$binding['id'], $userId > 0 ? $userId : null, 'assign', 'ok',
        $userId > 0
            ? 'assigned to account #' . $userId . ' with level ' . $level . ' by ' . $staff
            : 'unassigned by ' . $staff);

    return ['ok' => true, 'error' => ''];
}

/* ------------------------------------------------------------------ *
 * "I would like a server"                                             *
 * ------------------------------------------------------------------ */

/**
 * A holder asks for a server: naming one he bought, or asking the operator to pick one.
 *
 * @return array{ok: bool, error: string}
 */
function rainyun_request_submit(array $user, string $remoteId, string $purpose): array
{
    if (!rainyun_tables_ready()) {
        return ['ok' => false, 'error' => t('val.srv_off')];
    }
    $purpose = trim($purpose);
    if (strlen($purpose) < 10) {
        return ['ok' => false, 'error' => t('val.srv_need_purpose')];
    }
    if (db_one("SELECT id FROM server_requests WHERE user_id = ? AND status = 'pending'", [(int)$user['id']]) !== null) {
        return ['ok' => false, 'error' => t('val.srv_pending')];
    }
    $remoteId = trim($remoteId);
    if ($remoteId !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $remoteId) !== 1) {
        return ['ok' => false, 'error' => t('val.srv_bad_id')];
    }

    db_query(
        'INSERT INTO server_requests (user_id, remote_id, purpose, status, created_at)
         VALUES (?, ?, ?, \'pending\', NOW())',
        [(int)$user['id'], $remoteId === '' ? null : $remoteId, $purpose]
    );
    log_action('srv_request', 'server', null,
        (string)$user['username'] . ' asked for a server' . ($remoteId === '' ? '' : ' (' . $remoteId . ')'));

    $admin = trim((string)setting('notify_admin_email', ''));
    if ($admin !== '') {
        $previous = lang_switch('en');
        send_mail($admin, 'freedns.my: a server was requested', implode("\n", [
            t('mail.dear', $admin), '',
            (string)$user['username'] . ' (' . (string)$user['email'] . ') asks for a cloud server.',
            'Instance he names: ' . ($remoteId === '' ? '(none - please pick one)' : $remoteId),
            '',
            $purpose, '',
            rtrim(app_base_url(), '/') . '/admin/servers.php',
        ]));
        lang_switch($previous);
    }

    return ['ok' => true, 'error' => ''];
}

function rainyun_requests_of(int $userId): array
{
    if (!rainyun_tables_ready()) {
        return [];
    }

    return db_all('SELECT * FROM server_requests WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]);
}

function rainyun_requests_pending(): array
{
    if (!rainyun_tables_ready()) {
        return [];
    }

    return db_all(
        "SELECT r.*, u.username, u.email FROM server_requests r JOIN users u ON u.id = r.user_id
         WHERE r.status = 'pending' ORDER BY r.id ASC LIMIT 200"
    );
}

/** Approves (with an assignment) or rejects a request. */
function rainyun_request_decide(int $requestId, bool $approve, string $reason, string $staff,
    ?array $binding = null, string $level = 'read'): array
{
    $request = rainyun_tables_ready() ? db_one('SELECT * FROM server_requests WHERE id = ?', [$requestId]) : null;
    if ($request === null) {
        return ['ok' => false, 'error' => 'That request does not exist.'];
    }
    if ((string)$request['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'That request was already answered.'];
    }

    $bindingId = null;
    if ($approve) {
        if ($binding === null) {
            return ['ok' => false, 'error' => 'Choose the instance to hand over first.'];
        }
        $assigned = rainyun_assign($binding, (int)$request['user_id'], $level, $staff, $requestId);
        if (!$assigned['ok']) {
            return $assigned;
        }
        $bindingId = (int)$binding['id'];
    }

    db_query(
        'UPDATE server_requests SET status = ?, reason = ?, binding_id = ?, decided_by = ?, decided_at = NOW()
         WHERE id = ?',
        [$approve ? 'approved' : 'rejected', $approve ? null : substr($reason, 0, 255), $bindingId, $staff,
         $requestId]
    );
    log_action($approve ? 'srv_request_approved' : 'srv_request_rejected', 'server', $bindingId,
        'request ' . $requestId . ($approve ? ' approved' : ': ' . $reason));

    $to = (string)db_value('SELECT email FROM users WHERE id = ?', [(int)$request['user_id']], '');
    if ($to !== '' && mail_pref_allows($to, 'account')) {
        $previous = lang_switch('en');
        send_mail($to, $approve ? 'Your cloud server is ready' : 'Your server request was answered',
            implode("\n", array_merge(
                [t('mail.dear', $to), ''],
                [$approve
                    ? 'A cloud server was assigned to your account' . ($binding !== null ? ': ' . (string)$binding['name'] : '')
                      . ' (level: ' . $level . ').'
                    : 'Your request was not approved.'],
                $approve ? [] : ['', 'Reason: ' . $reason],
                ['', url('account/servers.php'), '', (string)setting('site_title', 'freedns.my')]
            )));
        lang_switch($previous);
    }

    return ['ok' => true, 'error' => ''];
}

/** Marks a request as withdrawn (by the holder). */
function rainyun_request_withdraw(array $request, array $user): bool
{
    if ((int)$request['user_id'] !== (int)$user['id'] || (string)$request['status'] !== 'pending') {
        return false;
    }
    db_query("UPDATE server_requests SET status = 'withdrawn', decided_at = NOW() WHERE id = ?",
        [(int)$request['id']]);
    log_action('srv_request_withdrawn', 'server', null, 'request ' . (int)$request['id'] . ' withdrawn');

    return true;
}

/**
 * Binds one instance to an account: the key is verified against the instance before anything is stored.
 *
 * @return array{ok: bool, error: string}
 */
function rainyun_bind(array $user, string $apiKey, string $remoteId, string $label): array
{
    if (!rainyun_enabled()) {
        return ['ok' => false, 'error' => t('val.srv_off')];
    }
    if (!rainyun_crypto_ready()) {
        return ['ok' => false, 'error' => t('val.srv_no_crypto')];
    }
    $apiKey   = trim($apiKey);
    $remoteId = trim($remoteId);
    if ($apiKey === '' || $remoteId === '') {
        return ['ok' => false, 'error' => t('val.srv_need')];
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode($remoteId) . '/', null, $apiKey);
    if (!$answer['ok']) {
        return ['ok' => false, 'error' => $answer['error']];
    }
    $parsed = rainyun_parse(rainyun_rows($answer['data'])[0] ?? []);

    $cipher = rainyun_encrypt($apiKey, 'binding:0');
    if ($cipher === '') {
        return ['ok' => false, 'error' => t('val.srv_no_crypto')];
    }

    // Encrypt once more with the row id as context, so a ciphertext cannot be moved to another row.
    db_query(
        "INSERT INTO server_bindings (user_id, provider, label, remote_id, name, specs, status, expires_at,
            detail, key_cipher, key_hint, last_synced_at, created_at, updated_at)
         VALUES (?, 'rainyun', ?, ?, ?, ?, ?, ?, ?, '', ?, NOW(), NOW(), NOW())",
        [
            (int)$user['id'], $label === '' ? null : $label, $remoteId,
            $parsed['name'], $parsed['specs'], $parsed['status'], $parsed['expires_at'] === '' ? null : $parsed['expires_at'],
            json_encode($parsed['raw'], JSON_UNESCAPED_UNICODE), substr($apiKey, -4),
        ]
    );
    $id = (int)db()->lastInsertId();

    db_query('UPDATE server_bindings SET key_cipher = ? WHERE id = ?',
        [rainyun_encrypt($apiKey, 'binding:' . $id), $id]);

    rainyun_audit($id, (int)$user['id'], 'bind', 'ok', $remoteId . ' (' . $parsed['name'] . ')');
    unset($GLOBALS['FD_RAINYUN_TABLES']);

    return ['ok' => true, 'error' => ''];
}

function rainyun_unbind(array $binding, bool $byOperator = false): void
{
    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'unbind', 'ok',
        $byOperator ? 'removed by the operator' : 'removed by the holder');
    db_query('DELETE FROM server_bindings WHERE id = ?', [(int)$binding['id']]);
}

/**
 * Reads the instance again with the holder's key and stores what came back.
 *
 * @return array{ok: bool, error: string}
 */
function rainyun_sync(array $binding, bool $audit = false): array
{
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'error' => t('val.srv_no_key')];
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/', null, $key);
    if (!$answer['ok']) {
        db_query('UPDATE server_bindings SET sync_error = ?, last_synced_at = NOW() WHERE id = ?',
            [substr($answer['error'], 0, 255), (int)$binding['id']]);
        if ($audit) {
            rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'sync', 'error', $answer['error']);
        }

        return ['ok' => false, 'error' => $answer['error']];
    }

    $parsed = rainyun_parse(rainyun_rows($answer['data'])[0] ?? []);
    db_query(
        'UPDATE server_bindings SET name = ?, specs = ?, status = ?, expires_at = ?, detail = ?,
            sync_error = NULL, last_synced_at = NOW(), updated_at = NOW() WHERE id = ?',
        [
            $parsed['name'], $parsed['specs'], $parsed['status'],
            $parsed['expires_at'] === '' ? null : $parsed['expires_at'],
            json_encode($parsed['raw'], JSON_UNESCAPED_UNICODE),
            (int)$binding['id'],
        ]
    );
    if ($audit) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'sync', 'ok', $parsed['status']);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * One of: start, stop, reboot, changeos (reinstall).
 *
 * @return array{ok: bool, error: string}
 */
function rainyun_action(array $binding, string $action, array $params = []): array
{
    $allowed = ['start', 'stop', 'reboot', 'changeos'];
    if (!in_array($action, $allowed, true)) {
        return ['ok' => false, 'error' => 'Unknown action.'];
    }

    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'error' => t('val.srv_no_key')];
    }
    if (in_array($action, ['start', 'stop', 'reboot'], true) && !setting_bool('rainyun_allow_power', true)) {
        return ['ok' => false, 'error' => t('val.srv_power_off')];
    }
    if ($action === 'changeos' && !setting_bool('rainyun_allow_reinstall', false)) {
        return ['ok' => false, 'error' => t('val.srv_reinstall_off')];
    }

    $path = '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/' . $action;
    $answer = rainyun_request('POST', $path, $params === [] ? null : $params, $key, 40);

    if (!$answer['ok']) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], $action, 'error', $answer['error']);

        return ['ok' => false, 'error' => $answer['error']];
    }

    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], $action, 'ok',
        $params === [] ? '' : json_encode($params, JSON_UNESCAPED_UNICODE));

    return ['ok' => true, 'error' => ''];
}

/** The images that can be installed (for the reinstall form). */
function rainyun_os_templates(array $binding): array
{
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return [];
    }

    $answer = rainyun_request('GET', '/product/rcs/os-templates', null, $key);
    if (!$answer['ok']) {
        return [];
    }

    $out = [];
    foreach (rainyun_rows($answer['data']) as $row) {
        $id   = rainyun_pick($row, ['id', 'template_id', 'os_id']);
        $name = rainyun_pick($row, ['name', 'title', 'os_name', 'template_name']);
        if ($id !== '') {
            $out[$id] = $name === '' ? $id : $name;
        }
    }

    return $out;
}

/* ------------------------------------------------------------------ *
 * Expiry reminders (called by the cron endpoint)                      *
 * ------------------------------------------------------------------ */

/**
 * Mails the holders whose instance expires soon (or has expired), at most every three days.
 *
 * @return array{checked: int, mailed: int}
 */
function rainyun_remind(int $limit = 50): array
{
    $out = ['checked' => 0, 'mailed' => 0];
    if (!rainyun_tables_ready() || !setting_bool('rainyun_enabled', true)) {
        return $out;
    }

    $days = max(1, min(60, (int)setting('rainyun_remind_days', '7')));
    $rows = db_all(
        'SELECT b.*, u.email, u.username FROM server_bindings b JOIN users u ON u.id = b.user_id
         WHERE b.expires_at IS NOT NULL AND b.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
           AND (b.reminded_at IS NULL OR b.reminded_at < DATE_SUB(NOW(), INTERVAL 3 DAY))
         ORDER BY b.expires_at ASC LIMIT ' . max(1, $limit),
        [$days]
    );

    foreach ($rows as $row) {
        $out['checked']++;
        $left = (int)floor((strtotime((string)$row['expires_at']) - time()) / 86400);

        $previous = lang_switch('en');
        $sent = send_mail((string)$row['email'], t('mail.srv_expire_subject', (string)$row['name']),
            implode("\n", [
                t('mail.dear', (string)$row['username']),
                '',
                t('mail.srv_expire_body', (string)$row['name'], (string)$row['expires_at'], (string)max(0, $left)),
                '',
                url('account/servers.php'),
                '',
                t('mail.srv_expire_footer'),
                '',
                (string)setting('site_title', 'freedns.my'),
            ]));
        lang_switch($previous);

        if ($sent) {
            db_query('UPDATE server_bindings SET reminded_at = NOW() WHERE id = ?', [(int)$row['id']]);
            $out['mailed']++;
            rainyun_audit((int)$row['id'], (int)$row['user_id'], 'remind', 'ok', 'expires ' . (string)$row['expires_at']);
        }
    }

    return $out;
}

function rainyun_days_left(?string $expiresAt): ?int
{
    if ($expiresAt === null || trim($expiresAt) === '') {
        return null;
    }
    $stamp = strtotime($expiresAt);
    if ($stamp === false) {
        return null;
    }

    return (int)floor(($stamp - time()) / 86400);
}

function rainyun_state_text(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return '-';
    }

    return t('srv.state_' . $status);
}

/* ------------------------------------------------------------------ *
 * Backups (/product/rcs/{id}/backup/...)                              *
 * ------------------------------------------------------------------ */

/**
 * The backups of one instance.
 *
 * @return array<int, array{id: string, name: string, created: string, size: string, state: string}>
 */
function rainyun_backups(array $binding): array
{
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return [];
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/backup/', null, $key);
    if (!$answer['ok']) {
        return [];
    }

    $out = [];
    foreach (rainyun_rows($answer['data']) as $row) {
        $id = rainyun_pick($row, ['id', 'backup_id', 'bid']);
        if ($id === '') {
            continue;
        }
        $created = rainyun_pick($row, ['created_at', 'create_time', 'time', 'date', 'created']);
        $stamp   = $created === '' ? false : (is_numeric($created) ? (int)$created : strtotime($created));
        $out[] = [
            'id'      => $id,
            'name'    => rainyun_pick($row, ['name', 'title', 'remark', 'note']),
            'created' => $stamp === false ? $created : date('Y-m-d H:i', $stamp),
            'size'    => rainyun_pick($row, ['size', 'disk_size', 'space']),
            'state'   => rainyun_pick($row, ['status', 'state']),
        ];
    }

    return $out;
}

/**
 * One backup action: create, restore, delete or cancel.
 *
 * "restore" and "delete" change or remove data, so they are audited like a reinstall; restoring also
 * requires the holder to have the same confirmation phrase as a reinstall.
 *
 * @return array{ok: bool, error: string}
 */
function rainyun_backup_action(array $binding, string $action, string $backupId = '', array $params = []): array
{
    if (!in_array($action, ['create', 'restore', 'delete', 'cancel'], true)) {
        return ['ok' => false, 'error' => 'Unknown backup action.'];
    }
    if (!setting_bool('rainyun_allow_backup', true)) {
        return ['ok' => false, 'error' => t('val.srv_backup_off')];
    }
    if (in_array($action, ['restore', 'delete'], true) && !setting_bool('rainyun_allow_reinstall', false)) {
        // Restoring overwrites the disk and deleting throws a backup away - both are destructive, so they
        // follow the same switch as reinstalling, which is off by default.
        return ['ok' => false, 'error' => t('val.srv_reinstall_off')];
    }

    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'error' => t('val.srv_no_key')];
    }

    $base = '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/backup/';
    $path = $base;
    $body = $params === [] ? null : $params;

    if ($action === 'create') {
        if ($body === null) {
            // The API takes the remark under one of these names; sending all of them is harmless and makes
            // the call work whichever spelling the instance expects.
            $body = ['name' => 'backup ' . date('Y-m-d H:i'), 'remark' => 'backup ' . date('Y-m-d H:i')];
        }
    } else {
        if ($backupId === '') {
            return ['ok' => false, 'error' => 'A backup has to be chosen.'];
        }
        $path = $base . rawurlencode($backupId) . '/';
        if ($action === 'restore' || $action === 'cancel') {
            $path .= $action;
        }
    }

    $method = $action === 'delete' ? 'DELETE' : ($action === 'create' ? 'POST' : 'POST');
    if ($action === 'delete') {
        $path = $base . rawurlencode($backupId) . '/';
    }

    $answer = rainyun_request($method, $path, $body, $key, 40);
    if (!$answer['ok']) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'backup_' . $action, 'error', $answer['error']);

        return ['ok' => false, 'error' => $answer['error']];
    }

    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'backup_' . $action, 'ok',
        $backupId === '' ? '' : 'backup ' . $backupId);

    return ['ok' => true, 'error' => ''];
}

/* ------------------------------------------------------------------ *
 * Firewall (/product/rcs/{id}/firewall/...)                           *
 * ------------------------------------------------------------------ */

/**
 * The firewall of one instance: its mode and its rules.
 *
 * A wrong rule can lock the holder out of his own server, so every change is audited and the page warns
 * about it; the switch rainyun_allow_firewall turns the whole section off.
 *
 * @return array{mode: string, rules: array<int, array<string, string>>}
 */
function rainyun_firewall(array $binding): array
{
    $out = ['mode' => '', 'rules' => []];
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return $out;
    }

    $base = '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/firewall';

    $mode = rainyun_request('GET', $base . '/mode', null, $key);
    if ($mode['ok']) {
        $out['mode'] = rainyun_pick($mode['data'], ['mode', 'firewall_mode', 'type', 'status']);
        if ($out['mode'] === '') {
            $rows = rainyun_rows($mode['data']);
            $out['mode'] = rainyun_pick($rows[0] ?? [], ['mode', 'firewall_mode', 'type', 'status']);
        }
    }

    $rules = rainyun_request('GET', $base . '/rule', null, $key);
    if ($rules['ok']) {
        foreach (rainyun_rows($rules['data']) as $row) {
            $id = rainyun_pick($row, ['id', 'rule_id']);
            if ($id === '') {
                continue;
            }
            $out['rules'][] = [
                'id'       => $id,
                'protocol' => rainyun_pick($row, ['protocol', 'proto', 'type']),
                'port'     => rainyun_pick($row, ['port', 'ports', 'port_range']),
                'action'   => rainyun_pick($row, ['action', 'policy', 'allow']),
                'ip'       => rainyun_pick($row, ['ip', 'source', 'cidr', 'address']),
                'remark'   => rainyun_pick($row, ['remark', 'name', 'note', 'description']),
                'pos'      => rainyun_pick($row, ['pos', 'position', 'order', 'sort']),
            ];
        }
    }

    return $out;
}

/** The mode of the firewall: usually "off", "white" (only the listed rules pass) or "black". */
function rainyun_firewall_set_mode(array $binding, string $mode): array
{
    $allowed = ['off', 'white', 'black'];
    if (!in_array($mode, $allowed, true)) {
        return ['ok' => false, 'error' => 'Unknown firewall mode.'];
    }
    if (!setting_bool('rainyun_allow_firewall', true)) {
        return ['ok' => false, 'error' => t('val.srv_fw_off')];
    }

    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'error' => t('val.srv_no_key')];
    }

    $path = '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/firewall/mode';
    $answer = rainyun_request('POST', $path, ['mode' => $mode], $key);
    if (!$answer['ok']) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'firewall_mode', 'error', $answer['error']);

        return ['ok' => false, 'error' => $answer['error']];
    }

    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'firewall_mode', 'ok', 'mode ' . $mode);

    return ['ok' => true, 'error' => ''];
}

/**
 * One rule action: add, update, delete or move.
 *
 * @param array<string, string> $rule protocol, port, action, ip, remark
 * @return array{ok: bool, error: string}
 */
function rainyun_firewall_rule(array $binding, string $action, array $rule, string $ruleId = '', int $pos = 0): array
{
    if (!in_array($action, ['add', 'update', 'delete', 'move'], true)) {
        return ['ok' => false, 'error' => 'Unknown firewall action.'];
    }
    if (!setting_bool('rainyun_allow_firewall', true)) {
        return ['ok' => false, 'error' => t('val.srv_fw_off')];
    }

    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'error' => t('val.srv_no_key')];
    }

    $base = '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/firewall/rule';
    $path = $base;
    $method = 'POST';
    $body = [];

    if ($action === 'add' || $action === 'update') {
        $protocol = strtolower(trim($rule['protocol'] ?? 'tcp'));
        if (!in_array($protocol, ['tcp', 'udp', 'icmp', 'all', 'any'], true)) {
            return ['ok' => false, 'error' => 'The protocol has to be tcp, udp, icmp or all.'];
        }
        $port = trim($rule['port'] ?? '');
        if (in_array($protocol, ['tcp', 'udp'], true)
            && preg_match('/^\d{1,5}(-\d{1,5})?(,\d{1,5}(-\d{1,5})?)*$/', $port) !== 1) {
            return ['ok' => false, 'error' => 'Give a port or a range, for example 22 or 8000-8100.'];
        }
        foreach (explode(',', $port === '' ? '0' : $port) as $piece) {
            foreach (explode('-', trim($piece)) as $number) {
                if ($number !== '' && ((int)$number < 0 || (int)$number > 65535)) {
                    return ['ok' => false, 'error' => 'Ports have to be between 0 and 65535.'];
                }
            }
        }

        $pass = strtolower(trim($rule['action'] ?? 'accept'));
        if (!in_array($pass, ['accept', 'drop', 'reject', 'allow', 'deny'], true)) {
            return ['ok' => false, 'error' => 'The action has to be accept or drop.'];
        }

        $source = trim($rule['ip'] ?? '');
        if ($source !== '' && $source !== '0.0.0.0/0'
            && !filter_var($source, FILTER_VALIDATE_IP)
            && preg_match('#^[0-9a-f:.]+/\d{1,3}$#i', $source) !== 1) {
            return ['ok' => false, 'error' => 'The source has to be an address or a network like 10.0.0.0/8.'];
        }

        $body = [
            'protocol' => $protocol,
            'port'     => $port === '' ? '0' : $port,
            'action'   => $pass,
            'ip'       => $source === '' ? '0.0.0.0/0' : $source,
            'remark'   => trim($rule['remark'] ?? ''),
        ];
        if ($action === 'update') {
            if ($ruleId === '') {
                return ['ok' => false, 'error' => 'A rule has to be chosen.'];
            }
            $path = $base . '/' . rawurlencode($ruleId);
            $method = 'PUT';
        }
    } elseif ($action === 'delete') {
        if ($ruleId === '') {
            return ['ok' => false, 'error' => 'A rule has to be chosen.'];
        }
        $path = $base . '/' . rawurlencode($ruleId);
        $method = 'DELETE';
    } else {
        if ($ruleId === '') {
            return ['ok' => false, 'error' => 'A rule has to be chosen.'];
        }
        if ($pos < 1 || $pos > 500) {
            return ['ok' => false, 'error' => 'The position has to be between 1 and 500.'];
        }
        $path = $base . '/' . rawurlencode($ruleId) . '/pos';
        $body = ['pos' => $pos, 'position' => $pos];
    }

    $answer = rainyun_request($method, $path, $body === [] ? null : $body, $key);
    if (!$answer['ok']) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'firewall_' . $action, 'error', $answer['error']);

        return ['ok' => false, 'error' => $answer['error']];
    }

    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'firewall_' . $action, 'ok',
        $ruleId === '' ? json_encode($body, JSON_UNESCAPED_UNICODE) : 'rule ' . $ruleId
            . ($action === 'move' ? ' -> ' . $pos : ''));

    return ['ok' => true, 'error' => ''];
}

/** Asks the provider to refresh the firewall from the instance again. */
function rainyun_firewall_sync_time(array $binding): array
{
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'error' => t('val.srv_no_key')];
    }

    $path = '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/firewall/sync_time';
    $answer = rainyun_request('POST', $path, null, $key);
    if (!$answer['ok']) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'firewall_sync', 'error', $answer['error']);

        return ['ok' => false, 'error' => $answer['error']];
    }
    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'firewall_sync', 'ok', '');

    return ['ok' => true, 'error' => ''];
}

/* ------------------------------------------------------------------ *
 * Usage, maintenance and VNC                                          *
 * ------------------------------------------------------------------ */

/**
 * The current load of one instance.
 *
 * The API answers with a time series whose shape is not documented; the numbers are therefore collected
 * defensively - anything that looks like a percentage or a byte count is shown under its own key, and the
 * whole answer is kept so the operator can look at it.
 *
 * @return array{values: array<string, string>, raw: string}
 */
function rainyun_usage(array $binding): array
{
    $out = ['values' => [], 'raw' => ''];
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return $out;
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/usage', null, $key);
    if (!$answer['ok']) {
        $out['raw'] = $answer['error'];

        return $out;
    }

    $wanted = [
        'cpu' => ['cpu', 'cpu_usage', 'cpu_percent', 'cpu_used'],
        'memory' => ['memory', 'mem', 'memory_usage', 'mem_usage', 'memory_percent'],
        'disk' => ['disk', 'disk_usage', 'disk_percent', 'space_usage'],
        'bandwidth' => ['bandwidth', 'bw', 'net', 'network', 'net_usage', 'flow'],
        'traffic_in' => ['traffic_in', 'net_in', 'rx', 'bytes_in', 'in'],
        'traffic_out' => ['traffic_out', 'net_out', 'tx', 'bytes_out', 'out'],
        'uptime' => ['uptime', 'run_time', 'online_time'],
    ];

    // Values may sit at the top level or inside a "data"/"result" wrapper, and inside the newest point of
    // a series; all three shapes are tried.
    $candidates = [$answer['data']];
    foreach (['data', 'result'] as $wrap) {
        if (isset($answer['data'][$wrap]) && is_array($answer['data'][$wrap])) {
            $candidates[] = $answer['data'][$wrap];
        }
    }
    $rows = rainyun_rows($answer['data']);
    if ($rows !== []) {
        $candidates[] = end($rows) ?: [];
    }

    foreach ($wanted as $label => $names) {
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $value = rainyun_pick($candidate, $names);
            if ($value !== '') {
                $out['values'][$label] = $value;
                break;
            }
        }
    }

    // Anything else that is a plain number is shown as well, so nothing useful stays hidden.
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        foreach ($candidate as $name => $value) {
            if (!is_scalar($value) || !is_numeric($value)) {
                continue;
            }
            $name = strtolower((string)$name);
            if (isset($out['values'][$name]) || strlen($name) > 24) {
                continue;
            }
            $out['values'][$name] = (string)$value;
        }
    }

    $out['raw'] = substr((string)json_encode($answer['data'], JSON_UNESCAPED_UNICODE), 0, 1200);

    return $out;
}

/**
 * Maintenance windows the provider announced for the instance.
 *
 * @return array{state: string, items: array<int, array{time: string, remark: string}>, raw: string}
 */
function rainyun_maintenance(array $binding): array
{
    $out = ['state' => '', 'items' => [], 'raw' => ''];
    $key = rainyun_key_of($binding);
    if ($key === '') {
        return $out;
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/maintenance', null, $key);
    if (!$answer['ok']) {
        $out['raw'] = $answer['error'];

        return $out;
    }

    $out['state'] = rainyun_pick($answer['data'], ['status', 'state', 'maintenance', 'maintenance_status']);
    foreach (rainyun_rows($answer['data']) as $row) {
        $time = rainyun_pick($row, ['time', 'start_time', 'start_at', 'created_at', 'date']);
        $stamp = $time === '' ? false : (is_numeric($time) ? (int)$time : strtotime($time));
        $out['items'][] = [
            'time'   => $stamp === false ? $time : date('Y-m-d H:i', $stamp),
            'remark' => rainyun_pick($row, ['remark', 'title', 'content', 'note', 'description', 'status']),
        ];
    }
    $out['raw'] = substr((string)json_encode($answer['data'], JSON_UNESCAPED_UNICODE), 0, 800);

    return $out;
}

/**
 * A temporary VNC ticket.
 *
 * The ticket is handed to the holder once and never stored: it expires on its own, and keeping it would
 * mean holding a live door into somebody's server. The request itself is written to the audit log.
 *
 * @return array{ok: bool, url: string, error: string}
 */
function rainyun_vnc(array $binding): array
{
    if (!setting_bool('rainyun_allow_vnc', true)) {
        return ['ok' => false, 'url' => '', 'error' => t('val.srv_vnc_off')];
    }

    $key = rainyun_key_of($binding);
    if ($key === '') {
        return ['ok' => false, 'url' => '', 'error' => t('val.srv_no_key')];
    }

    $answer = rainyun_request('GET', '/product/rcs/' . rawurlencode((string)$binding['remote_id']) . '/vnc', null, $key);
    if (!$answer['ok']) {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'vnc', 'error', $answer['error']);

        return ['ok' => false, 'url' => '', 'error' => $answer['error']];
    }

    $url = rainyun_pick($answer['data'], ['url', 'vnc_url', 'link', 'address', 'host']);
    if ($url === '') {
        $rows = rainyun_rows($answer['data']);
        $url  = rainyun_pick($rows[0] ?? [], ['url', 'vnc_url', 'link', 'address', 'host']);
    }
    if ($url === '') {
        rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'vnc', 'error',
            'the answer carried no address');

        return ['ok' => false, 'url' => '', 'error' => t('val.srv_vnc_none')];
    }

    rainyun_audit((int)$binding['id'], (int)$binding['user_id'], 'vnc', 'ok', 'ticket handed out');

    return ['ok' => true, 'url' => $url, 'error' => ''];
}
