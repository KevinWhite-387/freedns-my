<?php
declare(strict_types=1);

/**
 * freedns.my - TEMPORARY probe: find the line where /api.php dies under php-fpm.
 *
 * The HTTP API answers 500 through nginx while php-cgi runs the same file fine, so the failure happens in the
 * web SAPI only. This file walks the same steps api.php walks, one at a time, printing after each one:
 *
 *   /probe.php?step=1   require inc/bootstrap.php, then stop
 *   /probe.php?step=2   ... plus api_table_ready() and api_enabled()
 *   /probe.php?step=3   ... plus api_current_user()
 *   /probe.php?step=4   ... plus the API table's columns
 *
 * Whatever the last line printed before the 500 tells us the exact function that broke. Every answer is plain
 * text, which also proves whether output reaches the browser at all.
 *
 * DELETE THIS FILE when the problem is found. It exposes the shape of the install, nothing secret.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last === null || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "\n=== FATAL ===\n" . $last['message'] . "\n" . $last['file'] . ':' . $last['line'] . "\n";
});

header('Content-Type: text/plain; charset=utf-8');
$step = max(1, min(4, (int)($_GET['step'] ?? 1)));

echo "probe step $step\n";
echo 'PHP ' . PHP_VERSION . ' · SAPI ' . PHP_SAPI . ' · memory_limit ' . ini_get('memory_limit') . "\n";
echo 'SCRIPT_FILENAME ' . (string)($_SERVER['SCRIPT_FILENAME'] ?? '-') . "\n";
echo 'open_basedir ' . (ini_get('open_basedir') !== '' ? ini_get('open_basedir') : '(none)') . "\n";
echo 'disable_functions ' . (ini_get('disable_functions') !== '' ? ini_get('disable_functions') : '(none)') . "\n";
echo 'auto_prepend_file ' . (ini_get('auto_prepend_file') !== '' ? ini_get('auto_prepend_file') : '(none)') . "\n";
echo "---\n";

try {
    require __DIR__ . '/inc/bootstrap.php';
    echo "1  bootstrap ok\n";
} catch (Throwable $t) {
    echo "1  bootstrap THREW " . get_class($t) . ': ' . $t->getMessage() . "\n";
    exit;
}
if ($step < 2) {
    echo "done at step 1\n";
    return;
}

foreach (['api_table_ready', 'api_enabled', 'api_current_user', 'api_tokens_of', 'api_save_records'] as $fn) {
    echo '   function ' . $fn . ': ' . (function_exists($fn) ? 'present' : 'MISSING') . "\n";
}

try {
    $ready = api_table_ready();
    echo '2  api_table_ready() = ' . var_export($ready, true) . "\n";
    echo '   api_enabled()     = ' . var_export(api_enabled(), true) . "\n";
} catch (Throwable $t) {
    echo "2  THREW " . get_class($t) . ': ' . $t->getMessage() . "\n";
    exit;
}
if ($step < 3) {
    echo "done at step 2\n";
    return;
}

try {
    $user = api_current_user();
    echo '3  api_current_user() = ' . ($user === null ? 'null (no token sent)' : 'an account') . "\n";
} catch (Throwable $t) {
    echo "3  THREW " . get_class($t) . ': ' . $t->getMessage() . "\n";
    exit;
}
if ($step < 4) {
    echo "done at step 3\n";
    return;
}

try {
    $row = db_one("SHOW TABLES LIKE 'api\\_tokens'");
    echo '4  table api_tokens: ' . ($row === null ? 'MISSING' : 'present') . "\n";
    if ($row !== null) {
        foreach (db_all('SHOW COLUMNS FROM api_tokens') as $col) {
            echo '   column ' . (string)($col['Field'] ?? '?') . "\n";
        }
    }
} catch (Throwable $t) {
    echo "4  THREW " . get_class($t) . ': ' . $t->getMessage() . "\n";
}
echo "done at step 4\n";
