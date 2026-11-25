<?php
declare(strict_types=1);

/**
 * freedns.my - signing in and linking with GitHub (OAuth 2.0).
 *
 * Only the user console uses it; the back office keeps its own accounts, its hidden entry and its pass
 * phrase. The flow is plain redirects - no JavaScript - and asks for `read:user user:email` only, so
 * nothing in the visitor's repositories is ever touched. The access token is used once to read the
 * profile and is then dropped; only the numeric account id and the login name are stored.
 *
 * Linking rules:
 *   * signed in  -> the GitHub account is attached to the account that is signed in;
 *   * not signed in but the id is known -> sign that account in;
 *   * not signed in, id unknown, but GitHub reports a VERIFIED e-mail that matches an account ->
 *     attach and sign in (the e-mail is proof enough on both sides);
 *   * otherwise -> create a fresh account (user name from GitHub, e-mail as reported, active).
 */

const FD_GITHUB_SESSION = 'fd_github';
const FD_GITHUB_RETURN = 'fd_github_return';
const FD_GITHUB_API = 'https://api.github.com';
const FD_GITHUB_SCOPE = 'read:user user:email';

function github_enabled(): bool
{
    return setting_bool('github_enabled', false)
        && github_client_id() !== ''
        && github_client_secret() !== '';
}

function github_client_id(): string
{
    return trim((string)setting('github_client_id', ''));
}

function github_client_secret(): string
{
    return trim((string)setting('github_client_secret', ''));
}

/**
 * The one address that has to be pasted into the GitHub OAuth App.
 *
 * Always the canonical (English) path: GitHub compares the redirect_uri byte for byte, so a Chinese
 * visitor coming from /zh/account/github.php must still be sent back to /account/github.php, which is
 * what is registered there.
 */
function github_callback_url(): string
{
    return lang_url('en', 'account/github.php');
}

/** True when the users table carries the GitHub columns. */
function github_columns_ready(): bool
{
    if (isset($GLOBALS['FD_GITHUB_COLUMNS'])) {
        return (bool)$GLOBALS['FD_GITHUB_COLUMNS'];
    }

    $ready = false;
    try {
        $ready = users_table_ready() && db_one("SHOW COLUMNS FROM users LIKE 'github_id'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_GITHUB_COLUMNS'] = $ready;

    return $ready;
}

function github_available(): bool
{
    return github_enabled() && github_columns_ready();
}

/* ------------------------------------------------------------------ *
 * The OAuth flow                                                      *
 * ------------------------------------------------------------------ */

function github_new_state(): string
{
    fd_start_session();
    $state = bin2hex(random_bytes(16));
    $_SESSION[FD_GITHUB_SESSION] = ['state' => $state, 'at' => time()];

    return $state;
}

function github_state_ok(string $state): bool
{
    // The bootstrap starts no session: open the existing one (and only an existing one, so visitors
    // without a session cookie still get no cookie) before looking the security token up.
    if (!user_session_available()) {
        return false;
    }

    $saved = $_SESSION[FD_GITHUB_SESSION] ?? null;
    if (!is_array($saved) || empty($saved['state'])) {
        return false;
    }
    unset($_SESSION[FD_GITHUB_SESSION]);
    if ((time() - (int)($saved['at'] ?? 0)) > 900) {
        return false;
    }

    return hash_equals((string)$saved['state'], $state);
}

/** "Sign in with GitHub" / "Link your GitHub account": the return address is kept in the session. */
function github_start_url(string $returnTo = ''): string
{
    fd_start_session();
    $state = github_new_state();
    if ($returnTo !== '') {
        $_SESSION[FD_GITHUB_RETURN] = safe_relative_url($returnTo, url('account/index.php'));
    }

    return 'https://github.com/login/oauth/authorize?'
        . http_build_query([
            'client_id'    => github_client_id(),
            'redirect_uri' => github_callback_url(),
            'scope'        => FD_GITHUB_SCOPE,
            'state'        => $state,
            'allow_signup' => 'true',
        ]);
}

/**
 * The address to return to after a successful sign-in or linking - the page the visitor started from
 * (so a Chinese visitor lands back on /zh/account/...). Read once, then forgotten.
 */
function github_return_target(): string
{
    $target = '';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $target = (string)($_SESSION[FD_GITHUB_RETURN] ?? '');
        unset($_SESSION[FD_GITHUB_RETURN]);
    }

    return safe_relative_url($target, url('account/index.php'));
}

/** One call against the GitHub API. */
function github_api(string $path, string $token): array
{
    $url     = str_starts_with($path, 'http') ? $path : FD_GITHUB_API . $path;
    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: freedns.my',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    // Without a token GitHub refuses a bare "Authorization: Bearer" header, so it is only sent when
    // there really is one (public data needs no token at all).
    if (trim($token) !== '') {
        $headers[] = 'Authorization: Bearer ' . trim($token);
    }

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($handle);
        $code = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $err  = $body === false ? (string)curl_error($handle) : '';
        curl_close($handle);
        if ($body === false) {
            return ['ok' => false, 'status' => $code, 'data' => [], 'error' => 'GitHub: ' . $err];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", $headers),
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $code = 0;
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'GitHub could not be reached.'];
        }
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match)) {
                $code = (int)$match[1];
            }
        }
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'status' => $code, 'data' => [], 'error' => 'GitHub answered something that is not JSON.'];
    }
    if ($code >= 400) {
        return [
            'ok'     => false,
            'status' => $code,
            'data'   => $data,
            'error'  => 'GitHub API: ' . (string)($data['message'] ?? ('HTTP ' . $code)),
        ];
    }

    return ['ok' => true, 'status' => $code, 'data' => $data, 'error' => ''];
}

/** Exchanges the temporary code for an access token. */
function github_exchange_code(string $code): array
{
    $payload = http_build_query([
        'client_id'     => github_client_id(),
        'client_secret' => github_client_secret(),
        'code'          => $code,
        'redirect_uri'  => github_callback_url(),
    ]);

    $headers = ['Accept: application/json', 'User-Agent: freedns.my'];
    if (function_exists('curl_init')) {
        $handle = curl_init('https://github.com/login/oauth/access_token');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($handle);
        $err  = $body === false ? (string)curl_error($handle) : '';
        curl_close($handle);
        if ($body === false) {
            return ['ok' => false, 'token' => '', 'error' => 'GitHub: ' . $err];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", array_merge($headers, ['Content-Type: application/x-www-form-urlencoded'])),
                'content'       => $payload,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents('https://github.com/login/oauth/access_token', false, $context);
        if ($body === false) {
            return ['ok' => false, 'token' => '', 'error' => 'GitHub could not be reached.'];
        }
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        $message = is_array($data)
            ? (string)($data['error_description'] ?? $data['error'] ?? 'no access token')
            : 'the answer could not be read';

        return ['ok' => false, 'token' => '', 'error' => 'GitHub refused the sign-in: ' . $message];
    }

    return ['ok' => true, 'token' => (string)$data['access_token'], 'error' => ''];
}

/** The verified primary e-mail of the GitHub account, or '' when there is none. */
function github_verified_email(array $emails): string
{
    $fallback = '';
    foreach ($emails as $entry) {
        if (empty($entry['verified'])) {
            continue;
        }
        $address = (string)($entry['email'] ?? '');
        if ($address === '' || !valid_email_addr($address)) {
            continue;
        }
        if (!empty($entry['primary'])) {
            return $address;
        }
        if ($fallback === '') {
            $fallback = $address;
        }
    }

    return $fallback;
}

/* ------------------------------------------------------------------ *
 * Accounts                                                            *
 * ------------------------------------------------------------------ */

function user_by_github(string $githubId): ?array
{
    if (!github_columns_ready() || $githubId === '') {
        return null;
    }

    return db_one('SELECT * FROM users WHERE github_id = ?', [$githubId]);
}

function github_link_user(int $userId, string $githubId, string $login): bool
{
    if (!github_columns_ready()) {
        return false;
    }

    try {
        db_query(
            'UPDATE users SET github_id = ?, github_login = ?, github_linked_at = NOW() WHERE id = ?',
            [$githubId, $login, $userId]
        );
    } catch (Throwable $e) {
        // The unique key fires when that GitHub account already belongs to somebody else.
        return false;
    }

    log_action('github_linked', 'user', $userId, 'GitHub ' . $login . ' (id ' . $githubId . ')');

    return true;
}

function github_unlink_user(int $userId, string $login): void
{
    if (!github_columns_ready()) {
        return;
    }

    db_query(
        'UPDATE users SET github_id = NULL, github_login = NULL, github_linked_at = NULL WHERE id = ?',
        [$userId]
    );
    log_action('github_unlinked', 'user', $userId, 'GitHub ' . $login . ' removed');
}

/** A free user name derived from the GitHub login. */
function github_username(string $login): string
{
    $base = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', $login) ?? '');
    $base = trim($base, '-_');
    if (strlen($base) < 3) {
        $base = 'user' . ($base === '' ? '' : '-' . $base);
    }
    $base = substr($base, 0, 28);
    if (in_array(strtolower($base), reserved_usernames(), true)) {
        $base .= '-gh';
    }

    $candidate = $base;
    $suffix    = 2;
    while (db_one('SELECT id FROM users WHERE username_lower = ?', [strtolower($candidate)]) !== null) {
        $candidate = substr($base, 0, 28) . '-' . $suffix;
        $suffix++;
        if ($suffix > 99) {
            $candidate = $base . '-' . bin2hex(random_bytes(3));
            break;
        }
    }

    return $candidate;
}

/** Creates a console account for a fresh GitHub visitor. */
function github_create_user(string $githubId, string $login, string $email, string $language): array
{
    /*
     * Maintenance closes what CREATES something and leaves everything else alone - so this one place, and only
     * this one, has to refuse. Signing in with GitHub still works while maintenance is on (the account already
     * exists, nothing new is made); opening a brand new account does not. The request-level guard in
     * inc/maintenance.php cannot do this: it only sees the script name, and account/github.php serves both the
     * sign-in and the sign-up, so the decision is only knowable here, after GitHub has been asked who the person
     * is. Refusing here is what stops "I can still register during maintenance" from being true.
     */
    if (function_exists('maintenance_active') && maintenance_active()) {
        // Show the same notice the rest of the site shows, in the visitor's language, and stop here. A new error
        // code would have needed a line in all six catalogues for one sentence.
        if (function_exists('maintenance_page')) {
            maintenance_page();
            exit;
        }

        return ['ok' => false, 'error' => 'val.github_off', 'user' => null, 'maintenance' => true];
    }

    if (!github_columns_ready()) {
        return ['ok' => false, 'error' => 'val.user_no_table', 'user' => null];
    }
    if (db_one('SELECT id FROM users WHERE email_lower = ?', [strtolower($email)]) !== null) {
        return ['ok' => false, 'error' => 'val.user_email_taken', 'user' => null];
    }

    $username = github_username($login);

    db_query(
        'INSERT INTO users (username, username_lower, email, email_lower, password_hash, has_password,
            github_id, github_login, github_linked_at, status, language, created_at, created_ip)
         VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW(), \'active\', ?, NOW(), ?)',
        [
            $username,
            strtolower($username),
            $email,
            strtolower($email),
            // No usable local password until the owner sets one in the profile.
            password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            $githubId,
            $login,
            $language === '' ? 'en' : $language,
            client_ip(),
        ]
    );

    $user = db_one('SELECT * FROM users WHERE github_id = ?', [$githubId]);
    if ($user !== null) {
        log_action('github_signup', 'user', (int)$user['id'], $username . ' <' . $email . '> via GitHub ' . $login);
    }

    return ['ok' => true, 'error' => '', 'user' => $user];
}

/** Signs a console account in (the same state the password sign-in produces). */
function user_sign_in(array $user): void
{
    fd_start_session();
    session_regenerate_id(true);
    $_SESSION[FD_USER_SESSION] = (int)$user['id'];
    db_query(
        'UPDATE users SET last_login_at = NOW(), last_login_ip = ?, language = ? WHERE id = ?',
        [client_ip(), lang(), (int)$user['id']]
    );
    unset($GLOBALS['FD_CURRENT_USER']);
    log_action('user_signed_in', 'user', (int)$user['id'], (string)$user['username'] . ' via GitHub from ' . client_ip());
}

/** True when this account still has a local password. */
function user_has_password(array $user): bool
{
    return (int)($user['has_password'] ?? 1) === 1;
}

/* ------------------------------------------------------------------ *
 * Star bonus                                                          *
 *                                                                     *
 * GitHub has no "did this user star my repository" endpoint, so the   *
 * stargazer list is fetched and compared with the account's GitHub id *
 * - which is why an account has to be linked before it can claim the  *
 * bonus. The list is cached and the call may use a token, because an  *
 * unauthenticated address gets only 60 requests an hour.              *
 * ------------------------------------------------------------------ */

/** True when the users table carries the bonus columns. */
function star_bonus_columns_ready(): bool
{
    if (isset($GLOBALS['FD_STAR_COLS'])) {
        return (bool)$GLOBALS['FD_STAR_COLS'];
    }

    $ready = false;
    try {
        $ready = users_table_ready() && db_one("SHOW COLUMNS FROM users LIKE 'quota_bonus'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_STAR_COLS'] = $ready;

    return $ready;
}

function star_bonus_repo(): string
{
    $repo = trim((string)setting('star_repo', ''));

    return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) === 1 ? $repo : '';
}

function star_bonus_amount(): int
{
    return max(1, min(100, (int)setting('star_bonus_amount', '1')));
}

function star_bonus_enabled(): bool
{
    return setting_bool('star_bonus_enabled', false) && star_bonus_repo() !== '' && star_bonus_columns_ready();
}

function star_bonus_url(): string
{
    $repo = star_bonus_repo();

    return $repo === '' ? '' : 'https://github.com/' . $repo;
}

/** True when that account already collected the bonus. */
function star_bonus_taken(array $user): bool
{
    return trim((string)($user['star_bonus_at'] ?? '')) !== '';
}

/**
 * Turns a GitHub API failure into something the operator can act on. The status alone is not enough:
 * "Resource not accessible by personal access token" is a 403 as well, but has nothing to do with rate
 * limits.
 */
function github_error_hint(int $status, string $message): string
{
    $lower = strtolower($message);

    if (str_contains($lower, 'resource not accessible')) {
        return ' The token cannot see this repository. With a fine-grained token, set "Repository access" to '
            . '"All public repositories" - or tick this repository under "Only select repositories" - and '
            . 'make sure the "Resource owner" is the account or organisation that owns it. A classic token '
            . 'with no scopes checked works for every public repository and is the simplest fix.';
    }
    if (str_contains($lower, 'rate limit') || $status === 429) {
        return ' GitHub rate limits this: 60 anonymous requests an hour for the whole server, so a read-only '
            . 'token is worth storing.';
    }
    if ($status === 401 || str_contains($lower, 'bad credentials')) {
        return ' The stored token was rejected - it may have expired or been revoked. Create a new one and '
            . 'paste it again.';
    }
    if ($status === 403) {
        return ' GitHub refused the request. Check the token: a classic token needs no scopes for public '
            . 'data, a fine-grained one needs the right resource owner and repository access.';
    }

    return '';
}

/**
 * The stargazers of the configured repository.
 *
 * @return array{ok: bool, error: string, ids: array<int, bool>, count: int, truncated: bool, fetched: string,
 *                token: bool, full_name: string, stars: int}
 */
function github_stargazers(bool $force = false): array
{
    $repo = star_bonus_repo();
    $fail = [
        'ok' => false, 'ids' => [], 'count' => 0, 'truncated' => false, 'fetched' => '',
        'token' => trim((string)setting('star_token', '')) !== '', 'full_name' => '', 'stars' => 0,
    ];

    if ($repo === '') {
        return $fail + ['error' => 'No repository is configured (Settings -> GitHub star bonus).'];
    }

    $minutes = max(0, (int)setting('star_cache_minutes', '10'));
    if (!$force && $minutes > 0) {
        $cachedAt = trim((string)setting('star_cache_at', ''));
        $stamp    = $cachedAt === '' ? false : strtotime($cachedAt);
        if ($stamp !== false && (time() - $stamp) < $minutes * 60) {
            $ids = json_decode((string)setting('star_cache_ids', ''), true);
            if (is_array($ids)) {
                $clean = [];
                foreach ($ids as $id) {
                    $clean[(int)$id] = true;
                }

                return [
                    'ok' => true, 'error' => '', 'ids' => $clean, 'count' => count($clean),
                    'truncated' => trim((string)setting('star_cache_truncated', '')) === '1',
                    'fetched' => $cachedAt, 'token' => $fail['token'],
                    'full_name' => (string)setting('star_cache_repo', $repo),
                    'stars' => (int)setting('star_cache_stars', '0'),
                ];
            }
        }
    }

    $token = trim((string)setting('star_token', ''));

    /*
     * Ask for the repository itself first. A bare "Not Found" from the stargazer list could mean three
     * very different things, and this call tells them apart: a misspelled path, a private repository
     * (which nobody can star) or a repository the token cannot see.
     */
    $meta = github_api('/repos/' . $repo, $token);
    if (!$meta['ok']) {
        $status  = (int)$meta['status'];
        $message = (string)($meta['data']['message'] ?? $meta['error']);

        if ($status === 404) {
            return $fail + ['error' =>
                'GitHub answered "Not Found" for "' . $repo . '". That means one of three things: the path '
                . 'is misspelled, the repository is private (nobody can star a private repository, so it has '
                . 'to be public), or the token cannot see it. Open https://github.com/' . $repo
                . ' in a browser: if that page loads and shows a "Star" button, the path is right and the '
                . 'token is the problem.'];
        }

        return $fail + ['error' => $meta['error'] . github_error_hint($status, $message)];
    }

    $canonical = (string)($meta['data']['full_name'] ?? $repo);
    $stars     = (int)($meta['data']['stargazers_count'] ?? 0);
    $visibility = (string)($meta['data']['visibility'] ?? (!empty($meta['data']['private']) ? 'private' : 'public'));
    if (!empty($meta['data']['private']) || $visibility !== 'public') {
        return $fail + ['error' =>
            'The repository ' . $canonical . ' is ' . $visibility . ', and a private repository cannot be '
            . 'starred by anybody. Make it public (repository Settings -> General -> Danger zone -> Change '
            . 'repository visibility -> Public); it does not have to contain any source code, a README is '
            . 'enough.'];
    }

    $ids   = [];
    $page  = 1;
    $maxPages = 40;          // 4.000 stargazers; enough for a small registry, keeps a runaway list from
    $truncated = false;      // hammering the API.

    while ($page <= $maxPages) {
        $response = github_api(
            '/repos/' . $canonical . '/stargazers?per_page=100&page=' . $page,
            $token
        );

        if (!$response['ok']) {
            $status  = (int)$response['status'];
            $message = (string)($response['data']['message'] ?? $response['error']);

            return ['ok' => false, 'error' => $response['error'] . github_error_hint($status, $message),
                'ids' => [], 'count' => 0, 'truncated' => false, 'fetched' => '',
                'token' => $token !== '', 'full_name' => $canonical, 'stars' => $stars];
        }

        $rows = (array)$response['data'];
        if ($rows === []) {
            break;
        }

        foreach ($rows as $row) {
            $user = isset($row['user']) && is_array($row['user']) ? $row['user'] : $row;
            $id   = (int)($user['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        if (count($rows) < 100) {
            break;
        }
        $page++;
    }

    if ($page > $maxPages) {
        $truncated = true;
    }

    set_setting('star_cache_ids', json_encode(array_keys($ids)));
    set_setting('star_cache_at', date('Y-m-d H:i:s'));
    set_setting('star_cache_truncated', $truncated ? '1' : '0');
    set_setting('star_cache_repo', $canonical);
    set_setting('star_cache_stars', (string)$stars);

    return [
        'ok' => true, 'error' => '', 'ids' => $ids, 'count' => count($ids),
        'truncated' => $truncated, 'fetched' => (string)setting('star_cache_at', ''),
        'token' => $token !== '', 'full_name' => $canonical, 'stars' => $stars,
    ];
}

function star_bonus_forget_cache(): void
{
    set_setting('star_cache_ids', '');
    set_setting('star_cache_at', '');
    set_setting('star_cache_truncated', '');
}

/**
 * Grants the bonus when the account's GitHub id appears in the stargazer list.
 *
 * @return array{ok: bool, error: string, amount: int}
 */
function star_bonus_claim(array $user): array
{
    if (!setting_bool('star_bonus_enabled', false)) {
        return ['ok' => false, 'error' => 'val.star_off', 'amount' => 0];
    }
    if (!star_bonus_columns_ready()) {
        return ['ok' => false, 'error' => 'val.star_no_table', 'amount' => 0];
    }
    if (star_bonus_repo() === '') {
        return ['ok' => false, 'error' => 'val.star_no_repo', 'amount' => 0];
    }
    if (trim((string)($user['github_id'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'val.star_link_first', 'amount' => 0];
    }
    if (star_bonus_taken($user)) {
        return ['ok' => false, 'error' => 'val.star_already', 'amount' => 0];
    }

    $list = github_stargazers();
    if (!$list['ok']) {
        return ['ok' => false, 'error' => 'val.star_lookup', 'amount' => 0];
    }

    $id = (int)$user['github_id'];
    if (!isset($list['ids'][$id])) {
        return ['ok' => false, 'error' => 'val.star_not_found', 'amount' => 0];
    }

    $amount = star_bonus_amount();
    db_query(
        'UPDATE users SET quota_bonus = quota_bonus + ?, star_bonus_at = NOW(), star_bonus_repo = ?
         WHERE id = ?',
        [$amount, star_bonus_repo(), (int)$user['id']]
    );
    log_action(
        'star_bonus_awarded',
        'user',
        (int)$user['id'],
        (string)$user['username'] . ' +' . $amount . ' quota for starring ' . star_bonus_repo()
    );
    unset($GLOBALS['FD_CURRENT_USER']);

    return ['ok' => true, 'error' => '', 'amount' => $amount];
}
