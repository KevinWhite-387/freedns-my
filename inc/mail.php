<?php
declare(strict_types=1);

/**
 * freedns.my - outgoing mail.
 *
 * Two transports:
 *   smtp - a plain socket SMTP client (no library needed), with implicit TLS (port 465),
 *          STARTTLS (port 587) or an unencrypted connection, and AUTH LOGIN;
 *   mail - the PHP mail() function, which needs a working MTA on the server.
 *
 * The transport is chosen automatically: SMTP when a host is configured, mail() otherwise.
 * The last SMTP conversation and error are kept in $GLOBALS for the test page in the back office.
 */

function mail_settings(): array
{
    $secure = strtolower(trim((string)setting('smtp_secure', 'ssl')));
    if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
        $secure = 'ssl';
    }

    return [
        'transport' => strtolower(trim((string)setting('mail_transport', 'auto'))),
        'host'      => trim((string)setting('smtp_host', '')),
        'port'      => max(1, min(65535, (int)setting('smtp_port', '465'))),
        'secure'    => $secure,
        'user'      => trim((string)setting('smtp_user', '')),
        'pass'      => (string)setting('smtp_pass', ''),
        'timeout'   => max(5, min(60, (int)setting('smtp_timeout', '15'))),
        'insecure'  => setting_bool('smtp_insecure', false),
    ];
}

/** Which transport the next message will use. */
function mail_transport(): string
{
    $settings = mail_settings();

    if ($settings['transport'] === 'mail') {
        return 'mail';
    }
    if ($settings['transport'] === 'smtp') {
        return 'smtp';
    }

    return $settings['host'] === '' ? 'mail' : 'smtp';
}

function mail_from_address(): string
{
    $from = trim((string)setting('mail_from', ''));
    if ($from !== '' && valid_email_addr($from)) {
        return $from;
    }

    // Most mail providers insist that the sender is the authenticated mailbox.
    $user = trim((string)setting('smtp_user', ''));
    if ($user !== '' && valid_email_addr($user) && mail_transport() === 'smtp') {
        return $user;
    }

    $zone = default_zone();
    $host = $zone === null ? 'localhost' : (string)$zone['name'];

    return 'no-reply@' . preg_replace('/[^a-z0-9.\-]/i', '', $host);
}

/** Encodes a header value that may contain non-ASCII characters (RFC 2047). */
function mail_header_text(string $value): string
{
    $value = header_safe($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/[\x80-\xff]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    return $value;
}

/** Builds the complete RFC 5322 message used by both transports. */
function mail_build_message(string $fromAddress, string $fromName, string $to, string $subject, string $body): string
{
    $host = 'localhost';
    if (function_exists('gethostname')) {
        $name = gethostname();
        if (is_string($name) && $name !== '') {
            $host = $name;
        }
    }

    $headers = [
        'Date: ' . date('r'),
        'From: ' . ($fromName === '' ? '' : mail_header_text($fromName) . ' ') . '<' . $fromAddress . '>',
        'To: <' . $to . '>',
        'Subject: ' . mail_header_text($subject),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . preg_replace('/[^a-z0-9.\-]/i', '', $host) . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: freedns.my',
    ];

    $encoded = chunk_split(base64_encode($body), 76, "\r\n");

    return implode("\r\n", $headers) . "\r\n\r\n" . $encoded;
}

/**
 * Talks SMTP to the configured server.
 *
 * @param array<int, string> $log filled with the conversation, for the test page
 */
function smtp_deliver(array $settings, string $from, string $to, string $message, string &$error, array &$log): bool
{
    $error = '';
    $log   = [];

    $prefix = $settings['secure'] === 'ssl' ? 'ssl://' : '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => !$settings['insecure'],
            'verify_peer_name'  => !$settings['insecure'],
            'allow_self_signed' => (bool)$settings['insecure'],
        ],
    ]);

    $errno  = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        $prefix . $settings['host'] . ':' . $settings['port'],
        $errno,
        $errstr,
        (float)$settings['timeout'],
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        $error = 'Could not connect to ' . $settings['host'] . ':' . $settings['port'] . ' — '
            . ($errstr === '' ? 'unknown error' : $errstr) . ' (' . $errno . ')';

        return false;
    }
    stream_set_timeout($socket, (int)$settings['timeout']);

    $lastCode = 0;
    $readReply = static function () use ($socket, &$log, &$error, &$lastCode): int {
        $data = '';
        while (($line = fgets($socket, 2048)) !== false) {
            $data .= $line;
            $separator = $line[3] ?? ' ';
            if ($separator === ' ' || $separator === "\r" || $separator === "\n") {
                break;
            }
        }
        $log[] = '< ' . trim($data);
        if ($data === '') {
            $error    = 'The mail server closed the connection without an answer.';
            $lastCode = 0;

            return 0;
        }
        $lastCode = (int)substr($data, 0, 3);

        return $lastCode;
    };

    $command = static function (string $line) use ($socket, &$log): void {
        $log[] = '> ' . (strlen($line) > 120 ? substr($line, 0, 120) . '...' : $line);
        fwrite($socket, $line . "\r\n");
    };

    /** Sets the error message and closes the connection. */
    $abort = static function (string $message) use (&$error, $socket): bool {
        $error = $message;
        @fclose($socket);

        return false;
    };

    if ($readReply() !== 220) {
        return $abort($error !== '' ? $error
            : 'The mail server refused the connection (code ' . $lastCode . ').');
    }
    $hostname = 'localhost';
    if (function_exists('gethostname')) {
        $name = gethostname();
        if (is_string($name) && $name !== '') {
            $hostname = $name;
        }
    }

    $command('EHLO ' . $hostname);
    if ($readReply() !== 250) {
        return $abort('The mail server rejected EHLO (code ' . $lastCode . '). '
            . 'Check the host name and whether the server expects SSL or STARTTLS.');
    }

    if ($settings['secure'] === 'tls') {
        $command('STARTTLS');
        if ($readReply() !== 220) {
            return $abort('The mail server refused STARTTLS (code ' . $lastCode . '). '
                . 'Try SSL on port 465 instead.');
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $abort('The TLS handshake failed. If the certificate is fine but the server reports an '
                . 'error, tick "Skip TLS certificate verification".');
        }
        $command('EHLO ' . $hostname);
        if ($readReply() !== 250) {
            return $abort('The mail server rejected EHLO after STARTTLS (code ' . $lastCode . ').');
        }
    }

    if ($settings['user'] !== '') {
        $command('AUTH LOGIN');
        if ($readReply() !== 334) {
            return $abort('The mail server does not offer AUTH LOGIN (code ' . $lastCode . ').');
        }
        $command(base64_encode($settings['user']));
        if ($readReply() !== 334) {
            return $abort('The mail server rejected the mailbox name (code ' . $lastCode . ').');
        }
        $command(base64_encode($settings['pass']));
        if ($readReply() !== 235) {
            return $abort('Authentication failed (code ' . $lastCode . '). Use the client password / '
                . 'authorisation code of the mailbox, not the web password.');
        }
    }

    $command('MAIL FROM:<' . $from . '>');
    if ($readReply() !== 250) {
        return $abort('The mail server refused the sender address ' . $from . ' (code ' . $lastCode . '). '
            . 'Many providers only accept the authenticated mailbox here.');
    }

    $command('RCPT TO:<' . $to . '>');
    $code = $readReply();
    if ($code !== 250 && $code !== 251) {
        return $abort('The mail server refused the recipient ' . $to . ' (code ' . $code . ').');
    }

    $command('DATA');
    if ($readReply() !== 354) {
        return $abort('The mail server refused the message data (code ' . $lastCode . ').');
    }

    $normalised = str_replace(["\r\n", "\r", "\n"], "\n", $message);
    $normalised = preg_replace('/^\./m', '..', $normalised) ?? $normalised;
    $normalised = str_replace("\n", "\r\n", $normalised);

    $log[] = '> [message: ' . strlen($normalised) . ' bytes]';
    fwrite($socket, $normalised . "\r\n.\r\n");

    if ($readReply() !== 250) {
        return $abort('The message was not accepted (code ' . $lastCode . '): '
            . trim((string)end($log)));
    }

    $command('QUIT');
    @fclose($socket);

    return true;
}

/**
 * Sends one message. Returns true when the mail server (or mail()) accepted it.
 * On failure the reason is kept in $GLOBALS['FD_MAIL_ERROR'].
 */
function send_mail(string $to, string $subject, string $body): bool
{
    if (!valid_email_addr($to)) {
        $GLOBALS['FD_MAIL_ERROR'] = 'Not a valid recipient address: ' . $to;

        return false;
    }

    $fromName = mail_header_text((string)setting('site_title', 'freedns.my'));
    $fromAddr = mail_from_address();   // an address is never encoded, only validated
    if (!valid_email_addr($fromAddr)) {
        $GLOBALS['FD_MAIL_ERROR'] = 'Not a valid sender address: ' . $fromAddr;

        return false;
    }

    $message = mail_build_message($fromAddr, $fromName, $to, $subject, $body);
    $GLOBALS['FD_MAIL_ERROR'] = '';
    $GLOBALS['FD_SMTP_LOG']   = [];

    if (mail_transport() === 'smtp') {
        $settings = mail_settings();
        $error    = '';
        $log      = [];
        $ok       = smtp_deliver($settings, $fromAddr, $to, $message, $error, $log);
        $GLOBALS['FD_SMTP_LOG'] = $log;

        if (!$ok) {
            $GLOBALS['FD_MAIL_ERROR'] = $error;
            log_action('mail_failed', 'mail', null, 'SMTP to ' . $to . ': ' . shorten($error, 300));

            return false;
        }

        return true;
    }

    $headers = 'From: ' . ($fromName === '' ? '' : $fromName . ' ') . '<' . $fromAddr . ">\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "X-Mailer: freedns.my\r\n";

    $parts = explode("\r\n\r\n", $message, 2);
    $encodedBody = $parts[1] ?? base64_encode($body);

    $sent = @mail($to, mail_header_text($subject), $encodedBody, $headers);
    if (!$sent) {
        $GLOBALS['FD_MAIL_ERROR'] = 'PHP mail() returned false: no working mail service on the server '
            . '(configure SMTP under Administration -> Mail).';
        log_action('mail_failed', 'mail', null, 'mail() to ' . $to . ' failed');
    }

    return (bool)$sent;
}
