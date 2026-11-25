<?php
declare(strict_types=1);

/**
 * freedns.my - public list of active domains with their records.
 */

require __DIR__ . '/inc/bootstrap.php';

$zones    = zone_list();
$q        = trim((string)($_GET['q'] ?? ''));
$zoneName = strtolower(trim((string)($_GET['zone'] ?? '')));
$letter   = strtoupper(trim((string)($_GET['letter'] ?? 'ALL')));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = max(20, min(1000, (int)setting('listing_page_size', '200')));

$where  = ["status = 'active'"];
$params = [];

if ($q !== '') {
    $where[]  = 'name LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}

if ($zoneName !== '' && zone_by_name($zoneName) !== null) {
    $where[]  = 'zone = ?';
    $params[] = $zoneName;
} else {
    $zoneName = '';
}

if ($letter === '0-9') {
    $where[] = "label REGEXP '^[0-9]'";
} elseif ($letter !== '' && $letter !== 'ALL' && preg_match('/^[A-Z]$/', $letter)) {
    $where[]  = 'label LIKE ?';
    $params[] = strtolower($letter) . '%';
}

$sql   = 'FROM domains WHERE ' . implode(' AND ', $where);
$total = (int)db_value('SELECT COUNT(*) ' . $sql, $params, 0);
$pager = paginate($total, $perPage, $page);

$rows = db_all(
    'SELECT id, name, label, zone, organization, decided_at, created_at ' . $sql
    . ' ORDER BY zone ASC, label ASC LIMIT ' . (int)$pager['per_page'] . ' OFFSET ' . (int)$pager['offset'],
    $params
);

$recordsByDomain = [];
if ($rows !== []) {
    $ids          = array_map(static fn($r) => (int)$r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all(
        'SELECT domain_id, sub, type, value FROM records WHERE domain_id IN (' . $placeholders . ')
         ORDER BY domain_id ASC, sub ASC, type ASC, position ASC',
        $ids
    ) as $record) {
        $recordsByDomain[(int)$record['domain_id']][] = $record;
    }
}

/** Compact "www A 203.0.113.7" style summary of a record; addresses are partially hidden. */
$summarise = static function (array $record): string {
    $type  = strtoupper((string)($record['type'] ?? ''));
    $value = (string)($record['value'] ?? '');
    if ($type === 'A' || $type === 'AAAA') {
        $value = mask_address($value);
    }

    return record_sub_display((string)($record['sub'] ?? '')) . ' '
        . $type . ' ' . $value;
};

$whereText  = $zoneName === '' ? t('list.where_all') : t('list.where_zone', h($zoneName));
$filterText = ($q === '' && ($letter === 'ALL' || $letter === '')) ? '' : t('list.filtered');

page_header(t('list.heading'));

?>

<?php /* Sub-pages of the Domains entry: the five-item menu keeps them one level down. */ ?>
<p class="small">
  <a href="<?= h(url('request.php')) ?>"><?= h(function_exists('lang') && is_zh() ? '申请域名' : 'Request a name') ?></a>
  &middot; <strong><?= h(function_exists('lang') && is_zh() ? '生效中的域名' : 'Active names') ?></strong>
  &middot; <a href="<?= h(url('account/index.php')) ?>"><?= h(function_exists('lang') && is_zh() ? '我的域名与审核状态' : 'My names and review status') ?></a>
  &middot; <a href="<?= h(url('whois.php')) ?>">WHOIS</a>
  &middot; <a href="<?= h(url('abuse.php')) ?>"><?= h(function_exists('lang') && is_zh() ? '举报' : 'Report') ?></a>
</p>
<p>
<?= $total === 1
    ? t('list.count_one', $whereText, $filterText)
    : t('list.count_many', number_format($total), $whereText, $filterText) ?>
<?= h(t('list.also')) ?>
</p>

<form method="get" action="<?= h(url('domains.php')) ?>">
<label for="q"><?= h(t('list.search')) ?></label>
<input type="text" id="q" name="q" value="<?= h($q) ?>" maxlength="80">

<?php if (count($zones) > 1): ?>
<label for="zone"><?= h(t('list.zone')) ?></label>
<select id="zone" name="zone">
  <option value=""><?= h(t('list.all_zones')) ?></option>
  <?php foreach ($zones as $zone): ?>
  <option value="<?= h((string)$zone['name']) ?>"<?= $zoneName === strtolower((string)$zone['name']) ? ' selected' : '' ?>>
    <?= h((string)$zone['name']) ?>
  </option>
  <?php endforeach; ?>
</select>
<?php endif; ?>

<input type="hidden" name="letter" value="<?= h($letter) ?>">
<input type="submit" value="<?= h(t('list.search_btn')) ?>">
<?php if ($q !== '' || $zoneName !== ''): ?>
<a href="<?= h(url('domains.php')) ?>"><?= h(t('list.reset')) ?></a>
<?php endif; ?>
</form>

<?php
$letters = array_merge(['0-9'], range('A', 'Z'));
$base    = ['q' => $q, 'zone' => $zoneName];
$links   = ['<a href="?' . h(http_build_query($base + ['letter' => 'ALL'])) . '">ALL</a>'];
foreach ($letters as $l) {
    if ($l === $letter) {
        $links[] = '<strong>' . h($l) . '</strong>';
    } else {
        $links[] = '<a href="?' . h(http_build_query($base + ['letter' => $l])) . '">' . h($l) . '</a>';
    }
}
?>
<p class="small"><?= implode(' ', $links) ?></p>

<?= page_links($pager, $base + ['letter' => $letter]) ?>

<?php if ($rows === []): ?>
<p><?= h(t('list.none')) ?></p>
<?php else: ?>
<table>
  <tr>
    <th><?= h(t('list.col_name')) ?></th>
    <th><?= h(t('list.col_zone')) ?></th>
    <th><?= h(t('list.col_records')) ?></th>
    <th><?= h(t('list.col_org')) ?></th>
    <th><?= h(t('list.col_registered')) ?></th>
  </tr>
<?php foreach ($rows as $row): ?>
  <?php $list = $recordsByDomain[(int)$row['id']] ?? []; ?>
  <tr>
    <td><?= h((string)$row['name']) ?></td>
    <td><?= h((string)$row['zone']) ?></td>
    <td class="small">
      <?php if ($list === []): ?>
      <?= h(t('common.none')) ?>
      <?php else: ?>
      <?php
      echo implode(' &middot; ', array_map('h', array_map($summarise, array_slice($list, 0, 4))));
      if (count($list) > 4) {
          echo ' ' . h(t('list.more', count($list) - 4));
      }
      ?>
      <?php endif; ?>
    </td>
    <td><?= h((string)($row['organization'] ?? '')) ?></td>
    <td><?= h(format_date((string)($row['decided_at'] ?? $row['created_at']))) ?></td>
  </tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?= page_links($pager, $base + ['letter' => $letter]) ?>
<?php
page_footer();
