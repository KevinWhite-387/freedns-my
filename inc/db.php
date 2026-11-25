<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper. All queries in this application use prepared statements.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string)($c['host'] ?? '127.0.0.1'),
        (int)($c['port'] ?? 3306),
        (string)($c['name'] ?? ''),
        (string)($c['charset'] ?? 'utf8mb4')
    );

    $pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        db_schema_trouble($e, $sql);

        throw $e;
    }

    return $stmt;
}

/**
 * A missing table or column means the database was not upgraded yet (a new release added one). Instead
 * of the blank 500 an operator used to get, this explains what happened: the operator sees the statement
 * that has to be imported, everybody else a short "try again later". The error is still logged and the
 * exception is re-thrown, so nothing is silently swallowed.
 */
function db_schema_trouble(PDOException $e, string $sql): void
{
    static $handled = false;

    $message = $e->getMessage();
    $isSchema = str_contains($message, 'Unknown column')
        || str_contains($message, "doesn't exist")
        || str_contains($message, 'Base table or view not found');

    error_log('freedns.my database error: ' . $message . ' | SQL: ' . preg_replace('/\s+/', ' ', $sql));

    if ($handled || !$isSchema) {
        return;
    }
    $handled = true;

    if (!function_exists('is_admin') || !function_exists('page_header')) {
        return; // Too early in the request (installer, cron): the log entry has to be enough.
    }

    $operator = false;
    try {
        $operator = is_admin();
    } catch (Throwable $ignored) {
        $operator = false;
    }

    http_response_code($operator ? 500 : 503);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    $selfTest  = function_exists('url') ? url('selftest.php') : '/selftest.php';
    $dashboard = function_exists('url') ? url('admin/index.php') : '/admin/index.php';

    if ($operator) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Database not up to date</title>'
            . '<style>body{font-family:monospace;background:#fff;color:#111;margin:2rem;line-height:1.6}'
            . 'code{background:#f3f3f3;padding:.1rem .3rem}pre{background:#f7f7f7;border:1px solid #ddd;'
            . 'padding:.6rem;overflow-x:auto}</style></head><body>';
        echo '<h1>Database not up to date</h1>';
        echo '<p>The database is missing a table or a column that this version of the registry uses, so the '
            . 'request could not be finished. Nothing was changed.</p>';
        echo '<p>MySQL said: <code>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</code></p>';
        echo '<p>Statement: <code>' . htmlspecialchars(preg_replace('/\s+/', ' ', $sql) ?? '', ENT_QUOTES, 'UTF-8')
            . '</code></p>';
        echo '<h2>What to do</h2><ol>'
            . '<li>Open <a href="' . htmlspecialchars($selfTest, ENT_QUOTES, 'UTF-8')
            . '">the self test</a>: it lists every missing table and column together with the SQL to run.</li>'
            . '<li>Import the matching <code>upgrade-*.sql</code> file in phpMyAdmin (or paste the statement '
            . 'it prints).</li>'
            . '<li>Reload this page.</li></ol>';
        echo '<p><a href="' . htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8')
            . '">Back to the dashboard</a></p>';
        echo '</body></html>';
    } else {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Please try again</title>'
            . '<style>body{font-family:monospace;background:#fff;color:#111;margin:2rem;line-height:1.6}'
            . '</style></head><body>';
        echo '<h1>Briefly unavailable</h1><p>The registry is being updated. Please try again in a few '
            . 'minutes; nothing was changed on your account.</p>';
        echo '</body></html>';
    }
    exit;
}

/** Returns the first row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/** Returns all rows. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Returns a single scalar value, or the supplied default. */
function db_value(string $sql, array $params = [], $default = null)
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}
