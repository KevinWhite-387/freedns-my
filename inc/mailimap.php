<?php
declare(strict_types=1);

/**
 * freedns.my - reading the vault mailbox over IMAP, without any PHP extension.
 *
 * The registry polls the mailbox that BillionMail keeps for it (vault@mx.freedns.my) and takes the new
 * messages out of it; everything else (archive, scan, forward or hold) happens in inc/mailvault.php and the
 * pages. A minimal IMAP client is written out here on purpose: the imap extension is often not installed,
 * and we only need LOGIN, SELECT, FETCH and STORE.
 *
 * Addresses: the PUBLIC hostname is used first (mail_imap_host, for example mail.mx.freedns.my) and the
 * SERVER ITSELF (127.0.0.1) is the fallback, so a DNS or certificate problem on the public name cannot stop
 * the mail from being collected.
 */

function mailimap_settings(): array
{
    return [
        'host'      => trim((string)setting('mail_imap_host', 'mail.mx.freedns.my')),
        'port'      => max(1, min(65535, (int)setting('mail_imap_port', '993'))),
        'user'      => trim((string)setting('mail_imap_user', 'vault@mx.freedns.my')),
        'pass'      => (string)setting('mail_imap_pass', ''),
        'folder'    => trim((string)setting('mail_imap_folder', 'INBOX')),
        'insecure'  => setting_bool('mail_imap_insecure', true),
        'fallback'  => setting_bool('mail_imap_local_fallback', true),
    ];
}

/** Public first, then the machine itself. */
function mailimap_hosts(): array
{
    $config = mailimap_settings();
    $hosts  = [];
    if ($config['host'] !== '') {
        $hosts[] = $config['host'];
    }
    if ($config['fallback']) {
        foreach (['127.0.0.1', 'localhost'] as $local) {
            if (!in_array($local, $hosts, true)) {
                $hosts[] = $local;
            }
        }
    }

    return $hosts;
}

/** Reads one CRLF line from the socket. */
function mailimap_line($socket): string
{
    $line = @fgets($socket, 8192);

    return $line === false ? '' : rtrim($line, "\r\n");
}

/**
 * Sends one command and collects the answer until the tagged line arrives.
 *
 * @return array{ok: bool, text: string, lines: array<int, string>}
 */
function mailimap_cmd($socket, string $tag, string $command): array
{
    @fwrite($socket, $tag . ' ' . $command . "\r\n");
    $lines = [];
    $ok    = false;

    for ($i = 0; $i < 200; $i++) {
        $line = mailimap_line($socket);
        if ($line === '') {
            break;
        }
        $lines[] = $line;
        if (str_starts_with($line, $tag . ' ')) {
            $ok = stripos($line, $tag . ' OK') === 0;
            break;
        }
    }

    return ['ok' => $ok, 'text' => implode("\n", $lines), 'lines' => $lines];
}

/** Reads exactly $size bytes (an IMAP literal) plus the rest of that line. */
function mailimap_literal($socket, int $size): string
{
    $data = '';
    while (strlen($data) < $size) {
        $chunk = @fread($socket, min(8192, $size - strlen($data)));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $data .= $chunk;
    }
    mailimap_line($socket);      // the ")" that closes the FETCH

    return $data;
}

/**
 * Opens a connection and logs in, trying the hosts in order.
 *
 * @return array{ok: bool, socket: mixed, host: string, error: string}
 */
function mailimap_connect()
{
    $config = mailimap_settings();
    if ($config['user'] === '' || $config['pass'] === '') {
        return ['ok' => false, 'socket' => null, 'host' => '',
            'error' => 'The mailbox account and its password are not set yet (back office -> mail vault).'];
    }

    $errors = [];
    foreach (mailimap_hosts() as $host) {
        $context = stream_context_create(['ssl' => [
            'verify_peer'       => !$config['insecure'],
            'verify_peer_name'  => !$config['insecure'],
            'allow_self_signed' => $config['insecure'],
            'SNI_enabled'       => true,
            'peer_name'         => $config['host'] !== '' ? $config['host'] : $host,
        ]]);
        $socket = @stream_socket_client(
            'ssl://' . $host . ':' . $config['port'],
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket === false) {
            $errors[] = $host . ': ' . ($errstr !== '' ? $errstr : 'connection failed');
            continue;
        }
        stream_set_timeout($socket, 20);

        $greeting = mailimap_line($socket);
        if (stripos($greeting, 'OK') === false) {
            $errors[] = $host . ': the mailbox did not greet us ("' . $greeting . '")';
            @fclose($socket);
            continue;
        }

        $login = mailimap_cmd($socket, 'a1',
            'LOGIN "' . addcslashes($config['user'], '"\\') . '" "' . addcslashes($config['pass'], '"\\') . '"');
        if (!$login['ok']) {
            $errors[] = $host . ': login refused (' . $login['text'] . ')';
            @fclose($socket);
            continue;
        }

        return ['ok' => true, 'socket' => $socket, 'host' => $host, 'error' => ''];
    }

    return ['ok' => false, 'socket' => null, 'host' => '', 'error' => implode(' | ', $errors)];
}

function mailimap_close($socket): void
{
    if (is_resource($socket)) {
        mailimap_cmd($socket, 'a9', 'LOGOUT');
        @fclose($socket);
    }
}

/**
 * Collects the new messages. Every message is returned as raw RFC822 text; with $markSeen the mailbox is
 * left tidy (the message is also copied into the vault by the caller).
 *
 * @return array{ok: bool, host: string, error: string, messages: array<int, array{uid: string, raw: string}>}
 */
function mailimap_poll(int $limit = 20, bool $markSeen = true, string $folder = ''): array
{
    $result = ['ok' => false, 'host' => '', 'error' => '', 'messages' => []];
    $config = mailimap_settings();
    $folder = $folder !== '' ? $folder : $config['folder'];

    $open = mailimap_connect();
    if (!$open['ok']) {
        $result['error'] = $open['error'];

        return $result;
    }
    $socket = $open['socket'];
    $result['host'] = $open['host'];

    $select = mailimap_cmd($socket, 'a2', 'SELECT "' . addcslashes($folder, '"\\') . '"');
    if (!$select['ok']) {
        $result['error'] = 'The folder "' . $folder . '" cannot be opened: ' . $select['text'];
        mailimap_close($socket);

        return $result;
    }

    $exists = 0;
    foreach ($select['lines'] as $line) {
        if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $match) === 1) {
            $exists = (int)$match[1];
        }
    }
    if ($exists === 0) {
        $result['ok'] = true;
        mailimap_close($socket);

        return $result;
    }

    $limit = max(1, min(200, $limit));

    // The oldest first, so a burst is handled in the order it arrived.
    $fetch = 'a3 FETCH 1:' . $exists . ' (UID RFC822)';
    @fwrite($socket, $fetch . "\r\n");

    $collected = 0;
    for ($i = 0; $i < 400 && $collected < $limit; $i++) {
        $line = mailimap_line($socket);
        if ($line === '') {
            break;
        }
        if (str_starts_with($line, 'a3 ')) {
            break;
        }

        if (preg_match('/^\*\s+\d+\s+FETCH\s+\(UID\s+(\d+)\s+RFC822\s+\{(\d+)\}/i', $line, $match) === 1) {
            $uid = $match[1];
            $raw = mailimap_literal($socket, (int)$match[2]);
            if ($raw !== '') {
                $result['messages'][] = ['uid' => $uid, 'raw' => $raw];
                $collected++;
            }
            continue;
        }

        // Some servers put the UID after the body; the literal is still announced with {size}.
        if (preg_match('/\{(\d+)\}$/', $line, $match) === 1) {
            $raw = mailimap_literal($socket, (int)$match[1]);
            if ($raw !== '') {
                $result['messages'][] = ['uid' => '', 'raw' => $raw];
                $collected++;
            }
        }
    }

    if ($markSeen && $result['messages'] !== []) {
        mailimap_cmd($socket, 'a4', 'STORE 1:' . $exists . ' +FLAGS (\Seen)');
    }

    mailimap_close($socket);
    $result['ok'] = true;

    return $result;
}

/** A short report for the back office: which host answered, how many messages wait. */
function mailimap_self_test(): array
{
    $out = ['ok' => false, 'host' => '', 'count' => 0, 'error' => ''];
    $config = mailimap_settings();

    $open = mailimap_connect();
    if (!$open['ok']) {
        $out['error'] = $open['error'];

        return $out;
    }
    $socket = $open['socket'];
    $out['host'] = $open['host'];

    $select = mailimap_cmd($socket, 'a2', 'SELECT "' . addcslashes($config['folder'], '"\\') . '"');
    if (!$select['ok']) {
        $out['error'] = $select['text'];
        mailimap_close($socket);

        return $out;
    }
    $unseen = mailimap_cmd($socket, 'a5', 'SEARCH UNSEEN');
    if (preg_match('/^\*\s+SEARCH\s*(.*)$/i', $unseen['text'], $match) === 1) {
        $list = trim($match[1]);
        $out['count'] = $list === '' ? 0 : count(preg_split('/\s+/', $list) ?: []);
    }
    mailimap_close($socket);
    $out['ok'] = true;

    return $out;
}
