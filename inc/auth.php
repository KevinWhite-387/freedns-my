<?php
declare(strict_types=1);

/**
 * freedns.my - administrator authentication and audit logging.
 */

function current_admin(): ?array
{
    if (!array_key_exists('FD_ADMIN', $GLOBALS)) {
        $GLOBALS['FD_ADMIN'] = null;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            fd_start_session();
        }
        $id = (int)($_SESSION['admin_id'] ?? 0);
        if ($id > 0) {
            try {
                $row = db_one('SELECT * FROM admins WHERE id = ?', [$id]);
            } catch (Throwable $e) {
                $row = null;
            }
            if ($row !== null) {
                $GLOBALS['FD_ADMIN'] = $row;
            } else {
                unset($_SESSION['admin_id']);
            }
        }
    }

    return $GLOBALS['FD_ADMIN'];
}

function is_admin(): bool
{
    return current_admin() !== null;
}

function require_admin(): array
{
    // The hidden entry is checked first: without it the whole directory answers 404.
    admin_gate_enforce();

    $admin = current_admin();
    if ($admin === null) {
        $_SESSION['after_login'] = safe_relative_url((string)($_SERVER['REQUEST_URI'] ?? ''), '/');
        flash('Please sign in to continue.', 'error');
        redirect(url('admin/login.php'));
    }

    // Optional second factor of the operator account (see inc/totp.php).
    if (totp_columns_ready() && totp_admin_on($admin) && !totp_session_passed('admin', (int)$admin['id'])) {
        totp_pending_start('admin', (int)$admin['id']);
        redirect(url('totp.php'));
    }

    return $admin;
}

function is_owner(): bool
{
    $admin = current_admin();

    return $admin !== null && (int)$admin['is_owner'] === 1;
}

/* ------------------------------------------------------------------ *
 * Roles and the scope of a reviewer                                   *
 *                                                                     *
 * owner    - full access.                                             *
 * reviewer - may decide requests and record changes for the suffixes   *
 *            assigned to it, read those names and suspend or delete    *
 *            them; every other page answers 403.                      *
 * ------------------------------------------------------------------ */

/** True when the admins table carries the "zones" column. */
function admin_zones_ready(): bool
{
    if (isset($GLOBALS['FD_ADMIN_ZONES_COL'])) {
        return (bool)$GLOBALS['FD_ADMIN_ZONES_COL'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM admins LIKE 'zones'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_ADMIN_ZONES_COL'] = $ready;

    return $ready;
}

/**
 * The suffixes the signed-in reviewer may handle. Unknown suffixes (a zone that was renamed or
 * deleted since) simply grant nothing. An owner gets every suffix.
 */
function admin_scope_zones(): array
{
    $admin = current_admin();
    if ($admin === null) {
        return [];
    }

    $allZones = [];
    foreach (zone_list() as $zone) {
        $allZones[] = strtolower((string)$zone['name']);
    }

    if ((int)$admin['is_owner'] === 1) {
        return $allZones;
    }

    // Without the column a reviewer has no scope at all: never widen an account silently, the
    // Administrators page then asks for upgrade-reviewer.sql.
    if (!admin_zones_ready()) {
        return [];
    }

    $out = [];
    foreach (explode(',', (string)($admin['zones'] ?? '')) as $entry) {
        $name = strtolower(trim($entry));
        if ($name !== '' && in_array($name, $allZones, true) && !in_array($name, $out, true)) {
            $out[] = $name;
        }
    }

    return $out;
}

function admin_may_zone(string $zone): bool
{
    if (is_owner()) {
        return true;
    }

    return in_array(strtolower(trim($zone)), admin_scope_zones(), true);
}

/**
 * A SQL fragment that restricts a list to the reviewer's suffixes: [' AND d.zone IN (?, ?)', [params]]
 * - empty for an owner, and "AND 1 = 0" for a reviewer who has no suffix yet.
 */
function admin_scope_sql(string $column): array
{
    if (is_owner()) {
        return ['', []];
    }

    $zones = admin_scope_zones();
    if ($zones === []) {
        return [' AND 1 = 0', []];
    }

    return [' AND ' . $column . ' IN (' . implode(', ', array_fill(0, count($zones), '?')) . ')', $zones];
}

/** The 403 page: used for pages a reviewer may not open at all. */
function require_owner(string $what = 'This page'): void
{
    if (is_owner()) {
        return;
    }

    http_response_code(403);
    page_header('Not allowed');
    ?>
    <div class="msg error"><strong>Not allowed</strong></div>
    <p><?= h($what) ?> is reserved for the registry owner.</p>
    <p>
      Your account is a <strong>reviewer</strong>: it may decide requests and record changes for the
      suffixes assigned to it, read those names, and suspend or delete them. Nothing else.
    </p>
    <p>
      Your suffixes:
      <?php $scope = admin_scope_zones(); ?>
      <?= $scope === []
          ? '<em>none assigned yet - ask the owner to assign one</em>'
          : '<code>' . h(implode(', ', $scope)) . '</code>' ?>
    </p>
    <p>
      <a href="index.php">Dashboard</a> &middot; <a href="domains.php">Domains</a> &middot;
      <a href="changes.php">Changes</a>
    </p>
    <?php
    page_footer();
    exit;
}

/** The 403 page for a name that is outside the reviewer's suffixes. */
function admin_deny(string $message): void
{
    http_response_code(403);
    page_header('Outside your suffixes');
    echo '<div class="msg error"><strong>Outside your suffixes</strong></div>';
    echo '<p>' . h($message) . '</p>';
    echo '<p><a href="index.php">Dashboard</a> &middot; <a href="domains.php">Domains</a> &middot; '
        . '<a href="changes.php">Changes</a></p>';
    page_footer();
    exit;
}

/** Loads a name and refuses when it is not the reviewer's business. */
function admin_scope_domain(int $domainId): array
{
    $domain = db_one('SELECT * FROM domains WHERE id = ?', [$domainId]);
    if ($domain === null) {
        admin_deny('That name does not exist.');
    }
    if (!admin_may_zone((string)$domain['zone'])) {
        admin_deny(
            'The name ' . (string)$domain['name'] . ' belongs to the suffix ' . (string)$domain['zone']
            . ', which is not assigned to your account.'
        );
    }

    return $domain;
}

function login_window_cutoff(): string
{
    $minutes = (int)(config('security', [])['login_window_minutes'] ?? 15);

    return date('Y-m-d H:i:s', time() - (max(1, $minutes) * 60));
}

function login_throttled(string $username): bool
{
    $max    = max(3, (int)(config('security', [])['login_max_attempts'] ?? 8));
    $cutoff = login_window_cutoff();
    $ip     = client_ip();

    // Primary rule: this address guessing this account.
    $pair = (int)db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ? AND ip = ? AND username = ?',
        [$cutoff, $ip, $username],
        0
    );
    if ($pair >= $max) {
        return true;
    }

    // Secondary rules with a much higher allowance, so that an attacker cannot lock a
    // legitimate administrator out by hammering a user name (or an address) from elsewhere.
    $wide = $max * 5;
    $perIp = (int)db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ? AND ip = ?',
        [$cutoff, $ip],
        0
    );
    if ($perIp >= $wide) {
        return true;
    }

    $perUser = (int)db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ? AND username = ?',
        [$cutoff, $username],
        0
    );

    return $perUser >= $wide;
}

function record_login_attempt(string $username, bool $success): void
{
    db_query(
        'INSERT INTO login_attempts (username, ip, success, created_at) VALUES (?, ?, ?, NOW())',
        [substr($username, 0, 64), client_ip(), $success ? 1 : 0]
    );

    // Occasional cleanup keeps the table small.
    if (random_int(1, 20) === 1) {
        db_query('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 172800)]);
        db_query('DELETE FROM login_attempts WHERE success = 1 AND created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function admin_login(string $username, string $password): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        fd_start_session();
    }
    $row = db_one('SELECT * FROM admins WHERE username = ?', [$username]);

    if ($row === null || !password_verify($password, (string)$row['password_hash'])) {
        // Equalise timing a little when the account does not exist.
        if ($row === null) {
            password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000000');
        }
        record_login_attempt($username, false);

        return false;
    }

    record_login_attempt($username, true);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['fd_csrf']  = bin2hex(random_bytes(32));
    unset($_SESSION['fd_logout_token']);
    $GLOBALS['FD_ADMIN']  = $row;

    // A successful sign-in clears the failures of this address/account pair.
    db_query('DELETE FROM login_attempts WHERE success = 0 AND ip = ? AND username = ?', [client_ip(), $username]);

    db_query('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), (int)$row['id']]);
    log_action('login', 'admin', (int)$row['id'], 'Signed in from ' . client_ip());

    return true;
}

/** A one-purpose token for the sign-out link, so the CSRF token never travels in a URL. */
function logout_token(): string
{
    fd_start_session();
    if (empty($_SESSION['fd_logout_token'])) {
        $_SESSION['fd_logout_token'] = bin2hex(random_bytes(16));
    }

    return (string)$_SESSION['fd_logout_token'];
}

function admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        fd_start_session();
    }
    $admin = current_admin();
    if ($admin !== null) {
        log_action('logout', 'admin', (int)$admin['id'], 'Signed out');
    }
    unset($_SESSION['admin_id'], $_SESSION['fd_logout_token']);
    $_SESSION['fd_csrf'] = bin2hex(random_bytes(32));
    session_regenerate_id(true);
    $GLOBALS['FD_ADMIN'] = null;
}

function log_action(string $action, string $targetType = '', ?int $targetId = null, string $detail = ''): void
{
    $admin = current_admin();
    try {
        db_query(
            'INSERT INTO audit_log (admin_id, admin_username, action, target_type, target_id, detail, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $admin === null ? null : (int)$admin['id'],
                $admin === null ? 'system' : (string)$admin['username'],
                $action,
                $targetType,
                $targetId,
                $detail,
                client_ip(),
            ]
        );
    } catch (Throwable $e) {
        // Logging must never break a request.
    }
}
