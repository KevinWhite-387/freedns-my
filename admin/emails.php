<?php
declare(strict_types=1);

/**
 * freedns.my - administration: export the e-mail addresses of the registrant accounts.
 *
 * The point is to hand a clean list to whatever sends the newsletter: no names, no passwords, no
 * domains, one address per line. Filters keep it honest - an operator can take only the confirmed
 * accounts, only the ones still pending, or everything.
 *
 * Two shapes are offered:
 *   text    one address per line (pastes straight into a Bcc field or a mail tool)
 *   csv     address,status,language,registered (for a spreadsheet)
 *   inline  one comma-separated line (for tools that want a single field)
 *
 * Nothing is written to disk: the file is streamed and the request ends before the page header, so
 * no HTML can end up inside the download.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('E-mail export');

$zh = function_exists('admin_lang') ? admin_lang() === 'zh' : false;
$L  = static function (string $en, string $zhText) use ($zh): string {
    return $zh ? $zhText : $en;
};

$ready  = function_exists('users_table_ready') && users_table_ready();
$status = strtolower(trim((string)($_GET['status'] ?? 'active')));
$lang   = strtolower(trim((string)($_GET['language'] ?? '')));
$format = strtolower(trim((string)($_GET['format'] ?? 'text')));
$unique = (string)($_GET['unique'] ?? '1') !== '0';

$allowedStatus = ['all', 'active', 'pending', 'suspended'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'active';
}
if (!in_array($format, ['text', 'csv', 'inline'], true)) {
    $format = 'text';
}

/* ------------------------------------------------------------------ query */
$sql    = 'SELECT email, status, language, created_at FROM users WHERE 1 = 1';
$params = [];
if ($status !== 'all') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
if ($lang !== '') {
    $sql .= ' AND language = ?';
    $params[] = $lang;
}
$sql .= ' ORDER BY id ASC';

$rows = $ready ? db_all($sql, $params) : [];

/* keep only things that look like an address, and de-duplicate case-insensitively */
$seen = [];
$out  = [];
foreach ($rows as $row) {
    $address = trim((string)($row['email'] ?? ''));
    if ($address === '' || str_contains($address, ' ') || !str_contains($address, '@')) {
        continue;
    }
    $key = strtolower($address);
    if ($unique && isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $out[] = [
        'email'    => $address,
        'status'   => (string)($row['status'] ?? ''),
        'language' => (string)($row['language'] ?? ''),
        'created'  => (string)($row['created_at'] ?? ''),
    ];
}

/* ------------------------------------------------------------------ stream */
$download = (string)($_GET['download'] ?? '') === '1';

if ($download) {
    $stamp = date('Ymd-His');
    $file  = 'freedns-emails-' . $status . '-' . $stamp;

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '.csv"');
        echo "\xEF\xBB\xBF";   // BOM so Excel opens Chinese text correctly
        echo "email,status,language,registered\n";
        foreach ($out as $row) {
            echo '"' . str_replace('"', '""', $row['email']) . '",'
                . '"' . $row['status'] . '",'
                . '"' . $row['language'] . '",'
                . '"' . $row['created'] . '"' . "\n";
        }
        exit;
    }

    if ($format === 'inline') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '.txt"');
        echo implode(', ', array_column($out, 'email')) . "\n";
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '.txt"');
    foreach ($out as $row) {
        echo $row['email'] . "\n";
    }
    exit;
}

/* ------------------------------------------------------------------ page */
$languages = [];
if ($ready) {
    foreach (db_all('SELECT DISTINCT language FROM users ORDER BY language ASC') as $row) {
        $code = strtolower(trim((string)($row['language'] ?? '')));
        if ($code !== '') {
            $languages[] = $code;
        }
    }
}

$base = url('admin/emails.php');
$link = static function (array $changes) use ($base, $status, $lang, $format, $unique): string {
    $q = [
        'status'   => $changes['status']   ?? $status,
        'language' => $changes['language'] ?? $lang,
        'format'   => $changes['format']   ?? $format,
        'unique'   => $changes['unique']   ?? ($unique ? '1' : '0'),
    ];
    if (($changes['download'] ?? '') === '1') {
        $q['download'] = '1';
    }

    return $base . '?' . http_build_query($q);
};

page_header($L('E-mail export', '导出用户邮箱'));
?>
<h1><?= h($L('E-mail export', '导出用户邮箱')) ?></h1>

<p class="small">
  <?= h($L(
      'Only the addresses, nothing else, so the file can go straight into a mailing tool. '
      . 'The list is filtered by account status and language, and duplicate addresses are dropped.',
      '只导出邮箱地址，方便直接拿去发信。可按账号状态和语言筛选，重复地址会自动去掉。'
  )) ?>
</p>

<?php if (!$ready): ?>

<div class="msg error"><?= h($L('The users table is missing.', '用户表不存在。')) ?></div>

<?php else: ?>

<h2><?= h($L('1. Which accounts', '一、选范围')) ?></h2>
<p>
<?php foreach ($allowedStatus as $one): ?>
  <?php if ($one === $status): ?>
    <strong><?= h($L($one, ['all' => '全部', 'active' => '已验证', 'pending' => '未验证', 'suspended' => '已封禁'][$one])) ?></strong>
  <?php else: ?>
    <a href="<?= h($link(['status' => $one])) ?>"><?= h($L($one, ['all' => '全部', 'active' => '已验证', 'pending' => '未验证', 'suspended' => '已封禁'][$one])) ?></a>
  <?php endif; ?>
  <?php if ($one !== end($allowedStatus)): ?> &middot; <?php endif; ?>
<?php endforeach; ?>
</p>

<?php if ($languages !== []): ?>
<h2><?= h($L('2. Language', '二、语言')) ?></h2>
<p>
  <?php if ($lang === ''): ?><strong><?= h($L('any', '不限')) ?></strong><?php else: ?>
    <a href="<?= h($link(['language' => ''])) ?>"><?= h($L('any', '不限')) ?></a>
  <?php endif; ?>
  <?php foreach ($languages as $code): ?>
    &middot;
    <?php if ($code === $lang): ?><strong><?= h($code) ?></strong><?php else: ?>
      <a href="<?= h($link(['language' => $code])) ?>"><?= h($code) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</p>
<?php endif; ?>

<h2><?= h($L('3. Format', '三、格式')) ?></h2>
<p>
  <?php $formats = ['text' => $L('one address per line', '每行一个地址'), 'inline' => $L('comma separated', '逗号分隔'), 'csv' => $L('CSV with status', 'CSV（含状态）')]; ?>
  <?php foreach ($formats as $k => $label): ?>
    <?php if ($k === $format): ?><strong><?= h($label) ?></strong><?php else: ?>
      <a href="<?= h($link(['format' => $k])) ?>"><?= h($label) ?></a>
    <?php endif; ?>
    <?php if ($k !== 'csv'): ?> &middot; <?php endif; ?>
  <?php endforeach; ?>
  &middot;
  <?php if ($unique): ?>
    <strong><?= h($L('duplicates removed', '已去重')) ?></strong> &middot;
    <a href="<?= h($link(['unique' => '0'])) ?>"><?= h($L('keep duplicates', '保留重复')) ?></a>
  <?php else: ?>
    <a href="<?= h($link(['unique' => '1'])) ?>"><?= h($L('duplicates removed', '去重')) ?></a> &middot;
    <strong><?= h($L('keep duplicates', '保留重复')) ?></strong>
  <?php endif; ?>
</p>

<h2><?= h($L('4. Download', '四、下载')) ?></h2>
<p>
  <strong><?= (int)count($out) ?></strong>
  <?= h($L('address(es) match.', '个邮箱符合条件。')) ?>
</p>
<p class="actions">
  <a class="button" href="<?= h($link(['download' => '1'])) ?>"><?= h($L('Download the list', '下载邮箱列表')) ?></a>
</p>

<h2><?= h($L('Preview', '预览')) ?></h2>
<?php if ($out === []): ?>
<p class="small"><?= h($L('Nothing matches these filters.', '当前条件没有匹配的账号。')) ?></p>
<?php else: ?>
<pre style="max-height:22em;overflow:auto;border:1px solid #ccc;padding:.6em"><?php
$preview = array_slice($out, 0, 50);
if ($format === 'inline') {
    echo h(implode(', ', array_column($preview, 'email')));
} elseif ($format === 'csv') {
    echo h("email,status,language,registered\n");
    foreach ($preview as $row) {
        echo h($row['email'] . ',' . $row['status'] . ',' . $row['language'] . ',' . $row['created'] . "\n");
    }
} else {
    foreach ($preview as $row) {
        echo h($row['email']) . "\n";
    }
}
?></pre>
<p class="small"><?= h($L(
    'Showing the first 50 of ' . count($out) . '; the download contains all of them.',
    '这里只显示前 50 条（共 ' . count($out) . ' 条），下载的文件是全部。'
)) ?></p>
<?php endif; ?>

<p class="small"><?= h($L(
    'Addresses marked suspended are included only when you pick that filter: they can still not sign in.',
    '「已封禁」的地址只有在选择该筛选时才会导出；它们依然无法登录。'
)) ?></p>

<?php endif; ?>

<p><a href="<?= h(url('admin/users.php')) ?>"><?= h($L('Back to user accounts', '返回用户管理')) ?></a></p>
<?php
page_footer();