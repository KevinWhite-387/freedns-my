<?php
declare(strict_types=1);

/**
 * freedns.my - the dynamic update endpoint.
 *
 * GET /ddns.php?token=SECRET&ip=1.2.3.4[&ipv6=2001:db8::1][&host=name.zone]
 *
 * Answers with the plain wording every DDNS client understands:
 *   good <ip>    updated        nochg <ip>  nothing to change
 *   badauth      wrong token    nohost      unknown name / no matching record
 *   badip        bad address    911         stored, but publishing failed
 *
 * It is deliberately outside the visitor pages: no session, no cookie, no HTML.
 */

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (!ddns_enabled()) {
    http_response_code(503);
    echo "911 ddns disabled\n";
    exit;
}

$token = trim((string)($_REQUEST['token'] ?? ''));
$host  = strtolower(trim((string)($_REQUEST['host'] ?? $_REQUEST['hostname'] ?? '')));

// "myip" and "ip" are the names different clients use; some send the address as the value of "myip".
$ipv4 = trim((string)($_REQUEST['ip'] ?? $_REQUEST['myip'] ?? ''));
$ipv6 = trim((string)($_REQUEST['ipv6'] ?? $_REQUEST['myipv6'] ?? ''));

// A client may send only "myip" with an IPv6 address in it.
if ($ipv4 !== '' && str_contains($ipv4, ':')) {
    if ($ipv6 === '') {
        $ipv6 = $ipv4;
    }
    $ipv4 = '';
}

if ($token === '') {
    http_response_code(401);
    echo "badauth\n";
    exit;
}

$domain = ddns_domain_by_token($token);
if ($domain === null) {
    http_response_code(401);
    echo "badauth\n";
    exit;
}
if ($host !== '' && $host !== strtolower((string)$domain['name'])) {
    http_response_code(404);
    echo "nohost\n";
    exit;
}
if (ddns_throttled($domain)) {
    echo "nochg " . ($ipv4 !== '' ? $ipv4 : $ipv6) . "\n";
    exit;
}

$result = ddns_apply($domain, $ipv4, $ipv6);

switch ($result['status']) {
    case 'good':
        echo 'good ' . $result['ip'] . "\n";
        break;
    case 'nochg':
        echo 'nochg ' . $result['ip'] . "\n";
        break;
    case 'badauth':
        http_response_code(401);
        echo "badauth\n";
        break;
    case 'nohost':
        http_response_code(404);
        echo "nohost\n";
        break;
    case 'badip':
        http_response_code(400);
        echo 'badip ' . $result['ip'] . "\n";
        break;
    default:
        http_response_code(500);
        echo '911 ' . $result['message'] . "\n";
}
