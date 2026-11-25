<?php
declare(strict_types=1);

/**
 * freedns.my - the user API: tokens of a console account manage that account's own records.
 *
 * Answers are JSON. A token is shown once and stored as a hash, so a lost token is replaced, never
 * recovered. Everything the API does goes through the same validation and publishing path as the console.
 */

function api_table_ready(): bool
{
    if (isset($GLOBALS['FD_API_TABLE'])) {
        return (bool)$GLOBALS['FD_API_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'api\\_tokens'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_API_TABLE'] = $ready;

    return $ready;
}

function api_enabled(): bool
{
    return api_table_ready() && setting_bool('api_enabled', true);
}

function api_hash(string $token): string
{
    return hash('sha256', $token);
}

/* ------------------------------------------------------------------ *
 * Tokens                                                              *
 * ------------------------------------------------------------------ */

/** @return array{token: string, id: int} */
function api_token_create(int $userId, string $label): array
{
    $token = 'fd_' . bin2hex(random_bytes(20));
    db_query(
        'INSERT INTO api_tokens (user_id, label, token_hash, token_hint, created_at) VALUES (?, ?, ?, ?, NOW())',
        [$userId, $label === '' ? null : $label, api_hash($token), substr($token, -6)]
    );
    $id = (int)db()->lastInsertId();
    log_action('api_token_created', 'user', $userId, 'API token #' . $id . ' created');

    return ['token' => $token, 'id' => $id];
}

function api_tokens_of(int $userId): array
{
    if (!api_table_ready()) {
        return [];
    }

    return db_all('SELECT * FROM api_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]);
}

function api_token_revoke(int $userId, int $id): bool
{
    if (!api_table_ready()) {
        return false;
    }

    $row = db_one('SELECT id FROM api_tokens WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [$id, $userId]);
    if ($row === null) {
        return false;
    }
    db_query('UPDATE api_tokens SET revoked_at = NOW() WHERE id = ?', [$id]);
    log_action('api_token_revoked', 'user', $userId, 'API token #' . $id . ' revoked');

    return true;
}

/** The account behind a request, or null. */
function api_current_user(): ?array
{
    if (!api_enabled()) {
        return null;
    }

    $header = '';
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (!empty($_SERVER[$key])) {
            $header = (string)$_SERVER[$key];
            break;
        }
    }
    if ($header === '' && !empty($_SERVER['HTTP_X_API_KEY'])) {
        $header = 'Bearer ' . (string)$_SERVER['HTTP_X_API_KEY'];
    }
    if ($header === '' && isset($_REQUEST['token'])) {
        $header = 'Bearer ' . (string)$_REQUEST['token'];
    }
    if (stripos($header, 'bearer ') !== 0) {
        return null;
    }

    $token = trim(substr($header, 7));
    if ($token === '') {
        return null;
    }

    $row = db_one('SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1', [api_hash($token)]);
    if ($row === null) {
        return null;
    }

    db_query('UPDATE api_tokens SET calls = calls + 1, last_used_at = NOW(), last_ip = ? WHERE id = ?',
        [client_ip(), (int)$row['id']]);

    return db_one('SELECT * FROM users WHERE id = ?', [(int)$row['user_id']]);
}

/* ------------------------------------------------------------------ *
 * Answers                                                             *
 * ------------------------------------------------------------------ */

function api_send(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit;
}

function api_fail(string $code, string $message, int $status = 400): void
{
    api_send(['ok' => false, 'error' => $code, 'message' => $message], $status);
}

/** The request body, from JSON or from a normal form. */
function api_input(): array
{
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($type, 'json')) {
        $raw  = file_get_contents('php://input');
        $data = $raw === false ? null : json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    return $_POST;
}

/* ------------------------------------------------------------------ *
 * Resources                                                           *
 * ------------------------------------------------------------------ */

function api_domains_of(array $user): array
{
    $out = [];
    foreach (user_domains($user) as $domain) {
        $out[] = [
            'name'    => (string)$domain['name'],
            'status'  => (string)$domain['status'],
            'zone'    => (string)$domain['zone'],
            'proxied' => (int)($domain['proxied'] ?? 0) === 1,
            'delegated' => (int)($domain['delegated'] ?? 0) === 1,
            'records' => count(domain_records((int)$domain['id'])),
        ];
    }

    return $out;
}

function api_domain_of_user(array $user, string $name): ?array
{
    $name = strtolower(trim($name));
    if ($name === '') {
        return null;
    }
    foreach (user_domains($user) as $domain) {
        if (strtolower((string)$domain['name']) === $name) {
            return $domain;
        }
    }

    return null;
}

/** The records of a name in the shape the API speaks. */
function api_record_rows(array $domain): array
{
    $out = [];
    foreach (domain_records((int)$domain['id']) as $index => $row) {
        $out[] = [
            'index' => $index,
            'name'  => record_sub_display(normalize_record_sub((string)($row['name'] ?? ''),
                (string)$domain['name'])),
            'type'  => strtoupper((string)($row['type'] ?? '')),
            'value' => (string)($row['value'] ?? ''),
        ];
    }

    return $out;
}

/** Shorthand used by the examples and the console page. */
function api_example_curl(string $token, string $name): string
{
    $base = rtrim(app_base_url(), '/') . '/api.php';

    return 'curl -H "Authorization: Bearer ' . $token . '" "' . $base . '?action=records&domain=' . $name . '"';
}

/**
 * Writes a change and publishes it, exactly like the console does.
 *
 * @return array{ok: bool, error: string, message: string}
 */
function api_save_records(array $domain, array $rows): array
{
    if ((string)$domain['status'] !== 'active') {
        return ['ok' => false, 'error' => 'not_active', 'message' => 'The name is not active (yet).'];
    }
    if (ns_domain_delegated((int)$domain['id'])) {
        return ['ok' => false, 'error' => 'delegated',
            'message' => 'The name answers through its own name servers.'];
    }
    if (transfer_domain_locked((int)$domain['id'])) {
        return ['ok' => false, 'error' => 'locked', 'message' => 'A transfer is running for this name.'];
    }

    $proposed = [];
    foreach ($rows as $row) {
        $sub   = normalize_record_sub((string)($row['name'] ?? ''), (string)$domain['name']);
        $type  = strtoupper(trim((string)($row['type'] ?? '')));
        $value = normalize_record_value($type, (string)($row['value'] ?? ''));

        $error = record_validation_error($sub, $type, $value, (string)$domain['name']);
        if ($error !== '') {
            return ['ok' => false, 'error' => 'invalid_record', 'message' => $error];
        }
        $proposed[] = ['name' => $sub, 'type' => $type, 'value' => $value];
    }

    $problem = validate_record_set($proposed, (string)$domain['name']);
    if (is_string($problem) && $problem !== '') {
        return ['ok' => false, 'error' => 'invalid_set', 'message' => $problem];
    }

    replace_records((int)$domain['id'], $proposed);
    $published = publish_domain((int)$domain['id'], $domain);
    if (isset($published['ok']) && !$published['ok']) {
        return ['ok' => false, 'error' => 'publish_failed',
            'message' => (string)($published['error'] ?? 'publishing failed')];
    }

    log_action('api_records', 'domain', (int)$domain['id'],
        'API wrote ' . count($proposed) . ' record(s) for ' . (string)$domain['name']);

    return ['ok' => true, 'error' => '', 'message' => 'Saved and published.'];
}

/* ------------------------------------------------------------------ *
 * Maintenance ("down for maintenance")                                *
 * ------------------------------------------------------------------ */

/** Is the API deliberately taken down? An allow-listed address still gets through. */
function api_maint_on(): bool
{
    if (function_exists('setting_bool') === false || setting_bool('api_maint', false) === false) {
        return false;
    }

    $allow = trim((string)setting('api_maint_allow', ''));
    if ($allow !== '') {
        $ip = function_exists('client_ip') ? (string)client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        foreach (preg_split('/[\s,]+/', $allow) ?: [] as $one) {
            if ($one !== '' && $one === $ip) {
                return false;   // the operator keeps working while everybody else sees the notice
            }
        }
    }

    return true;
}

/** When it is expected back: free text, empty when unknown. */
function api_maint_until(): string
{
    return trim((string)setting('api_maint_until', ''));
}

/** What the caller is told while the API is down, in the reader's language. */
function api_maint_note(): string
{
    $note = trim((string)setting_lang('api_maint_note'));
    if ($note === '') {
        $note = is_zh()
            ? '接口正在维护，暂时无法使用。恢复后你这边不需要改任何设置，请稍后再试。'
            : 'The API is down for maintenance and is temporarily unavailable. Nothing needs changing on your '
              . 'side - please try again later.';
    }

    return $note;
}