<?php
declare(strict_types=1);

/**
 * freedns.my - TEMPORARY debug entry for /api.php.
 *
 * Why it exists: the HTTP API answers 500 through nginx/php-fpm while the very same file runs fine from the
 * command line, so the fatal error only happens under the web SAPI and nothing reaches the error log (error
 * logging is off on this install). This file runs api.php exactly as the web server would, but turns error
 * reporting on and prints what went wrong.
 *
 * Use:
 *   https://freedns.my/api-debug.php?fddebug=1&action=domains
 *   (the answer is api.php's own answer, with any fatal error or exception appended as plain text)
 *
 * Without fddebug=1 it behaves exactly like /api.php, so it cannot be used to leak errors by accident.
 *
 * DELETE THIS FILE once the problem is found. It is a diagnostic, not part of the site.
 */

$fdDebug = (string)($_GET['fddebug'] ?? '') === '1';

if ($fdDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);

    // A fatal error is not catchable: this prints it after the request has died.
    register_shutdown_function(static function (): void {
        $last = error_get_last();
        if ($last === null) {
            return;
        }
        if (!in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "\n\n=== FATAL ===\n"
            . $last['message'] . "\n"
            . $last['file'] . ':' . $last['line'] . "\n"
            . "PHP " . PHP_VERSION . ' · SAPI ' . PHP_SAPI . "\n"
            . "memory_limit " . ini_get('memory_limit') . ' · peak ' . memory_get_peak_usage(true) . " bytes\n";
    });
}

try {
    require __DIR__ . '/api.php';
} catch (Throwable $t) {
    if ($fdDebug) {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "\n\n=== EXCEPTION ===\n"
            . get_class($t) . ': ' . $t->getMessage() . "\n"
            . $t->getFile() . ':' . $t->getLine() . "\n\n"
            . $t->getTraceAsString() . "\n";
        return;
    }
    // Without the flag it stays quiet, exactly like api.php would if it could answer.
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'internal', 'message' => 'The API could not answer.']);
}
