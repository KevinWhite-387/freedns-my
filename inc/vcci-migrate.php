<?php
declare(strict_types=1);

/**
 * freedns.my - move the domains of a vc.ci account into this registry.
 *
 * Called after a successful vc.ci sign-in or link (the page guards this with function_exists),
 * and from the "migrate now" button. It reads the vc.ci database directly - both projects live on
 * the same machine and the same MySQL server, so this is a read-only SELECT and nothing else.
 *
 * Connection details come from the settings (Admin -> Settings -> vc.ci):
 *   vcci_db_name   the vc.ci database, default "vc_ci"
 *   vcci_db_host   default 127.0.0.1
 *   vcci_db_user   a MySQL user that may SELECT it
 *   vcci_db_pass   its password
 *
 * Rules that matter:
 *   * only the names of THIS vc.ci account are touched (matched by vc.ci user id, then e-mail)
 *   * only active names are migrated by default (setting vcci_migrate_active_only)
 *   * everything already present is skipped, so running it again is harmless
 *   * A/AAAA/CNAME/TXT only - the record table is a strict ENUM, MX and friends are counted and
 *     reported instead of being dropped silently
 */

function vcci_migrate_enabled(): bool
{
    return setting_bool('vcci_auto_migrate', true) && vcci_columns_ready();
}

/** @return array{ok:bool,error:string,pdo:?PDO} */
function vcci_migrate_pdo(): array
{
    $host = trim((string)setting('vcci_db_host', '127.0.0.1'));
    $name = trim((string)setting('vcci_db_name', 'vc_ci'));
    $user = trim((string)setting('vcci_db_user', ''));
    $pass = (string)setting('vcci_db_pass', '');

    if ($name === '') {
        return ['ok' => false, 'error' => 'vcci_db_name is empty.', 'pdo' => null];
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Cannot read the vc.ci database: ' . $e->getMessage(), 'pdo' => null];
    }

    return ['ok' => true, 'error' => '', 'pdo' => $pdo];
}

/** Which vc.ci account belongs to this local user? */
function vcci_migrate_find_source(PDO $src, array $userRow): ?array
{
    $sub = trim((string)($userRow['vcci_id'] ?? ''));

    if ($sub !== '' && ctype_digit($sub)) {
        $stmt = $src->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$sub]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
    }
    $email = strtolower(trim((string)($userRow['vcci_email'] ?? '')));
    if ($email === '') {
        $email = strtolower(trim((string)($userRow['email'] ?? '')));
    }
    if ($email !== '') {
        $stmt = $src->prepare('SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
    }

    return null;
}

/** vc.ci stores the apex as "@"; this registry uses an empty sub. */
function vcci_migrate_sub(string $name, string $label): string
{
    $name = strtolower(rtrim(trim($name), '.'));
    if ($name === '' || $name === '@' || $name === strtolower($label)) {
        return '';
    }
    $suffix = '.' . strtolower($label);
    if (str_ends_with($name, $suffix)) {
        return substr($name, 0, -strlen($suffix));
    }

    return $name;
}

function vcci_migrate_value(string $type, string $content, $priority): string
{
    $content = trim($content);
    if (strtoupper($type) === 'MX' && preg_match('/^\d+\s+/', $content) !== 1) {
        return (int)($priority ?? 10) . ' ' . $content;
    }

    return $content;
}

function vcci_migrate_status(string $s): string
{
    return match (strtolower(trim($s))) {
        'active', 'approved', 'ok' => 'active',
        'suspended', 'hold'        => 'suspended',
        'rejected'                 => 'rejected',
        'deleted', 'removed'       => 'deleted',
        default                    => 'pending',
    };
}

/**
 * Migrate the domains of one local user.
 *
 * @return array{ok:bool,error:string,created:int,records:int,skipped:int,unsupported:array,zone:string}
 */
function vcci_migrate_user(int $userId, string $zone = 'vc.ci'): array
{
    $out = ['ok' => false, 'error' => '', 'created' => 0, 'records' => 0,
        'skipped' => 0, 'unsupported' => [], 'zone' => $zone];

    $userRow = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($userRow === null) {
        $out['error'] = 'Unknown user.';

        return $out;
    }
    if (trim((string)($userRow['vcci_id'] ?? '')) === '' && trim((string)($userRow['vcci_email'] ?? '')) === '') {
        $out['error'] = 'This account is not linked to vc.ci.';

        return $out;
    }

    $conn = vcci_migrate_pdo();
    if (!$conn['ok'] || $conn['pdo'] === null) {
        $out['error'] = $conn['error'];

        return $out;
    }
    $src = $conn['pdo'];

    $source = vcci_migrate_find_source($src, $userRow);
    if ($source === null) {
        $out['error'] = 'No matching vc.ci account was found (by id or e-mail).';

        return $out;
    }

    /* The target suffix has to exist, otherwise nothing can be attached to it. */
    $zoneRow = db_one('SELECT * FROM zones WHERE name = ?', [$zone]);
    if ($zoneRow === null) {
        $out['error'] = 'The zone ' . $zone . ' does not exist in this registry.';

        return $out;
    }

    $activeOnly = setting_bool('vcci_migrate_active_only', true);

    $stmt = $src->prepare('SELECT * FROM domains WHERE user_id = ? ORDER BY id ASC');
    $stmt->execute([(int)$source['id']]);
    $domains = $stmt->fetchAll();

    $allowed = ['A', 'AAAA', 'CNAME', 'TXT'];

    $findDomain = db()->prepare('SELECT id FROM domains WHERE name = ? LIMIT 1');
    $findRecord = db()->prepare('SELECT id FROM records WHERE domain_id = ? AND sub = ? AND type = ? AND value = ? LIMIT 1');
    // The columns this registry actually has. The vc.ci row supplies the name, the registrant and
    // the creation date; everything else is a policy decision (it is a free registry, so no
    // provider owns the name and publishing happens later, on demand).
    $insertDomain = db()->prepare(
        'INSERT INTO domains (name, label, zone, language, reserved, proxied, delegated,
            status, request_code, provider_owned, registrant_name, registrant_email,
            justification, admin_note, created_at, updated_at, decided_at, decided_by, created_ip)
         VALUES (?, ?, ?, ?, 0, 0, 0,
            ?, ?, 0, ?, ?,
            ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertRecord = db()->prepare(
        'INSERT INTO records (domain_id, sub, type, value, ttl, position, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($domains as $d) {
            $label = strtolower(trim((string)($d['label'] ?? '')));
            $fqdn  = strtolower(trim((string)($d['fqdn'] ?? '')));
            $st    = vcci_migrate_status((string)($d['status'] ?? ''));
            if ($label === '' || $fqdn === '') {
                $out['skipped']++;
                continue;
            }
            if ($activeOnly && $st !== 'active') {
                $out['skipped']++;
                continue;
            }
            if ($fqdn !== $zone && !str_ends_with($fqdn, '.' . $zone)) {
                $out['skipped']++;
                continue;
            }

            $findDomain->execute([$fqdn]);
            $existing = $findDomain->fetch();
            if ($existing !== false) {
                $out['skipped']++;
                continue;
            }

            $created = (string)($d['created_at'] ?? date('Y-m-d H:i:s'));
            $insertDomain->execute([
                $fqdn, $label, $zone, 'en',
                $st,
                'VA-' . strtoupper(substr(sha1('vc.ci|' . $fqdn), 0, 10)),
                (string)($userRow['username'] ?? 'migrated'),
                (string)($userRow['email'] ?? ('migrated+' . $label . '@' . $zone)),
                'Migrated from vc.ci',
                'Migrated from vc.ci',
                $created, $created,
                $st === 'pending' ? null : $created,
                $st === 'pending' ? null : 'migration',
                null,
            ]);
            $domainId = (int)$pdo->lastInsertId();
            $out['created']++;

            $stmt2 = $src->prepare('SELECT * FROM records WHERE domain_id = ? ORDER BY id ASC');
            $stmt2->execute([(int)$d['id']]);
            $position = 0;
            foreach ($stmt2->fetchAll() as $r) {
                if (isset($r['status']) && strtolower((string)$r['status']) !== 'active') {
                    continue;
                }
                $sub   = vcci_migrate_sub((string)($r['name'] ?? '@'), $label);
                $type  = strtoupper(trim((string)($r['type'] ?? 'A')));
                $value = vcci_migrate_value($type, (string)($r['content'] ?? ''), $r['priority'] ?? null);
                if ($type === '' || $value === '') {
                    continue;
                }
                if (!in_array($type, $allowed, true)) {
                    $out['unsupported'][$type] = ($out['unsupported'][$type] ?? 0) + 1;
                    continue;
                }
                $ttl = (int)($r['ttl'] ?? 3600);
                if ($ttl < 60 || $ttl > 86400) {
                    $ttl = 3600;
                }
                $findRecord->execute([$domainId, $sub, $type, $value]);
                if ($findRecord->fetch() !== false) {
                    continue;
                }
                $position++;
                $insertRecord->execute([$domainId, $sub, $type, $value, $ttl, $position]);
                $out['records']++;
            }
        }

        if (vcci_columns_ready()) {
            db_query('UPDATE users SET vcci_migrated_at = NOW() WHERE id = ?', [$userId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $out['error'] = 'Migration failed: ' . $e->getMessage();

        return $out;
    }

    log_action('vcci_migrated', 'user', $userId,
        'domains=' . $out['created'] . ' records=' . $out['records'] . ' skipped=' . $out['skipped']);
    $out['ok'] = true;

    return $out;
}
