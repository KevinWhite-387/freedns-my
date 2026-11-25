<?php
declare(strict_types=1);

/**
 * freedns.my - the mail pipeline (run from cron).
 *
 *   curl -s "https://freedns.my/mail-cron.php?key=<cf_cron_key>&limit=20"
 *
 * What it does, in this order:
 *   1. takes the new messages out of the vault mailbox over IMAP (inc/mailimap.php);
 *   2. archives each one encrypted (inc/mailvault.php) - that is what the back office can look at later;
 *   3. checks the content: anything that looks like a threat, extortion or coercion is NOT forwarded and is
 *      put in the human queue instead (mail_scan_mode rules; AI can be plugged in later);
 *   4. forwards the clean ones to the address the holder set up, through the site's SMTP (the Alibaba relay,
 *      as agreed - it has the reputation and the PTR that this server does not);
 *   5. deletes what passed the retention time (mail-vault: viewable 90 days, downloadable 180, then gone).
 *
 * Nothing here is exposed to a browser: no session, no cookie, plain text.
 */

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key      = trim((string)($_GET['key'] ?? $_REQUEST['key'] ?? ''));
$expected = trim((string)setting('cf_cron_key', ''));

if ($expected === '') {
    http_response_code(503);
    echo "no cron key is set (back office -> DNS page)\n";
    exit;
}
if ($key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}
if (!setting_bool('mail_pipeline', true)) {
    echo "the mail pipeline is switched off (mail_pipeline = 0)\n";
    // Advertising orders are still kept in step: this endpoint already runs every minute, and whether an
    // advertisement goes up or comes down should not depend on the mail pipeline being on.
    if (function_exists('ad_sync_orders')) {
        $ads = ad_sync_orders();
        echo 'ads: ' . (int)$ads['up'] . ' published, ' . (int)$ads['down'] . " taken down\n";
    }
    exit;
}

// Advertising orders first: cheap, and it keeps the slots in step with what has been paid for.
if (function_exists('ad_sync_orders')) {
    $ads = ad_sync_orders();
    echo 'ads: ' . (int)$ads['up'] . ' published, ' . (int)$ads['down'] . " taken down\n";
    if (function_exists('ad_remind')) {
        echo 'ads: ' . (int)ad_remind() . " renewal reminder(s) sent\n";
    }
}

$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));

/** Splits an RFC822 message into headers and body, decoding the usual transfer encodings. */
function mailpipe_split(string $raw): array
{
    $split = preg_split("/\r?\n\r?\n/", $raw, 2) ?: ['', ''];
    $head  = $split[0] ?? '';
    $body  = $split[1] ?? '';

    $headers = [];
    $current = '';
    foreach (preg_split("/\r?\n/", $head) ?: [] as $line) {
        if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
            $headers[$current] = ($headers[$current] ?? '') . ' ' . trim($line);
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $current = strtolower(trim(substr($line, 0, $pos)));
        $headers[$current] = trim(substr($line, $pos + 1));
    }

    $encoding = strtolower((string)($headers['content-transfer-encoding'] ?? ''));
    if ($encoding === 'base64') {
        $decoded = base64_decode($body, false);
        if (is_string($decoded) && $decoded !== '') {
            $body = $decoded;
        }
    } elseif ($encoding === 'quoted-printable') {
        $body = quoted_printable_decode($body);
    }

    return ['headers' => $headers, 'body' => $body];
}

/** "Name <a@b>" -> "a@b"; also decodes the usual encoded-word subjects. */
function mailpipe_address(string $value): string
{
    if (preg_match('/<([^>]+)>/', $value, $match) === 1) {
        $value = $match[1];
    }

    return trim($value, " \t\n\r<>");
}

function mailpipe_subject(string $value): string
{
    if (preg_match_all('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', $value, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $match) {
            $decoded = $match[2] === 'B' || $match[2] === 'b'
                ? base64_decode($match[3], false)
                : quoted_printable_decode(str_replace('_', ' ', $match[3]));
            if (is_string($decoded) && $decoded !== '') {
                $value = str_replace($match[0], $decoded, $value);
            }
        }
    }

    return substr(trim($value), 0, 255);
}

$poll = mailimap_poll($limit, true);
if (!$poll['ok']) {
    echo "IMAP: " . $poll['error'] . "\n";
    exit;
}

echo 'mailbox reached at ' . $poll['host'] . ', ' . count($poll['messages']) . " new message(s)\n";

$archived = 0;
$forwarded = 0;
$held      = 0;

foreach ($poll['messages'] as $message) {
    $parts   = mailpipe_split($message['raw']);
    $headers = $parts['headers'];

    $from    = mailpipe_address((string)($headers['from'] ?? ''));
    $to      = mailpipe_address((string)($headers['to'] ?? ''));
    $subject = mailpipe_subject((string)($headers['subject'] ?? ''));
    $body    = $parts['body'];

    // Which holder does this belong to? The address is <something>@<name>.freedns.my
    $route = null;
    $at    = strrpos($to, '@');
    if ($at !== false && mailroute_table_ready()) {
        $localPart  = substr($to, 0, $at);
        $mailDomain = substr($to, $at + 1);

        // The exact address first, then any address of that name (the domain's owner is who counts).
        $route = db_one(
            'SELECT r.* FROM mail_routes r JOIN domains d ON d.id = r.domain_id
             WHERE LOWER(r.local_part) = LOWER(?) AND LOWER(d.name) = LOWER(?) LIMIT 1',
            [$localPart, $mailDomain]
        );
        if ($route === null) {
            $route = db_one(
                'SELECT r.* FROM mail_routes r JOIN domains d ON d.id = r.domain_id
                 WHERE LOWER(d.name) = LOWER(?) ORDER BY r.id ASC LIMIT 1',
                [$mailDomain]
            );
        }
    }

    $id = mailvault_store([
        'user_id'   => $route === null ? 0 : (int)$route['user_id'],
        'direction' => 'in',
        'mail_from' => $from,
        'mail_to'   => $to,
        'subject'   => $subject,
        'body'      => $body,
    ]);
    if ($id > 0) {
        $archived++;
    }

    $entry = $id > 0 ? mailvault_entry($id) : null;
    if ($entry !== null && (string)$entry['scan_state'] === 'dangerous') {
        $held++;
        echo '  HELD     #' . $id . ' from ' . $from . ' - ' . $subject . " (in the human queue)\n";
        continue;
    }

    if ($route === null) {
        echo '  NO ROUTE #' . $id . ' to ' . $to . " - archived, nothing to forward to\n";
        continue;
    }

    $target = trim((string)$route['forward_to']);
    if ($target === '') {
        echo '  NO TARGET #' . $id . " - the holder has not set an address\n";
        continue;
    }

    $text = "Forwarded by freedns.my\n"
        . "From:    " . $from . "\n"
        . "To:      " . $to . "\n"
        . "Subject: " . $subject . "\n"
        . "Archive: message #" . $id . " (kept for the registry's records)\n"
        . str_repeat('-', 60) . "\n\n"
        . $body;

    $sent = send_mail($target, '[fwd] ' . ($subject === '' ? '(no subject)' : $subject), $text);
    if ($sent) {
        $forwarded++;
        db_query('UPDATE mail_archive SET direction = \'out\' WHERE id = ?', [$id]);
        echo '  FORWARD  #' . $id . ' -> ' . $target . "\n";
    } else {
        echo '  FAILED   #' . $id . ' -> ' . $target . " (the mail server refused it; it stays archived)\n";
    }
}

$pruned = mailvault_prune(200);

echo "\narchived " . $archived . ', forwarded ' . $forwarded . ', held for a human ' . $held
    . ', deleted by age ' . (int)$pruned['deleted'] . ' (older than ' . (int)$pruned['days'] . " days)\n";
log_action('mail_pipeline', 'mail', null,
    $archived . ' archived, ' . $forwarded . ' forwarded, ' . $held . ' held, '
    . (int)$pruned['deleted'] . ' deleted by age');
