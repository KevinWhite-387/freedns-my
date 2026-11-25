<?php
/**
 * freedns.my - sample configuration.
 *
 * Copy this file to config.php and adjust the values, or simply run install.php
 * from a browser: the installer can create this file for you.
 *
 * Keep config.php out of any public repository or backup you share.
 */

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'freedns',
        'user'    => 'freedns',
        'pass'    => 'change-me',
        'charset' => 'utf8mb4',
    ],

    'site' => [
        // Public base URL, no trailing slash. Leave empty to auto-detect.
        'base_url'     => 'https://freedns.my',
        'session_name' => 'freednssid',
        'timezone'     => 'UTC',
        // Set to true only while debugging on a private server.
        'debug'        => false,
    ],

    'security' => [
        // Force the session cookie to HTTPS only. Set false for plain-HTTP testing.
        'cookie_secure'        => true,
        'login_max_attempts'   => 8,
        'login_window_minutes' => 15,
    ],

    /**
     * Telegram bot (optional).
     *
     * A token from @BotFather, and a secret of your own (openssl rand -hex 32) that Telegram is told about when
     * the webhook is registered, so that only Telegram can post to tg-webhook.php.
     *
     * Both may instead live in the settings table (`tg_token` / `tg_secret`, editable in the back office) - this
     * file simply takes priority, and a missing `telegram` block is not an error.
     */
    'telegram' => [
        'token'  => '',
        'secret' => '',
    ],
];
