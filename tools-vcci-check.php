<?php
declare(strict_types=1);

/**
 * freedns.my - why did the vc.ci migration not move anything?
 *
 * Run from the shell, as the web user, in the site root:
 *
 *   sudo -u www php tools-vcci-check.php                 # report only
 *   sudo -u www php tools-vcci-check.php --apply         # report, then actually migrate
 *   sudo -u www php tools-vcci-check.php --user=admin    # check one local account (default: all bound)
 *
 * It walks the same steps vcci_migrate_user() walks and prints where it stops.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/inc/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$only  = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--user=')) {
        $only = substr($arg, 7);
    }
}

function line(string $label, string $value = ''): void
{
    printf("%-34s %s\n", $label, $value);
}

echo "=== freedns.my / vc.ci migration check ===\n\n";

/* ------------------------------------------------------------------ 1. settings */
echo "1) settings\n";
foreach (['vcci_db_host', 'vcci_db_name', 'vcci_db_user', 'vcci_db_pass', 'vcci_migrate_active_only'] as $key) {
    $val = (string)setting($key, '');
    if ($key === 'vcci_db_pass') {
        $val = $val === '' ? '(empty)' : '(set, ' . strlen($val) . ' chars)';
    }
    line('   ' . $key, $val === '' ? '(empty)' : $val);
}

/* ------------------------------------------------------------------ 2. connection */
echo "\n2) vc.ci database\n";
$conn = vcci_migrate_pdo();
if (!$conn['ok']) {
    line('   connect', 'FAILED');
    line('   error', (string)$conn['error']);
    echo "\n=> 卡在这里：数据库连不上。把上面 error 里的原因处理掉再跑。\n";
    exit(1);
}
line('   connect', 'OK');
$src = $conn['pdo'];
try {
    $u = (int)$src->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $d = (int)$src->query('SELECT COUNT(*) FROM domains')->fetchColumn();
    line('   vc.ci users', (string)$u);
    line('   vc.ci domains', (string)$d);
} catch (Throwable $e) {
    line('   read tables', 'FAILED: ' . $e->getMessage());
    echo "\n=> 卡在这里：连上了但读不到 users/domains 表（库名对不对？）\n";
    exit(1);
}
try {
    $rows = $src->query('SELECT status, COUNT(*) AS n FROM domains GROUP BY status')->fetchAll();
    $bits = [];
    foreach ($rows as $row) {
        $bits[] = (string)$row['status'] . '=' . (int)$row['n'];
    }
    line('   domains by status', $bits === [] ? '(none)' : implode(', ', $bits));
} catch (Throwable $e) {
    line('   domains status', 'n/a');
}
try {
    $cols = $src->query('SHOW COLUMNS FROM domains')->fetchAll();
    $names = [];
    foreach ($cols as $col) {
        $names[] = (string)$col['Field'];
    }
    line('   domains columns', implode(', ', $names));
} catch (Throwable $e) {
    line('   domains columns', 'n/a');
}
try {
    $cols = $src->query('SHOW COLUMNS FROM records')->fetchAll();
    $names = [];
    foreach ($cols as $col) {
        $names[] = (string)$col['Field'];
    }
    line('   records columns', implode(', ', $names));
} catch (Throwable $e) {
    line('   records columns', 'n/a');
}

/* ------------------------------------------------------------------ 3. target zone */
echo "\n3) target zone\n";
$zone = 'vc.ci';
$zoneRow = db_one('SELECT id, name, title FROM zones WHERE name = ?', [$zone]);
if ($zoneRow === null) {
    line('   zone vc.ci', 'MISSING');
    echo "\n=> 卡在这里：本站 zones 表里没有 vc.ci，迁移无法挂靠。\n";
    exit(1);
}
line('   zone vc.ci', 'OK (id ' . (int)$zoneRow['id'] . ')');

/* ------------------------------------------------------------------ 4. bound users */
echo "\n4) local accounts that are linked to vc.ci\n";
$sql = 'SELECT id, username, email, vcci_id, vcci_email, vcci_migrated_at FROM users'
     . ' WHERE (vcci_id IS NOT NULL AND vcci_id <> \'\') OR (vcci_email IS NOT NULL AND vcci_email <> \'\')';
$params = [];
if ($only !== '') {
    $sql .= ' AND (username = ? OR email = ?)';
    $params = [$only, $only];
}
$bound = db_all($sql, $params);
if ($bound === []) {
    line('   linked accounts', 'NONE');
    echo "\n=> 卡在这里：本库里没有任何账号带 vcci_id / vcci_email。\n"
       . "   先到 https://freedns.my/account/vcci.php?do=link 绑定一次，再跑这个脚本。\n";
    exit(1);
}
foreach ($bound as $u2) {
    printf(
        "   #%d %-18s vcci_id=%-10s vcci_email=%-24s migrated_at=%s\n",
        (int)$u2['id'],
        (string)$u2['username'],
        (string)($u2['vcci_id'] !== '' ? $u2['vcci_id'] : '—'),
        (string)($u2['vcci_email'] !== '' ? $u2['vcci_email'] : '—'),
        (string)($u2['vcci_migrated_at'] !== '' ? $u2['vcci_migrated_at'] : '—')
    );
}

/* ------------------------------------------------------------------ 5. source account */
echo "\n5) matching vc.ci account and their domains\n";
foreach ($bound as $u2) {
    $source = vcci_migrate_find_source($src, $u2);
    printf("   local #%d %s -> ", (int)$u2['id'], (string)$u2['username']);
    if ($source === null) {
        echo "NO MATCH (按 vcci_id 和邮箱都找不到 vc.ci 用户)\n";
        continue;
    }
    echo 'vc.ci user #' . (int)$source['id'] . ' ' . (string)($source['username'] ?? '') . "\n";
    $stmt = $src->prepare('SELECT * FROM domains WHERE user_id = ? ORDER BY id ASC');
    $stmt->execute([(int)$source['id']]);
    $doms = $stmt->fetchAll();
    if ($doms === []) {
        echo "      -> 这个 vc.ci 账号名下 0 个域名（可能域名在别的 user_id 上）\n";
        continue;
    }
    foreach ($doms as $dom) {
        $exists = db_one('SELECT id FROM domains WHERE name = ? LIMIT 1', [(string)$dom['name']]);
        $fdLabel = (string)($dom['label'] ?? '');
        $fdFqdn  = (string)($dom['fqdn'] ?? '');
        $fdName  = $fdFqdn !== '' ? $fdFqdn : ($fdLabel !== '' ? $fdLabel . '.' : '?');
        printf(
            "      %-30s status=%-10s %s\n",
            $fdName,
            (string)($dom['status'] ?? ''),
            $exists !== null ? '已存在于本站（会跳过）' : '未迁移'
        );
    }
    if (setting_bool('vcci_migrate_active_only', true)) {
        echo "      注意：vcci_migrate_active_only=1，只会迁 status=active 的域名\n";
    }
}

/* ------------------------------------------------------------------ 6. run it */
echo "\n6) migration\n";
foreach ($bound as $u2) {
    if (!$apply) {
        line('   dry run for #' . (int)$u2['id'], 'skipped (加 --apply 才会真的写库)');
        continue;
    }
    $summary = vcci_migrate_user((int)$u2['id']);
    printf(
        "   #%d ok=%s created=%d records=%d skipped=%d error=%s\n",
        (int)$u2['id'],
        !empty($summary['ok']) ? 'yes' : 'NO',
        (int)($summary['created'] ?? 0),
        (int)($summary['records'] ?? 0),
        (int)($summary['skipped'] ?? 0),
        (string)($summary['error'] ?? '')
    );
}

echo "\n=== done ===\n";