<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the list of other registries.
 *
 * The list is edited the way the public application form is filled in: one field per box (category, name,
 * address, one line about it, limits, cost), not as pipe-separated text. Under the surface it is still stored as
 * the same line format the public page reads back, because inc/partners.php is the only reader and the format is
 * what it understands:
 *
 *     category | name | url | one line about it | limits | free or paid
 *
 * Adding an entry: fill one of the empty rows at the bottom. Removing one: tick "delete" on its row. No
 * JavaScript anywhere - the empty rows are simply always there, which is also how account/manage.php edits
 * records.
 *
 * A "|" or a line break inside a value would break the stored line format, so both are turned into spaces on
 * save; the hint under the form says so.
 *
 * Categories: free2ld, dns, ddns, paid. Lines starting with # are ignored, a line with fewer than three fields
 * is skipped, and a link that is not http(s) is dropped: a mistyped value loses its entry rather than turning
 * into something else on the page.
 *
 * The previews at the bottom are laid out the way the public page is written - the service on the left, a line
 * about it on the right, grouped under the category heading - so what is checked here is what a visitor gets.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';
// Only the shared reader, never the public page: including that one would render it inside the back office.
require_once dirname(__DIR__) . '/inc/partners.php';

$admin  = require_admin();
require_owner('Other registries');
$errors = [];
$notes  = [];

/** Values that would break the stored line format are flattened, and everything is trimmed. */
$clean = static function (string $v): string {
    $v = str_replace(['|', "\r", "\n", "\t"], ' ', $v);

    return trim(preg_replace('/\s{2,}/', ' ', $v) ?? '');
};

/** Turn one submitted set of field rows into the stored line format. */
$rebuild = static function (array $post, string $prefix, array $cats) use ($clean, &$errors, &$notes): string {
    $cat    = (array)($post[$prefix . 'cat'] ?? []);
    $name   = (array)($post[$prefix . 'name'] ?? []);
    $url    = (array)($post[$prefix . 'url'] ?? []);
    $note   = (array)($post[$prefix . 'note'] ?? []);
    $limits = (array)($post[$prefix . 'limits'] ?? []);
    $cost   = (array)($post[$prefix . 'cost'] ?? []);
    $del    = (array)($post[$prefix . 'del'] ?? []);

    $lines  = [];
    $seen   = 0;
    $count  = max(count($cat), count($name), count($url));
    for ($i = 0; $i < $count; $i++) {
        if (in_array((string)$i, array_map('strval', $del), true)) {
            continue;   // ticked for deletion
        }
        $c  = strtolower($clean((string)($cat[$i] ?? '')));
        $n  = $clean((string)($name[$i] ?? ''));
        $u  = $clean((string)($url[$i] ?? ''));
        $no = $clean((string)($note[$i] ?? ''));
        $li = $clean((string)($limits[$i] ?? ''));
        $co = strtolower($clean((string)($cost[$i] ?? '')));

        if ($n === '' && $u === '' && $no === '') {
            continue;   // an empty row: nothing to store
        }
        if (!array_key_exists($c, $cats)) {
            $errors[] = 'Row ' . ($i + 1) . ': pick a category for "' . ($n !== '' ? $n : $u) . '".';
            continue;
        }
        if (preg_match('#^https?://#i', $u) !== 1) {
            $errors[] = 'Row ' . ($i + 1) . ' ("' . $n . '"): the address has to start with http:// or https://.';
            continue;
        }
        if ($n === '') {
            $errors[] = 'Row ' . ($i + 1) . ': a name is needed.';
            continue;
        }
        if (mb_strlen($n) > 80) {
            $errors[] = 'Row ' . ($i + 1) . ': the name is longer than 80 characters.';
            continue;
        }
        if (in_array($co, ['free', 'paid'], true) === false) {
            $co = '';   // anything else reads as "see their site" on the page
        }
        $lines[] = $c . ' | ' . $n . ' | ' . $u . ' | ' . $no . ' | ' . $li . ' | ' . $co;
        $seen++;
    }
    if ($seen > 0) {
        $notes[] = $prefix === '' ? $seen . ' English entry/entries kept.'
                                  : $seen . ' Chinese entry/entries kept.';
    }

    return implode("\n", $lines);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $cats   = partner_categories();
        $newEn  = $rebuild($_POST, '', $cats);
        $newZh  = $rebuild($_POST, 'zh_', $cats);
        $introEn = trim((string)($_POST['partners_intro'] ?? ''));
        $introZh = trim((string)($_POST['partners_intro_zh'] ?? ''));

        $changed = 0;
        if ((string)setting('partners_list', '') !== $newEn) {
            set_setting('partners_list', $newEn);
            $changed++;
        }
        if ((string)setting('partners_list_zh', '') !== $newZh) {
            set_setting('partners_list_zh', $newZh);
            $changed++;
        }
        if ((string)setting('partners_intro', '') !== $introEn) {
            set_setting('partners_intro', $introEn);
            $changed++;
        }
        if ((string)setting('partners_intro_zh', '') !== $introZh) {
            set_setting('partners_intro_zh', $introZh);
            $changed++;
        }
        log_action('partners_save', 'settings', null, $changed . ' partner value(s) changed');
        if ($errors === []) {
            flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
            redirect('partners.php');
        }
    }
}

/** The stored list, split back into field rows so the form can show it. */
$rowsOf = static function (array $list, array $cats): array {
    $out = [];
    foreach ($cats as $key => $_) {
        foreach ($list[$key] as $row) {
            $out[] = [
                'cat'    => $key,
                'name'   => $row['name'],
                'url'    => $row['url'],
                'note'   => $row['note'],
                'limits' => $row['limits'],
                'cost'   => in_array($row['cost'], ['free', 'paid'], true) ? $row['cost'] : '',
            ];
        }
    }

    return $out;
};

$list  = partner_list();
$cats  = partner_categories();
$total = 0;
foreach ($list as $rows) {
    $total += count($rows);
}
$rowsEn = $rowsOf($list, $cats);

/*
 * The Chinese list, for the second form and the second preview. partner_list() reads whichever language is
 * current (setting_lang), so the only honest way to see the Chinese list is to ask for it: switch to zh, read,
 * switch back. Same pattern the public page uses before it sends its mail, so there is one way of doing this in
 * the code base and not two.
 */
$previous = lang_switch('zh');
$listZh   = partner_list();
lang_switch($previous);
$totalZh  = 0;
foreach ($listZh as $rowsZh) {
    $totalZh += count($rowsZh);
}
$rowsZh = $rowsOf($listZh, $cats);

$differs = ($totalZh !== $total);
foreach ($listZh as $key => $rows) {
    if (count($rows) !== count($list[$key])) {
        $differs = true;
    }
}

/** The cost column as the public page renders it. */
$costText = static function (string $cost): string {
    if ($cost === 'free') {
        return 'free';
    }
    if ($cost === 'paid') {
        return 'paid';
    }

    return 'see their site';
};

/** One editable set of rows, used for both languages. */
$formRows = static function (array $rows, array $cats, string $prefix, int $blanks) use ($clean): void { ?>
<?php $n = 0; foreach ($rows as $r): $i = $n++; ?>
<tr>
  <td>
    <select name="<?= h($prefix) ?>cat[]">
      <?php foreach ($cats as $key => $label): ?>
      <option value="<?= h($key) ?>"<?= $r['cat'] === $key ? ' selected' : '' ?>><?= h($label[0]) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="<?= h($prefix) ?>keep[]" value="1">
  </td>
  <td><input type="text" name="<?= h($prefix) ?>name[]" maxlength="80" value="<?= h($r['name']) ?>"></td>
  <td><input type="text" name="<?= h($prefix) ?>url[]" maxlength="190" value="<?= h($r['url']) ?>"></td>
  <td><input type="text" name="<?= h($prefix) ?>note[]" maxlength="190" value="<?= h($r['note']) ?>"></td>
  <td><input type="text" name="<?= h($prefix) ?>limits[]" maxlength="190" value="<?= h($r['limits']) ?>"></td>
  <td>
    <select name="<?= h($prefix) ?>cost[]">
      <option value=""<?= $r['cost'] === '' ? ' selected' : '' ?>>see their site</option>
      <option value="free"<?= $r['cost'] === 'free' ? ' selected' : '' ?>>free</option>
      <option value="paid"<?= $r['cost'] === 'paid' ? ' selected' : '' ?>>paid</option>
    </select>
  </td>
  <td><label class="small"><input type="checkbox" name="<?= h($prefix) ?>del[]" value="<?= $i ?>"> delete</label></td>
</tr>
<?php endforeach; ?>
<?php for ($b = 0; $b < $blanks; $b++): $i = ($n + $b); ?>
<tr>
  <td>
    <select name="<?= h($prefix) ?>cat[]">
      <?php foreach ($cats as $key => $label): ?>
      <option value="<?= h($key) ?>"><?= h($label[0]) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td><input type="text" name="<?= h($prefix) ?>name[]" maxlength="80" placeholder="new entry"></td>
  <td><input type="text" name="<?= h($prefix) ?>url[]" maxlength="190" placeholder="https://"></td>
  <td><input type="text" name="<?= h($prefix) ?>note[]" maxlength="190" placeholder="one line about it"></td>
  <td><input type="text" name="<?= h($prefix) ?>limits[]" maxlength="190" placeholder="limits (optional)"></td>
  <td>
    <select name="<?= h($prefix) ?>cost[]">
      <option value="">see their site</option>
      <option value="free">free</option>
      <option value="paid">paid</option>
    </select>
  </td>
  <td></td>
</tr>
<?php endfor; ?>
<?php };

/** One read-only preview, used for both languages. */
$preview = static function (array $rows, array $cats, string $langPrefix, bool $zhLabels) use ($costText): void { ?>
<?php if ($rows === []): ?>
<p class="hint">Nothing yet.</p>
<?php else: ?>
<?php $byCat = []; foreach ($rows as $r) { $byCat[$r['cat']][] = $r; } ?>
<?php foreach ($cats as $key => $label): ?>
<?php if (!isset($byCat[$key])) { continue; } ?>
<h3><?= h($zhLabels ? $label[1] : $label[0]) ?> <span class="small">(<code><?= h($key) ?></code>)</span></h3>
<table>
  <tr>
    <th style="white-space:nowrap"><?= $zhLabels ? '服务' : 'Service' ?></th>
    <th><?= $zhLabels ? '一句话简介' : 'About' ?></th>
    <th style="white-space:nowrap"><?= $zhLabels ? '详细页' : 'Entry page' ?></th>
  </tr>
  <?php foreach ($byCat[$key] as $i => $r): ?>
  <?php
  // The r= number the public page will hand this entry: its position inside its own category.
  $slug = $key . '-' . $i;
  ?>
  <tr>
    <td style="white-space:nowrap">
      <a href="<?= h($r['url']) ?>" rel="nofollow noopener" target="_blank"><strong><?= h($r['name']) ?></strong></a>
      <div class="small"><?= h($r['url']) ?></div>
    </td>
    <td>
      <?= h($r['note']) ?>
      <?php if ($r['limits'] !== ''): ?>
      <div class="small"><?= $zhLabels ? '限制：' : 'Limits: ' ?><?= h($r['limits']) ?></div>
      <?php endif; ?>
      <div class="small"><?= $zhLabels ? '费用：' : 'Cost: ' ?><?= h($costText($r['cost'])) ?></div>
    </td>
    <td style="white-space:nowrap">
      <a href="<?= h(url($langPrefix . 'partners.php?r=' . $slug)) ?>" target="_blank"><?= $zhLabels ? '打开' : 'open' ?> &rarr;</a>
      <div class="small"><code>r=<?= h($slug) ?></code></div>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endforeach; ?>
<?php endif; ?>
<?php };

page_header('Other registries');
?>
<p>
The public page is <a href="<?= h(url('partners.php')) ?>">/partners.php</a> (Chinese:
<code>/zh/partners.php</code>). It shows the service on the left and a line about it on the right, grouped by
category; clicking a name opens that entry's own page with the full description and the outbound link. It shows a
disclaimer that these services are not affiliated with the registry, and every outbound link carries
<code>nofollow</code>.
</p>
<div class="msg <?= $total === 0 ? 'error' : 'ok' ?>">
  English list: <?= $total === 0 ? 'no entry parses yet - the public page says the list is being put together.'
                                : $total . ' entry/entries.' ?>
  &middot; Chinese list: <?= $totalZh === 0 ? 'empty, so Chinese readers get the English one.'
                                            : $totalZh . ' entry/entries.' ?>
</div>

<?= error_list($errors) ?>
<?php foreach ($notes as $note): ?><div class="msg ok"><?= h($note) ?></div><?php endforeach; ?>

<form method="post" action="partners.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<h2>The list (English)</h2>
<p class="hint">One field per box, exactly like the application form on the public page. Fill an empty row at the
bottom to add an entry; tick <em>delete</em> to remove one. A "<code>|</code>" or a line break inside a box is
turned into a space when saved, because the stored format uses <code>|</code> between the fields.</p>
<table>
  <tr>
    <th>Category</th><th>Name</th><th>Address</th><th>One line about it</th><th>Limits</th><th>Cost</th><th></th>
  </tr>
  <?php $formRows($rowsEn, $cats, '', 3); ?>
</table>

<h2>The list (Chinese)</h2>
<p class="hint">Same shape; leave every row empty to show the English list to Chinese readers too.</p>
<table>
  <tr>
    <th>分类</th><th>名称</th><th>网址</th><th>一句话介绍</th><th>限制</th><th>费用</th><th></th>
  </tr>
  <?php $formRows($rowsZh, $cats, 'zh_', 3); ?>
</table>

<h2>Introduction (optional)</h2>
<label for="partners_intro">English</label>
<textarea id="partners_intro" name="partners_intro" rows="3"><?= h((string)setting('partners_intro', '')) ?></textarea>
<label for="partners_intro_zh">Chinese</label>
<textarea id="partners_intro_zh" name="partners_intro_zh" rows="3"><?= h((string)setting('partners_intro_zh', '')) ?></textarea>

<p><input type="submit" value="Save"></p>
</form>

<h2>What the page will show</h2>
<p class="hint">Laid out exactly like the public page: the service on the left, a line about it on the right. The
last column opens that entry's own page - the one a visitor gets when they click a name.</p>
<h3>English readers</h3>
<?php $preview($rowsEn, $cats, '', false); ?>

<h3>Chinese readers</h3>
<?php if ($totalZh === 0): ?>
<p class="hint">The Chinese list is empty, so <code>/zh/partners.php</code> falls back to the English list above.
Fill the Chinese rows if you want a translated one.</p>
<?php else: ?>
<p class="hint"><?= $totalZh ?> entry/entries<?= $differs
    ? ' - careful: this list does not hold the same number of entries per category as the English one, so the two '
      . 'pages differ.'
      : ' - same shape as the English list above.' ?></p>
<?php $preview($rowsZh, $cats, 'zh/', true); ?>
<?php endif; ?>

<p class="hint">Reminder: reordering or deleting a row shifts the <code>r=</code> numbers of the entries after it
inside the same category, so a link that a visitor bookmarked may then point at a different service. That is
harmless - the page always resolves an entry or falls back to the list - but it is worth knowing before a big
reshuffle.</p>
<p class="hint">Applications to be listed arrive by mail (the address on the Maintenance page as
<code>notify_admin_email</code>); nothing is stored in the database.</p>
<?php
page_footer();
