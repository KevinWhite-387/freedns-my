<?php
declare(strict_types=1);

/**
 * freedns.my - talking to BillionMail, from the server itself.
 *
 * The registry keeps mailboxes on the self-hosted BillionMail instance. Nothing here talks to the internet:
 * the call goes to 127.0.0.1 (the host these containers publish their ports on), which means no public DNS,
 * no proxy and no certificate trouble - the certificate of that panel does not cover 127.0.0.1, so checking
 * it is switched off on purpose.
 *
 * Because the exact shape of BillionMail's API is not documented to us, every path and every field name is a
 * SETTING with a sensible default. If a call comes back with "not found" or "invalid parameter", the path is
 * corrected in the back office - no code change, no new upload. The test button shows the raw answer, so the
 * real shape can be read from the server itself.
 *
 * Used by: the back office mailbox page (create, suspend, resize, reset the password).
 */

function bm_ready(): bool
{
    return trim((string)setting('bm_base', '')) !== '';
}

function bm_base(): string
{
    $base = trim((string)setting('bm_base', 'https://127.0.0.1:41443'));

    return rtrim($base, '/');
}

function bm_key(): string
{
    return trim((string)setting('bm_api_key', ''));
}

/** The path of one action, from the settings, with a default that is most likely right. */
function bm_path(string $action, array $replace = []): string
{
    // These defaults were confirmed against the panel itself: POST to /api/mailbox/create, /update and
    // /delete all answer 200, while /add, /edit, /password and the domain paths do not exist. Changing a
    // password therefore goes through /update as well.
    $defaults = [
        'create'   => '/api/mailbox/create',
        'delete'   => '/api/mailbox/delete',
        'update'   => '/api/mailbox/update',
        'password' => '/api/mailbox/update',
        'list'     => '/api/mailbox/list',
        'domains'  => '/api/domain/list',
        // A mailbox alone is not enough: Postfix only accepts mail for a domain the panel knows about,
        // otherwise it answers "454 Relay access denied" and the sender keeps retrying. So the domain of a
        // new mailbox is created first - see bm_mailbox_create(). Note the plural: "domains", not "domain"
        // (the singular paths answer 404, the plural one answers 200).
        'domain_create' => '/api/domains/create',
    ];
    $path = trim((string)setting('bm_path_' . $action, $defaults[$action] ?? ''));
    if ($path === '') {
        $path = $defaults[$action] ?? '';
    }
    foreach ($replace as $key => $value) {
        $path = str_replace('{' . $key . '}', rawurlencode((string)$value), $path);
    }

    return $path;
}

/**
 * One call to the panel.
 *
 * @return array{ok: bool, status: int, data: mixed, error: string, raw: string}
 */
function bm_request(string $method, string $path, ?array $body = null, int $timeout = 20): array
{
    $out = ['ok' => false, 'status' => 0, 'data' => null, 'error' => '', 'raw' => ''];
    if (!bm_ready()) {
        $out['error'] = 'The BillionMail address is not set (back office -> Email).';

        return $out;
    }
    if (!function_exists('curl_init')) {
        $out['error'] = 'The PHP curl extension is not available on this server.';

        return $out;
    }

    $url     = bm_base() . $path;
    $headers = ['Accept: application/json'];
    $key     = bm_key();
    if ($key !== '') {
        // Both spellings are sent: the panel accepts one of them, an extra header does no harm.
        $headers[] = 'Authorization: Bearer ' . $key;
        $headers[] = 'X-API-Key: ' . $key;
    }

    $handle = curl_init();
    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,   // the panel's certificate does not cover 127.0.0.1
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($handle, $options);

    $raw    = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $err    = curl_error($handle);
    curl_close($handle);

    $out['status'] = $status;
    $out['raw']    = is_string($raw) ? substr($raw, 0, 4000) : '';

    if ($raw === false) {
        $out['error'] = $err !== '' ? $err : 'the connection failed';

        return $out;
    }

    $decoded = json_decode((string)$raw, true);
    $out['data'] = $decoded;

    if ($status >= 200 && $status < 300) {
        /*
         * A 200 is not proof of success: this panel answers 200 with a body that says what really happened
         * ({"code":..., "msg":...} or {"success":false}). Reading only the status code made a refused call look
         * like a successful one, which is exactly how "it says success but nothing was added" happens.
         */
        if (is_array($decoded)) {
            $code = $decoded['code'] ?? $decoded['status'] ?? null;
            $flag = $decoded['success'] ?? $decoded['ok'] ?? null;
            $good = true;
            if (is_numeric($code)) {
                $good = in_array((int)$code, [0, 200, 201], true);
            } elseif ($flag === false) {
                $good = false;
            }
            if (!$good) {
                $message = '';
                foreach (['msg', 'message', 'error', 'detail'] as $field) {
                    if (isset($decoded[$field]) && is_string($decoded[$field]) && $decoded[$field] !== '') {
                        $message = $decoded[$field];
                        break;
                    }
                }
                $out['error'] = $message !== '' ? $message : ('the panel answered code ' . (string)$code);

                return $out;   // ok stays false: the caller must not believe it worked
            }
        }
        $out['ok'] = true;

        return $out;
    }

    // The panel's own words are the most useful thing to show.
    $message = '';
    if (is_array($decoded)) {
        foreach (['msg', 'message', 'error', 'detail', 'data'] as $field) {
            if (isset($decoded[$field]) && is_string($decoded[$field]) && $decoded[$field] !== '') {
                $message = $decoded[$field];
                break;
            }
        }
    }
    $out['error'] = 'HTTP ' . $status . ($message !== '' ? ': ' . $message : '');

    return $out;
}

/* ------------------------------------------------------------------ *
 * The actions the registry needs                                      *
 * ------------------------------------------------------------------ */

/**
 * The panel's own database, used when its API cannot do the job.
 *
 * The API endpoint for adding a domain (version 4.9.5) ignores the fields it is given, builds the row from its
 * own defaults, and writes the string "null" into a PostgreSQL ARRAY column - which makes the insert fail with
 * "malformed array literal". Adding the row ourselves is what the panel's own screen does, and it works: the
 * only trick is sending a REAL empty array literal ('{}') for that column.
 */
function bm_pg_settings(): array
{
    return [
        'host' => trim((string)setting('bm_pg_host', '127.0.0.1')),
        'port' => max(1, min(65535, (int)setting('bm_pg_port', '25432'))),
        'db'   => trim((string)setting('bm_pg_db', '')),
        'user' => trim((string)setting('bm_pg_user', '')),
        'pass' => (string)setting('bm_pg_pass', ''),
    ];
}

function bm_pg_ready(): bool
{
    $config = bm_pg_settings();

    return $config['db'] !== '' && $config['user'] !== ''
        && in_array('pgsql', PDO::getAvailableDrivers(), true);
}

/** Which way to register a domain: api | db | auto (api first, then the database). */
function bm_domain_route(): string
{
    $route = strtolower(trim((string)setting('bm_domain_route', 'auto')));

    return in_array($route, ['api', 'db', 'auto'], true) ? $route : 'auto';
}

/**
 * Adds the domain row straight into the panel's database.
 *
 * @return array{ok: bool, error: string, raw: string}
 */
function bm_domain_create_db(string $domain, int $sizeMb = 1024): array
{
    $out = ['ok' => false, 'error' => '', 'raw' => ''];
    if (!bm_pg_ready()) {
        $out['error'] = 'The panel database is not configured, or the pdo_pgsql extension is missing '
            . '(drivers: ' . implode(', ', PDO::getAvailableDrivers()) . ').';

        return $out;
    }

    $config       = bm_pg_settings();
    $perMailboxKb = max(64, $sizeMb) * 1024;

    try {
        $pdo = new PDO(
            'pgsql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['db'],
            $config['user'],
            $config['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
        );

        // Already there? Then there is nothing to do, and that counts as success.
        $exists = $pdo->prepare('SELECT 1 FROM domain WHERE domain = ? LIMIT 1');
        $exists->execute([$domain]);
        if ($exists->fetchColumn() !== false) {
            $out['ok']    = true;
            $out['raw']   = 'the domain was already in the panel database';
            $out['error'] = '';

            return $out;
        }

        // "urls" must be a real array literal - '{}' - never the string 'null'.
        $insert = $pdo->prepare(
            'INSERT INTO domain (domain, a_record, mailboxes, mailbox_quota, quota, rate_limit, create_time,
                active, urls, hasbrandinfo, current_usage)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING domain'
        );
        $insert->execute([
            $domain,
            $domain,
            50,
            $perMailboxKb,
            $perMailboxKb * 10,
            12,
            time(),
            1,
            '{}',
            0,
            0,
        ]);
        $out['ok']  = true;
        $out['raw'] = 'inserted ' . (string)$insert->fetchColumn();
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

/**
 * Makes the panel accept mail for one domain, by the route the settings choose.
 *
 * @return array{ok: bool, error: string, raw: string}
 */
function bm_domain_create(string $domain, int $sizeMb = 1024): array
{
    $route = bm_domain_route();

    if ($route === 'db') {
        return bm_domain_create_db($domain, $sizeMb);
    }

    $perMailboxKb = max(64, $sizeMb) * 1024;              // the panel counts in KB
    $answer = bm_request('POST', bm_path('domain_create'), [
        'domain'        => $domain,
        'a_record'      => $domain,                       // the panel points the name at itself
        'mailboxes'     => 50,                            // how many mailboxes this domain may hold
        'mailbox_quota' => $perMailboxKb,                 // per mailbox
        'quota'         => $perMailboxKb * 10,            // for the domain as a whole
        'rate_limit'    => 12,
        'active'        => 1,
        'status'        => 1,
        'urls'          => [],                            // a real (empty) array, never the string "null"
        'hasbrandinfo'  => 0,
        'current_usage' => 0,
        'create_time'   => time(),
    ]);
    if ($answer['ok']) {
        return ['ok' => true, 'error' => '', 'raw' => $answer['raw']];
    }

    if ($route === 'auto') {
        // The endpoint of this version cannot do it (it ignores the fields and breaks on the array column),
        // so fall back to writing the row the way the panel's own screen does.
        $direct = bm_domain_create_db($domain, $sizeMb);
        if ($direct['ok']) {
            return ['ok' => true, 'error' => '', 'raw' => $direct['raw']
                . "\n(the API refused it: " . $answer['error'] . ')'];
        }

        return ['ok' => false, 'error' => $answer['error'] . ' | database: ' . $direct['error'],
                'raw' => $answer['raw']];
    }

    return ['ok' => $answer['ok'], 'error' => $answer['error'], 'raw' => $answer['raw']];
}

/**
 * Creates one mailbox.
 *
 * @return array{ok: bool, error: string, raw: string}
 */
function bm_mailbox_create(string $address, string $password, int $sizeMb): array
{
    $at = strrpos($address, '@');
    if ($at === false) {
        return ['ok' => false, 'error' => 'That is not an address.', 'raw' => ''];
    }
    $local  = substr($address, 0, $at);
    $domain = substr($address, $at + 1);

    // The domain first: without it the panel refuses incoming mail for this address.
    $domainAnswer = bm_domain_create($domain);

    // Field names differ between panels, so every plausible spelling is sent at once. A panel ignores the
    // ones it does not know, which makes this work without knowing the exact schema.
    $answer = bm_request('POST', bm_path('create'), [
        'domain'     => $domain,
        'domain_name' => $domain,
        'mailbox'    => $local,
        'username'   => $local,
        'user'       => $local,
        'local_part' => $local,
        'password'   => $password,
        'passwd'     => $password,
        'quota'      => $sizeMb,
        'quota_mb'   => $sizeMb,
        'size'       => $sizeMb,
        'address'    => $address,
        'email'      => $address,
    ]);

    return ['ok' => $answer['ok'], 'error' => $answer['error'], 'raw' => $answer['raw']];
}

/** Suspends or wakes a mailbox (state: 1 = active, 0 = suspended when the panel uses that convention). */
function bm_mailbox_state(string $address, bool $active, int $sizeMb): array
{
    $answer = bm_request('POST', bm_path('update'), [
        'address'  => $address,
        'email'    => $address,
        'active'   => $active ? 1 : 0,
        'status'   => $active ? 1 : 0,
        'enabled'  => $active ? 1 : 0,
        'quota'    => $sizeMb,
        'quota_mb' => $sizeMb,
    ]);

    return ['ok' => $answer['ok'], 'error' => $answer['error'], 'raw' => $answer['raw']];
}

function bm_mailbox_password(string $address, string $password): array
{
    $answer = bm_request('POST', bm_path('password'), [
        'address'  => $address,
        'email'    => $address,
        'password' => $password,
        'passwd'   => $password,
    ]);

    return ['ok' => $answer['ok'], 'error' => $answer['error'], 'raw' => $answer['raw']];
}

function bm_mailbox_delete(string $address): array
{
    $answer = bm_request('POST', bm_path('delete'), ['address' => $address, 'email' => $address]);

    return ['ok' => $answer['ok'], 'error' => $answer['error'], 'raw' => $answer['raw']];
}

/**
 * The test the back office runs: it asks the panel for something harmless and reports the raw answer.
 *
 * This is also how the real shape of the API is found out - the answer (or the error) is shown word for
 * word, so the paths in the settings can be corrected instead of guessed.
 *
 * @return array{ok: bool, status: int, error: string, raw: string, tried: array<int, string>}
 */
function bm_selftest(): array
{
    $out = ['ok' => false, 'status' => 0, 'error' => '', 'raw' => '', 'tried' => []];
    if (!bm_ready()) {
        $out['error'] = 'The BillionMail address is not set.';

        return $out;
    }

    // The documentation the panel serves is the best thing to look at first.
    foreach (['/api.json', '/swagger/api.json', '/api/docs.json'] as $doc) {
        $out['tried'][] = 'GET ' . $doc;
        $answer = bm_request('GET', $doc, null, 12);
        if ($answer['ok'] && is_array($answer['data'])) {
            $paths = [];
            foreach (['paths', 'routes', 'endpoints'] as $section) {
                if (isset($answer['data'][$section]) && is_array($answer['data'][$section])) {
                    foreach ($answer['data'][$section] as $path => $info) {
                        $methods = is_array($info) ? implode('/', array_map('strtoupper',
                            array_filter(array_keys($info), static fn($k) => is_string($k)))) : '';
                        $paths[] = trim($methods . ' ' . $path);
                    }
                }
            }
            $out['ok']    = true;
            $out['status'] = $answer['status'];
            $out['raw']   = $paths === []
                ? $answer['raw']
                : implode("\n", array_slice($paths, 0, 200));

            return $out;
        }
        $out['error'] = $answer['error'];
        $out['raw']   = $answer['raw'];
    }

    // No documentation reachable: at least prove that the panel answers and that the key is accepted.
    foreach (['list', 'domains'] as $action) {
        $path = bm_path($action);
        $out['tried'][] = 'GET ' . $path;
        $answer = bm_request('GET', $path, null, 12);
        $out['status'] = $answer['status'];
        $out['raw']    = $answer['raw'];
        if ($answer['ok']) {
            $out['ok'] = true;

            return $out;
        }
        $out['error'] = $answer['error'];
    }

    return $out;
}
