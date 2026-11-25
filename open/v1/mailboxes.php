<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the mailboxes a holder has on this registry.
 *
 *   GET /open/v1/mailboxes.php          scope: mailboxes
 *   Authorization: Bearer <access token>
 *
 * Addresses, state and quota - never a password, never a message. The mailbox tables are only read when they are
 * actually there: an installation that never used the mail feature answers with an empty list and a note, instead
 * of an error a client would have to special-case.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

if (op_unavailable()) {
    op_error('temporarily_unavailable', 'The open platform is not set up yet.', 503);
}

$input = op_input();
$plain = op_token_from_request($input);
if ($plain === '') {
    header('WWW-Authenticate: Bearer realm="freedns.my"');
    op_error('invalid_token', 'Send the access token: Authorization: Bearer <token>.', 401);
}

$auth = op_token_verify($plain, 'mailboxes');
if ($auth === null) {
    header('WWW-Authenticate: Bearer realm="freedns.my", error="insufficient_scope"');
    op_error('insufficient_scope', 'This token may not read the mailboxes of this account.', 403);
}

$user   = $auth['user'];
$userId = (int)$user['id'];
$email  = strtolower((string)$user['email']);

/** Is there a table with this name? (Nothing here may explode on an installation without the mail feature.) */
$has = static function (string $table): bool {
    try {
        return db_one('SHOW TABLES LIKE ?', [$table]) !== null;
    } catch (Throwable $e) {
        return false;
    }
};

$out   = [];
$notes = [];

// The mailboxes themselves. Column names differ between releases, so each row is shaped defensively.
if ($has('mail_boxes')) {
    try {
        $rows = db_all('SELECT * FROM mail_boxes WHERE user_id = ? ORDER BY address ASC', [$userId]);
        foreach ($rows as $row) {
            $out[] = [
                'address'    => (string)($row['address'] ?? $row['email'] ?? ''),
                'local_part' => (string)($row['local_part'] ?? ''),
                'domain'     => (string)($row['domain'] ?? ''),
                'status'     => (string)($row['status'] ?? ''),
                'quota_mb'   => isset($row['quota_mb']) ? (int)$row['quota_mb'] : null,
                'used_mb'    => isset($row['used_mb']) ? (int)$row['used_mb'] : null,
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $notes[] = 'The mailbox table could not be read.';
    }
} else {
    $notes[] = 'This installation has no mailbox table.';
}

// Requests for a mailbox that are still being reviewed, so a client can show "waiting".
$requests = [];
if ($has('mail_box_requests')) {
    try {
        foreach (db_all('SELECT * FROM mail_box_requests WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]) as $row) {
            $requests[] = [
                'address'    => (string)($row['address'] ?? $row['email'] ?? ''),
                'status'     => (string)($row['status'] ?? ''),
                'note'       => (string)($row['note'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $notes[] = 'The mailbox request table could not be read.';
    }
}

// Forwarding addresses that this account owns, when that feature is in use.
$forwards = [];
if ($has('mail_routes')) {
    try {
        foreach (db_all('SELECT * FROM mail_routes WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$userId]) as $row) {
            $forwards[] = [
                'local_part' => (string)($row['local_part'] ?? ''),
                'forward_to' => (string)($row['forward_to'] ?? ''),
                'status'     => (string)($row['status'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $notes[] = 'The forwarding table could not be read.';
    }
}

// Which addresses on this registry belong to the account's own names: useful when there is no mailbox table.
$addresses = [];
foreach (user_domains($user) as $domain) {
    if ((int)($domain['id'] ?? 0) > 0) {
        $addresses[] = (string)$domain['name'];
    }
}

op_send([
    'ok'         => true,
    'email'      => $email,
    'mailboxes'  => $out,
    'requests'   => $requests,
    'forwarding' => $forwards,
    'names'      => $addresses,
    'notes'      => $notes,
]);
