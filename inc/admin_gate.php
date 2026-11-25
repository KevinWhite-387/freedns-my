<?php
declare(strict_types=1);

/**
 * freedns.my - the hidden back-office entry.
 *
 * When the gate is switched on (Settings -> Admin entry), every address under /admin/ answers
 * 404 unless the session has already passed the gate. The gate is opened with the secret slug
 * (for example /admin/login.php?k=<slug>), which brings up a pass-phrase form; only after that
 * the ordinary sign-in form appears.
 *
 * The gate is off by default so an existing installation is never locked out by an upgrade.
 * To recover from a lost slug or pass phrase, set admin_gate_enabled to 0 (or delete that row)
 * in the settings table with phpMyAdmin; the back office is then reachable again.
 */

const FD_ADMIN_GATE_SESSION = 'fd_admin_gate';
const FD_ADMIN_GATE_ACCOUNT = 'fd_admin_gate_account';
const FD_ADMIN_GATE_TTL     = 604800;   // re-ask for the pass phrase after seven days

function admin_gate_enabled(): bool
{
    return setting_bool('admin_gate_enabled', false);
}

function admin_gate_slug(): string
{
    return trim((string)setting('admin_gate_slug', ''));
}

function admin_gate_has_pass(): bool
{
    return trim((string)setting('admin_gate_pass', '')) !== '';
}

/** The URL the operator has to remember: /admin/login.php?k=<slug>. */
function admin_gate_url(): string
{
    $slug = admin_gate_slug();

    return $slug === '' ? '' : url('admin/login.php') . '?k=' . rawurlencode($slug);
}

/* ------------------------------------------------------------------ *
 * One entry key per account                                           *
 *                                                                     *
 * The owner keeps the master entry above. Every other account can get *
 * its own slug and its own pass phrase, so a leaked reviewer key does  *
 * not expose the master entry, and that entry may only sign in the     *
 * account it belongs to.                                              *
 * ------------------------------------------------------------------ */

/** True when the admins table carries the per-account entry columns. */
function admin_gate_columns_ready(): bool
{
    if (isset($GLOBALS['FD_ADMIN_ENTRY_COLS'])) {
        return (bool)$GLOBALS['FD_ADMIN_ENTRY_COLS'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM admins LIKE 'gate_slug'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_ADMIN_ENTRY_COLS'] = $ready;

    return $ready;
}

/** The account whose entry slug this is, or null. */
function admin_entry_by_slug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '' || !admin_gate_columns_ready()) {
        return null;
    }

    $row = db_one('SELECT * FROM admins WHERE gate_slug = ? LIMIT 1', [$slug]);
    if ($row === null) {
        return null;
    }

    return $row;
}

/** The entry address to hand to that account, or '' when it has no own key. */
function admin_entry_url(array $admin): string
{
    $slug = trim((string)($admin['gate_slug'] ?? ''));

    return $slug === '' ? '' : url('admin/login.php') . '?k=' . rawurlencode($slug);
}

function admin_entry_has_pass(array $admin): bool
{
    return trim((string)($admin['gate_pass'] ?? '')) !== '';
}

function admin_entry_new_slug(): string
{
    return bin2hex(random_bytes(12));
}

/**
 * Which entry the request came through: the master slug, an account slug, or nothing.
 *
 * @return array{kind: string, admin: ?array}
 */
function admin_gate_resolve(string $given): array
{
    $given = trim($given);
    if ($given === '') {
        return ['kind' => 'none', 'admin' => null];
    }

    $master = admin_gate_slug();
    if ($master !== '' && hash_equals($master, $given)) {
        return ['kind' => 'master', 'admin' => null];
    }

    $account = admin_entry_by_slug($given);
    if ($account !== null) {
        return ['kind' => 'account', 'admin' => $account];
    }

    return ['kind' => 'none', 'admin' => null];
}

/** The pass phrase hash that belongs to this entry: '' when none is stored. */
function admin_gate_pass_hash(string $kind, ?array $admin): string
{
    if ($kind === 'account' && $admin !== null) {
        return trim((string)($admin['gate_pass'] ?? ''));
    }

    return trim((string)setting('admin_gate_pass', ''));
}

/** The account an open gate is bound to: 0 = the master entry (or no gate), -1 = nothing open. */
function admin_gate_bound_admin_id(): int
{
    if (!admin_gate_enabled()) {
        // With the hidden entry switched off there is no binding at all: any account may sign in.
        return 0;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return -1;
    }
    if (!admin_gate_passed()) {
        return -1;
    }

    return (int)($_SESSION[FD_ADMIN_GATE_ACCOUNT] ?? 0);
}

/** True when the visitor may see the back office at all. Needs an active session. */
function admin_gate_passed(): bool
{
    if (!admin_gate_enabled()) {
        return true;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Without a session there is nothing that could remember the pass phrase.
        return false;
    }

    $stamp = (int)($_SESSION[FD_ADMIN_GATE_SESSION] ?? 0);

    return $stamp > 0 && (time() - $stamp) < FD_ADMIN_GATE_TTL;
}

function admin_gate_open(int $adminId = 0): void
{
    fd_start_session();
    $_SESSION[FD_ADMIN_GATE_SESSION] = time();
    $_SESSION[FD_ADMIN_GATE_ACCOUNT] = $adminId;
}

function admin_gate_close(): void
{
    if (isset($_SESSION)) {
        unset($_SESSION[FD_ADMIN_GATE_SESSION], $_SESSION[FD_ADMIN_GATE_ACCOUNT]);
    }
}

/** Answers like an ordinary missing page, without hinting that a back office exists. */
function admin_gate_hide(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n\nThe requested page does not exist on this server.\n";
    exit;
}

/**
 * Enforced at the top of every admin entry point (and of require_admin()).
 * Either passes, shows the pass-phrase form, or hides the back office.
 */
function admin_gate_enforce(): void
{
    if (!admin_gate_enabled()) {
        return;
    }

    $cookieName = (string)(config('site', [])['session_name'] ?? 'freednssid');
    $given      = (string)($_POST['k'] ?? $_GET['k'] ?? '');

    // A visitor without the session cookie and without the secret parameter has nothing to
    // remember: answer 404 without setting a cookie at all.
    if ($given === '' && empty($_COOKIE[$cookieName])) {
        admin_gate_hide();
    }

    // The session must exist before the pass is looked up: otherwise every page except the
    // sign-in page would look locked even right after the gate was opened.
    fd_start_session();

    if (admin_gate_passed()) {
        return;
    }

    // Which door is this? The master entry of the owner, or the own key of one account.
    $entry = admin_gate_resolve($given);
    if ($entry['kind'] === 'none') {
        admin_gate_hide();
    }

    $boundId   = $entry['kind'] === 'account' && $entry['admin'] !== null ? (int)$entry['admin']['id'] : 0;
    $passHash  = admin_gate_pass_hash($entry['kind'], $entry['admin']);
    $entryName = $entry['kind'] === 'account' && $entry['admin'] !== null
        ? (string)$entry['admin']['username']
        : 'the master entry';

    if ($passHash === '') {
        // Half-configured gate: opening it with the secret address is enough, but say so after login.
        admin_gate_open($boundId);
        redirect('login.php');
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gate_pass'])) {
        csrf_verify();
        $attempt = (string)$_POST['gate_pass'];
        $key     = portal_key('admin-gate', client_ip() . '|' . $given);

        if (portal_throttled($key)) {
            $error = 'Too many attempts. Please wait a few minutes and try again.';
        } elseif (password_verify($attempt, $passHash)) {
            record_portal_attempt($key, true);
            log_action('admin_gate_opened', 'admin', $boundId > 0 ? $boundId : null,
                'Entry opened for ' . $entryName . ' from ' . client_ip());
            admin_gate_open($boundId);
            redirect('login.php');
        } else {
            record_portal_attempt($key, false);
            log_action('admin_gate_failed', 'admin', $boundId > 0 ? $boundId : null,
                'Wrong pass phrase for ' . $entryName . ' from ' . client_ip());
            $error = 'That pass phrase is not correct.';
        }
    }

    $GLOBALS['FD_ADMIN_GATE_PROMPT'] = true;

    page_header('Security check');
    ?>
    <p>Enter the pass phrase for this entry to continue.</p>
    <?= error_list($error === '' ? [] : [$error]) ?>
    <form method="post" action="<?= h($_SERVER['REQUEST_URI'] ?? 'login.php') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="k" value="<?= h($given) ?>">
      <label for="gate_pass">Pass phrase</label>
      <input type="password" id="gate_pass" name="gate_pass" autocomplete="off" required>
      <p><input type="submit" value="Continue"></p>
    </form>
    <?php
    page_footer();
    exit;
}
