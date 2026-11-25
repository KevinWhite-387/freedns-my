<?php
declare(strict_types=1);

/**
 * freedns.my - migrate the domains of a vc.ci installation into this registry.
 *
 * What it does
 *   vc.ci `domains`  -> this registry `domains`   (label / fqdn / status / created_at / provider_ref)
 *   vc.ci `records`  -> this registry `records`   (name -> sub, type, content -> value, MX priority)
 *   vc.ci `users`    -> only to fill registrant_name / registrant_email, no user is created here
 *
 * Font of truth note: this registry keeps live DNS in `records` (`sub` empty = the apex) and only
 * the provider mapping in `dns_records`. The migration writes `records`; the mapping is filled the
 * first time a name is published, so nothing has to be faked there.
 *
 * Usage
 *   php migrate-vcci.php \
 *       --from-host=127.0.0.1 --from-db=vc_ci   --from-user=root --from-pass=secret \
 *       --to-host=127.0.0.1   --to-db=freedns_my --to-user=root --to-pass=secret \
 *       [--zone=vc.ci] [--status=active|all] [--limit=0] [--apply]
 *
 *   Without --apply it only reports what it would do (dry run). Everything is idempotent:
 *   a name that already exists is skipped, a record that already exists is skipped.
 *
 * Exit codes: 0 fine, 1 usage or connection problem, 2 something failed half way.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs on the command line only.\n");
}

/* ------------------------------------------------------------------ options */

$opt = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $pair = explode('=', substr($arg, 2), 2);
        $opt[$pair[0]] = $pair[1] ?? '1';
    }
}

$fromHost = (string)($opt['from-host'] ?? '127.0.0.1');
$fromDb   = (string)($opt['from-db'] ?? '');
$fromUser = (string)($opt['from-user'] ?? '');
$fromPass = (string)($opt['from-pass'] ?? '');
$toHost   = (string)($opt['to-host'] ?? '127.0.0.1');
$toDb     = (string)($opt['to-db'] ?? '');
$toUser   = (string)($opt['to-user'] ?? '');
$toPass   = (string)($opt['to-pass'] ?? '');
$zone     = strtolower(trim((string)($opt['zone'] ?? 'vc.ci')));
$status   = strtolower(trim((string)($opt['status'] ?? 'all')));
$limit    = (int)($opt['limit'] ?? 0);
$apply    = isset($opt['apply']);

if ($fromDb === '' || $toDb === '' || $zone === '') {
    fwrite(STDERR, "Missing --from-db / --to-db / --zone.\n\n"
        . "Example:\n"
        . "  php migrate-vcci.php --from-db=vc_ci --from-user=root --from-pass=SECRET \\\n"
        . "      --to-db=freedns_my --to-user=root --to-pass=SECRET --zone=vc.ci\n"
        . "Add --apply to actually write; without it this is a dry run.\n");
    exit(1);
}

function say(string $line): void
{
    echo $line . "\n";
}

/* --------------------------------------------------------------- connection */

function pdo_for(string $host, string $db, string $user, string $pass, string $label): PDO
{
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cannot connect to ' . $label . ': ' . $e->getMessage() . "\n");
        exit(1);
    }

    return $pdo;
}

$from = pdo_for($fromHost, $fromDb, $fromUser, $fromPass, 'vc.ci (' . $fromDb . ')');
$to   = pdo_for($toHost, $toDb, $toUser, $toPass, 'freedns (' . $toDb . ')');

/* ------------------------------------------------------------ sanity checks */

$need = static function (PDO $pdo, string $table) : bool {
    try {
        $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
};

foreach ([['domains'], ['records'], ['users']] as $pair) {
    if (!$need($from, $pair[0])) {
        fwrite(STDERR, 'The vc.ci database has no `' . $pair[0] . "` table.\n");
        exit(1);
    }
}
foreach ([['domains'], ['records']] as $pair) {
    if (!$need($to, $pair[0])) {
        fwrite(STDERR, 'The freedns database has no `' . $pair[0] . "` table - is this the right database?\n");
        exit(1);
    }
}

/* The target zone has to exist, otherwise every name would point at nothing. */
$zoneRow = null;
try {
    $stmt = $to->prepare('SELECT * FROM zones WHERE name = ?');
    $stmt->execute([$zone]);
    $zoneRow = $stmt->fetch() ?: null;
} catch (Throwable $e) {
    $zoneRow = null;
}

say('==================================================================');
say(' vc.ci -> freedns migration' . ($apply ? '   [APPLY]' : '   [DRY RUN]'));
say('==================================================================');
say('  from      : ' . $fromHost . '/' . $fromDb);
say('  to        : ' . $toHost . '/' . $toDb);
say('  zone      : ' . $zone . ($zoneRow === null ? '   <-- MISSING IN zones!' : '   (id ' . $zoneRow['id'] . ')'));
say('  statuses  : ' . $status);
say('  mode      : ' . ($apply ? 'writes to the database' : 'reads only'));
say('------------------------------------------------------------------');

if ($zoneRow === null) {
    fwrite(STDERR, "The zone `" . $zone . "` does not exist in the target database.\n"
        . "Create it first (Admin -> Zones) or pass --zone=<existing suffix>.\n");
    exit(1);
}

/* ---------------------------------------------------------------- migration */

$sqlDomains = 'SELECT d.*, u.username AS vc_username, u.email AS vc_email
               FROM domains d LEFT JOIN users u ON u.id = d.user_id';
$where = [];
$args  = [];
if ($status !== 'all' && $status !== '') {
    $where[] = 'd.status = ?';
    $args[]  = $status;
}
if ($where !== []) {
    $sqlDomains .= ' WHERE ' . implode(' AND ', $where);
}
$sqlDomains .= ' ORDER BY d.id ASC';
if ($limit > 0) {
    $sqlDomains .= ' LIMIT ' . $limit;
}

$stmt = $from->prepare($sqlDomains);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$stats = ['seen' => 0, 'created' => 0, 'skipped' => 0, 'records' => 0, 'rec_skipped' => 0, 'failed' => 0, 'unsupported' => []];

$findDomain = $to->prepare('SELECT id FROM domains WHERE name = ? LIMIT 1');
$findRecord = $to->prepare('SELECT id FROM records WHERE domain_id = ? AND sub = ? AND type = ? AND value = ? LIMIT 1');

$insertDomain = $to->prepare(
    'INSERT INTO domains (name, label, zone, language, reserved, proxied, delegated, nameservers, glue,
        status, request_code, provider_ref, provider_owned, ref_code, registrant_name, registrant_email,
        organization, country, justification, public_note, admin_note, created_at, updated_at,
        decided_at, decided_by, created_ip)
     VALUES (?, ?, ?, ?, 0, 0, 0, NULL, NULL,
        ?, ?, ?, 1, NULL, ?, ?,
        NULL, NULL, ?, NULL, ?, ?, ?, ?,
        ?, ?, NULL)'
);

/* The live-DNS table accepts A, AAAA, CNAME and TXT only (it is a strict ENUM) and has no
 * proxied column - that one lives on domains. Anything else is counted and reported instead of
 * being silently dropped. */
$allowedTypes = ['A', 'AAAA', 'CNAME', 'TXT'];

$insertRecord = $to->prepare(
    'INSERT INTO records (domain_id, sub, type, value, ttl, position, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
);

/** vc.ci stores the apex as "@"; this registry uses an empty sub. */
$toSub = static function (string $name, string $label): string {
    $name = strtolower(trim($name));
    if ($name === '' || $name === '@') {
        return '';
    }
    if ($name === strtolower($label)) {
        return '';
    }
    $suffix = '.' . strtolower($label);
    if (str_ends_with($name, $suffix)) {
        return substr($name, 0, -strlen($suffix));
    }

    return $name;
};

/** MX keeps its priority inside the value in this registry. */
$toValue = static function (string $type, string $content, $priority): string {
    $content = trim($content);
    if (strtoupper($type) === 'MX' && preg_match('/^\d+\s+/', $content) !== 1) {
        return (int)($priority ?? 10) . ' ' . $content;
    }

    return $content;
};

$statusMap = static function (string $s): string {
    $s = strtolower(trim($s));

    return match ($s) {
        'active', 'approved', 'ok'      => 'active',
        'suspended', 'hold'             => 'suspended',
        'rejected'                      => 'rejected',
        'deleted', 'removed'            => 'deleted',
        default                         => 'pending',
    };
};

if ($apply) {
    $to->beginTransaction();
}

try {
    foreach ($rows as $d) {
        $stats['seen']++;

        $label = strtolower(trim((string)$d['label']));
        $fqdn  = strtolower(trim((string)$d['fqdn']));
        if ($label === '' || $fqdn === '') {
            $stats['skipped']++;
            continue;
        }
        /* Only names that belong to the chosen zone. */
        if ($fqdn !== $zone && !str_ends_with($fqdn, '.' . $zone)) {
            say('  skip (other zone): ' . $fqdn);
            $stats['skipped']++;
            continue;
        }

        $findDomain->execute([$fqdn]);
        $existing = $findDomain->fetch();
        if ($existing !== false) {
            say('  skip (exists):     ' . $fqdn);
            $stats['skipped']++;
            continue;
        }

        $regName  = trim((string)($d['vc_username'] ?? '')) !== '' ? (string)$d['vc_username'] : 'migrated';
        $regEmail = trim((string)($d['vc_email'] ?? '')) !== '' ? (string)$d['vc_email'] : ('migrated+' . $label . '@' . $zone);
        $code     = 'VA-' . strtoupper(substr(sha1('vc.ci|' . $fqdn), 0, 10));
        $created  = (string)($d['created_at'] ?? date('Y-m-d H:i:s'));
        $mStatus  = $statusMap((string)$d['status']);

        say(sprintf('  %-24s %-8s %s', $fqdn, $mStatus, $apply ? 'creating' : 'would create'));

        if ($apply) {
            $insertDomain->execute([
                $fqdn,                 // name
                $label,                // label
                $zone,                 // zone
                'en',                  // language
                $mStatus,              // status
                $code,                 // request_code
                ($d['provider_ref'] ?? null) === null || (string)$d['provider_ref'] === ''
                    ? null : (string)$d['provider_ref'],
                $regName,              // registrant_name
                $regEmail,             // registrant_email
                'Migrated from vc.ci', // justification
                'Migrated from vc.ci', // admin_note
                $created,              // created_at
                $created,              // updated_at
                $mStatus === 'pending' ? null : $created,  // decided_at
                $mStatus === 'pending' ? null : 'migration',
            ]);
            $domainId = (int)$to->lastInsertId();
        } else {
            $domainId = 0;
        }
        $stats['created']++;

        /* Records. */
        $stmt2 = $from->prepare('SELECT * FROM records WHERE domain_id = ? ORDER BY id ASC');
        $stmt2->execute([(int)$d['id']]);
        $position = 0;
        foreach ($stmt2->fetchAll() as $r) {
            if (isset($r['status']) && strtolower((string)$r['status']) !== 'active') {
                $stats['rec_skipped']++;
                continue;
            }
            $sub   = $toSub((string)($r['name'] ?? '@'), $label);
            $type  = strtoupper(trim((string)($r['type'] ?? 'A')));
            $value = $toValue($type, (string)($r['content'] ?? ''), $r['priority'] ?? null);
            if ($type === '' || $value === '') {
                $stats['rec_skipped']++;
                continue;
            }
            if (!in_array($type, $allowedTypes, true)) {
                /* MX / NS / SRV / CAA cannot be stored in this registry's record table. */
                $stats['unsupported'][$type] = ($stats['unsupported'][$type] ?? 0) + 1;
                $stats['rec_skipped']++;
                continue;
            }
            $ttl = (int)($r['ttl'] ?? 3600);
            if ($ttl < 60 || $ttl > 86400) {
                $ttl = 3600;
            }
            if ($apply) {
                $findRecord->execute([$domainId, $sub, $type, $value]);
                if ($findRecord->fetch() !== false) {
                    $stats['rec_skipped']++;
                    continue;
                }
                $position++;
                $insertRecord->execute([$domainId, $sub, $type, $value, $ttl, $position]);
            }
            $stats['records']++;
        }
    }

    if ($apply) {
        $to->commit();
    }
} catch (Throwable $e) {
    if ($apply && $to->inTransaction()) {
        $to->rollBack();
    }
    fwrite(STDERR, "\nFAILED: " . $e->getMessage() . "\n");
    if ($apply) {
        fwrite(STDERR, "Everything was rolled back - the database is unchanged.\n");
    }
    exit(2);
}

say('------------------------------------------------------------------');
say('  domains seen        : ' . $stats['seen']);
say('  domains ' . ($apply ? 'created' : 'to create') . '     : ' . $stats['created']);
say('  domains skipped     : ' . $stats['skipped']);
say('  records ' . ($apply ? 'written' : 'to write') . '     : ' . $stats['records']);
say('  records skipped     : ' . $stats['rec_skipped']);
if ($stats['unsupported'] !== []) {
    say('');
    say('  NOT migrated (this registry stores A / AAAA / CNAME / TXT only):');
    foreach ($stats['unsupported'] as $ty => $cnt) {
        say('    ' . str_pad((string)$ty, 8) . $cnt);
    }
    say('  Add those by hand, or keep them at the DNS provider.');
}
say('==================================================================');

if (!$apply) {
    say('');
    say('This was a dry run. Nothing was written.');
    say('Run the same command with --apply to migrate.');
    say('');
    say('Afterwards, publish the names once so the DNS provider gets the records:');
    say('  Admin -> DNS -> "Sync" (or run the reconcile cron)');
}

exit(0);
