<?php
declare(strict_types=1);

/**
 * freedns.my - other registries we are happy to point at.
 *
 * Layout: the list is a two-column table, the way sitemap.php shows its links - the registry on the left, a
 * one-line note about it on the right. Clicking a name opens a detail view on this same page (?r=category-index),
 * which carries the full description, the limits, the cost and the outbound link to the service itself. The
 * detail lives here rather than in its own file on purpose: a new file would need six language wrappers, and the
 * whole page already knows the language.
 *
 * The registry is not the only place to get a name, and pretending otherwise would waste everybody's time. The
 * list is written by hand in the back office (Back office -> Other registries), one partner per line:
 *
 *     category | name | url | one line about it | limits | free or paid
 *
 * Categories: free2ld (a free second level domain), dns (DNS hosting), ddns (dynamic DNS), paid (a commercial
 * registrar). The registry is not affiliated with any of them: the disclaimer below says so, and it is the whole
 * point of the page.
 *
 * An application to be listed is a form here, but it is not stored: it is mailed to the registry, which answers
 * by hand. That keeps the feature free of any new table.
 */

require __DIR__ . '/inc/bootstrap.php';
// The reader is shared with the back office page, so only one copy of the parsing rules exists.
require_once __DIR__ . '/inc/partners.php';

/*
 * These three must be set before anything is printed. They were lost once when partner_list() was moved into
 * inc/partners.php: $zh became null, so every Chinese branch fell through to English, and $errors became null,
 * so error_list() threw a TypeError and cut the page off in the middle. Both symptoms, one cause.
 */
$zh     = function_exists('lang') && is_zh();
$errors = [];
$done   = '';

$list   = partner_list();
$cats   = partner_categories();
$total  = 0;
foreach ($list as $rows) {
    $total += count($rows);
}

/** How the cost column reads, in the reader's language. */
$costText = static function (string $cost) use ($zh): string {
    if ($cost === 'free') {
        return $zh ? '免费' : 'free';
    }
    if ($cost === 'paid') {
        return $zh ? '收费' : 'paid';
    }

    return $zh ? '以对方页面为准' : 'see their site';
};

/*
 * The detail view. A slug is "category-index" (free2ld-0), which is stable as long as the list order is not
 * shuffled; anything that does not resolve is ignored and the visitor simply gets the list.
 */
$wanted = strtolower(trim((string)($_GET['r'] ?? '')));
$detail = null;
if ($wanted !== '' && preg_match('/^([a-z0-9]+)-(\d{1,3})$/', $wanted, $m) === 1
    && array_key_exists($m[1], $cats) && isset($list[$m[1]][(int)$m[2]])) {
    $detail = ['key' => $m[1], 'index' => (int)$m[2], 'row' => $list[$m[1]][(int)$m[2]]];
    $detail['slug'] = $m[1] . '-' . $m[2];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name  = trim((string)($_POST['name'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $note  = trim((string)($_POST['note'] ?? ''));
    $mail  = trim((string)($_POST['email'] ?? ''));
    $kind  = strtolower(trim((string)($_POST['category'] ?? '')));

    if ($name === '' || mb_strlen($name) > 80) {
        $errors[] = $zh ? '请填写平台名称（80 字以内）。' : 'A name is needed (up to 80 characters).';
    }
    if (preg_match('#^https?://#i', $url) !== 1) {
        $errors[] = $zh ? '链接必须是以 http:// 或 https:// 开头的完整地址。'
                        : 'The link has to be a full http:// or https:// address.';
    }
    if (!valid_email_addr($mail)) {
        $errors[] = $zh ? '请填写有效的联系邮箱。' : 'A valid contact address is needed.';
    }
    if (!array_key_exists($kind, partner_categories())) {
        $errors[] = $zh ? '请选择一个分类。' : 'Please pick a category.';
    }

    if ($errors === []) {
        $to = trim((string)setting('notify_admin_email', ''));
        if ($to !== '' && function_exists('send_mail')) {
            $previous = lang_switch('en');
            send_mail($to, 'freedns.my: a listing request arrived', implode("\n", [
                'A site would like to be listed on /partners.php.', '',
                'Name:     ' . $name,
                'Link:     ' . $url,
                'Category: ' . $kind . ' (' . partner_categories()[$kind][0] . ')',
                'About:    ' . $note,
                'Contact:  ' . $mail, '',
                'Nothing is stored in the database: this mail is the only record.', '',
                rtrim(app_base_url(), '/') . '/admin/partners.php',
            ]));
            lang_switch($previous);
        }
        log_action('partner_request', 'settings', null, 'listing request from ' . $mail . ' for ' . $url);

        $done = $zh
            ? '申请已收到，我们会通过你留下的邮箱回复。上榜完全免费，也不要求互链。'
            : 'Your request was received; we answer to the address you gave. Being listed costs nothing and no '
              . 'link back is required.';
    }
}

page_header($zh ? '其它免费域名平台' : 'Other registries');
?>
<p><?= $zh
    ? 'freedns.my 不是唯一的选择，我们也不打算假装是。下面是注册局认可、值得一看的其它平台，按用途分类；'
      . '左边是平台，右边是一句话说明，<strong>点平台名可以看到详细介绍与跳转链接</strong>。'
    : 'freedns.my is not the only place to get a name, and it would be dishonest to pretend otherwise. Below are '
      . 'other services the registry is happy to point at, grouped by what they are for: the service on the left, '
      . 'a line about it on the right, and <strong>clicking a name opens the full entry with the link</strong>.' ?></p>

<div class="msg info"><?= $zh
    ? '免责声明：这些平台与 freedns.my <strong>没有隶属或合作关系</strong>。我们在收录时会看一眼，但不对它们的稳定性、政策、数据处置或收费作出任何担保。请自行阅读对方的条款，不要把我们当作它们的客服。'
    : 'Disclaimer: these services are <strong>not affiliated with freedns.my</strong>. A listing means we looked '
      . 'at them once; it is not a guarantee of their uptime, policies, handling of your data or prices. Read '
      . 'their terms yourself, and please do not ask us to act as their support desk.' ?></div>

<?php if ($detail !== null): /* ------------------------------------------- one entry, in full */ ?>
<?php $row = $detail['row']; ?>
<h2><?= h($row['name']) ?></h2>
<table>
  <tr>
    <th style="white-space:nowrap"><?= $zh ? '分类' : 'Category' ?></th>
    <td><a href="<?= h(url('partners.php')) ?>#<?= h($detail['key']) ?>"><?= h($zh
        ? $cats[$detail['key']][1] : $cats[$detail['key']][0]) ?></a></td>
  </tr>
  <tr>
    <th style="white-space:nowrap"><?= $zh ? '介绍' : 'About' ?></th>
    <td><?= h($row['note']) ?></td>
  </tr>
  <?php if ($row['limits'] !== ''): ?>
  <tr>
    <th style="white-space:nowrap"><?= $zh ? '限制' : 'Limits' ?></th>
    <td><?= h($row['limits']) ?></td>
  </tr>
  <?php endif; ?>
  <tr>
    <th style="white-space:nowrap"><?= $zh ? '费用' : 'Cost' ?></th>
    <td><?= h($costText($row['cost'])) ?></td>
  </tr>
  <tr>
    <th style="white-space:nowrap"><?= $zh ? '跳转链接' : 'Link' ?></th>
    <td>
      <a href="<?= h($row['url']) ?>" target="_blank" rel="nofollow noopener"><strong><?= h($row['url']) ?></strong></a>
      <div class="small"><?= $zh
          ? '在新窗口打开，带 nofollow；对方与本站无隶属关系。'
          : 'Opens in a new window, carries nofollow; the service is not affiliated with this site.' ?></div>
    </td>
  </tr>
</table>
<p>
  <a href="<?= h(url('partners.php')) ?>">&larr; <?= $zh ? '返回平台列表' : 'Back to the list' ?></a>
  <?php
  // The neighbours in the same category, so the reader keeps browsing instead of going back every time.
  $siblings = [];
  foreach ($list[$detail['key']] as $i => $r) {
      if ($i !== $detail['index']) {
          $siblings[] = ['i' => $i, 'row' => $r];
      }
  }
  if ($siblings !== []): ?>
  &middot; <?= $zh ? '同类还有：' : 'Also in this category: ' ?>
  <?php foreach ($siblings as $n => $s): ?><?= $n > 0 ? ' &middot; ' : '' ?><a href="<?= h(url(
      'partners.php?r=' . $detail['key'] . '-' . $s['i'])) ?>"><?= h($s['row']['name']) ?></a><?php endforeach; ?>
  <?php endif; ?>
</p>
<?php endif; ?>

<?php if ($total === 0): ?>
<p class="hint"><?= $zh
    ? '名单还在整理中，目前没有公开的推荐条目。你可以在下方申请上榜。'
    : 'The list is still being put together, so nothing is published yet. You can apply to be listed below.' ?></p>
<?php else: ?>
<?php foreach ($cats as $key => $label): ?>
<?php if ($list[$key] === []) { continue; } ?>
<h2 id="<?= h($key) ?>"><?= h($zh ? $label[1] : $label[0]) ?></h2>
<table>
  <?php foreach ($list[$key] as $i => $row): ?>
  <tr>
    <td style="white-space:nowrap"><a href="<?= h(url('partners.php?r=' . $key . '-' . $i)) ?>"><strong><?= h(
        $row['name']) ?></strong></a></td>
    <td>
      <?= h($row['note']) ?>
      <?php if ($row['limits'] !== ''): ?>
      <div class="small"><?= $zh ? '限制：' : 'Limits: ' ?><?= h($row['limits']) ?></div>
      <?php endif; ?>
      <div class="small"><?= $zh ? '费用：' : 'Cost: ' ?><?= h($costText($row['cost'])) ?>
        &middot; <a href="<?= h(url('partners.php?r=' . $key . '-' . $i)) ?>"><?= $zh ? '看详细介绍与链接'
        : 'full entry and link' ?></a></div>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endforeach; ?>
<p class="hint"><?= $zh
    ? '跳转链接都在条目页里，且一律带 nofollow：本站不为它们传递权重，也不借此换取排名。'
    : 'The outbound links live in the entry pages and every one of them carries nofollow: the site passes no '
      . 'ranking to them, and no place on this list is sold for one.' ?></p>
<?php endif; ?>

<h2><?= $zh ? '希望上榜？' : 'Would you like to be listed?' ?></h2>
<p><?= $zh
    ? '免费，不要求互链。条件很简单：与域名、DNS 或主机相关，内容合法，不做弹窗与追踪，并且真的能用。'
    : 'It costs nothing and no link back is required. The conditions are short: related to domains, DNS or '
      . 'hosting, lawful, free of pop-ups and tracking, and actually working.' ?></p>
<?php if ($done !== ''): ?><div class="msg ok"><?= h($done) ?></div><?php endif; ?>
<?= error_list($errors) ?>
<form method="post" action="<?= h(url('partners.php')) ?>">
<?= csrf_field() ?>
<label for="category"><?= $zh ? '分类' : 'Category' ?></label>
<select id="category" name="category">
  <?php foreach ($cats as $key => $label): ?>
  <option value="<?= h($key) ?>"><?= h($zh ? $label[1] : $label[0]) ?></option>
  <?php endforeach; ?>
</select>
<label for="name"><?= $zh ? '名称' : 'Name' ?></label>
<input type="text" id="name" name="name" maxlength="80">
<label for="url"><?= $zh ? '网址' : 'Address' ?></label>
<input type="text" id="url" name="url" maxlength="190" placeholder="https://">
<label for="note"><?= $zh ? '一句话介绍' : 'One line about it' ?></label>
<input type="text" id="note" name="note" maxlength="190">
<label for="email"><?= $zh ? '你的联系邮箱' : 'Your contact address' ?></label>
<input type="text" id="email" name="email" maxlength="190">
<p class="hint"><?= $zh
    ? '申请不会存进数据库，而是直接发到注册局的邮箱，由人工回复与上架。'
    : 'The request is not stored in a database: it is mailed to the registry, which answers by hand and adds the '
      . 'entry.' ?></p>
<p><input type="submit" value="<?= $zh ? '申请上榜' : 'Ask to be listed' ?>"></p>
</form>

<h2><?= $zh ? '也看看' : 'Also worth a look' ?></h2>
<ul>
  <li><a href="<?= h(url('doc.php')) ?>"><?= $zh ? '使用文档' : 'Documentation' ?></a></li>
  <li><a href="<?= h(url('about.php')) ?>"><?= $zh ? '关于我们与资金公开' : 'About us, and the money' ?></a></li>
</ul>
<?php
page_footer();
