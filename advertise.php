<?php
declare(strict_types=1);

/**
 * freedns.my - advertising: prices and orders (public page).
 *
 * What a buyer sees: where each slot is, what it costs per month, the rules, and a form to order. The registry
 * approves the order, collects the money offline, and marks it paid - from that moment the advertisement runs
 * in its slot until the paid months are over, after which it disappears on its own.
 *
 * No payment gateway is involved, on purpose: no card data, no third party script, and the site keeps working
 * without JavaScript.
 */

require __DIR__ . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
广告投放
');

$zh     = function_exists('lang') && is_zh();
$errors = [];
$done   = '';
$user   = function_exists('current_user') ? current_user() : null;

/** The prices, per slot, per month. */
function ad_price(string $slot): int
{
    return max(0, (int)setting('ad_price_' . $slot, '0'));
}

function ad_money(int $amount): string
{
    $currency = trim((string)setting('ad_currency', 'CNY'));

    return ($currency === 'CNY' ? '¥' : $currency . ' ') . number_format($amount, 2, '.', ',');
}

/** "1 month" / "3 months" - and the same in Chinese. */
function ad_months_text(int $months, bool $zh): string
{
    return $zh ? $months . ' 个月' : $months . ' month' . ($months === 1 ? '' : 's');
}

$minMonths = max(1, (int)setting('ad_min_months', '1'));
$maxMonths = max($minMonths, (int)setting('ad_max_months', '12'));

/* ------------------------------------------------------------------ an order arrives */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && setting_bool('ad_enabled', true)) {
    csrf_verify();

    $slot    = strtolower(trim((string)($_POST['slot'] ?? '')));
    $title   = trim((string)($_POST['title'] ?? ''));
    $url     = trim((string)($_POST['url'] ?? ''));
    $note    = trim((string)($_POST['note'] ?? ''));
    $image   = trim((string)($_POST['image_url'] ?? ''));
    $months  = max($minMonths, min($maxMonths, (int)($_POST['months'] ?? 1)));
    $name    = trim((string)($_POST['contact_name'] ?? ''));
    $email   = trim((string)($_POST['contact_email'] ?? ''));
    $way     = trim((string)($_POST['contact_way'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $slots = function_exists('ad_slots') ? array_keys(ad_slots()) : [];
    /*
     * Any slot may be ordered. A slot without a price is not a mistake on the buyer's side - it means "ask us",
     * and the order is the way they ask; the amount is then 0 and the registry quotes it by hand. Refusing it
     * here (as this line used to) made the whole form unusable until every price had been filled in.
     */
    if (!in_array($slot, $slots, true)) {
        $errors[] = $zh ? '请选择一个广告位。' : 'Choose a slot.';
    }
    if ($title === '' || mb_strlen($title) > 120) {
        $errors[] = $zh ? '标题必填，且不超过 120 字。' : 'A title is required, at most 120 characters.';
    }
    if (preg_match('#^https?://#i', $url) !== 1) {
        $errors[] = $zh ? '链接必须是以 http:// 或 https:// 开头的完整地址。'
                        : 'The link has to be a full http:// or https:// address.';
    }
    if ($image !== '' && preg_match('#^https?://#i', $image) !== 1) {
        $errors[] = $zh ? '图片链接也必须以 http:// 或 https:// 开头（本站在你们那边托管图片）。'
                        : 'The image link has to start with http:// or https:// as well (the picture stays on your server).';
    }
    if ($name === '' || !valid_email_addr($email)) {
        $errors[] = $zh ? '请填写联系人与有效的联系邮箱。' : 'A contact name and a valid contact address are needed.';
    }

    if ($errors === []) {
        $amount = ad_price($slot) * $months;
        db_query(
            "INSERT INTO ad_orders (user_id, slot, title, url, note, image_url, months, amount, currency,
                contact_name, contact_email, contact_way, message, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [
                $user === null ? null : (int)$user['id'], $slot, substr($title, 0, 190), substr($url, 0, 190),
                $note === '' ? null : substr($note, 0, 190), $image === '' ? null : substr($image, 0, 190),
                $months, $amount, trim((string)setting('ad_currency', 'CNY')), substr($name, 0, 120),
                substr($email, 0, 190), $way === '' ? null : substr($way, 0, 190),
                $message === '' ? null : $message,
            ]
        );
        $id = (int)db()->lastInsertId();
        log_action('ad_order', 'settings', null, 'order #' . $id . ' for ' . $slot . ' (' . $months
            . ' month(s), ' . $amount . ') by ' . $email);

        $admin = trim((string)setting('notify_admin_email', ''));
        if ($admin !== '') {
            $previous = lang_switch('en');
            send_mail($admin, 'freedns.my: an advertising order arrived', implode("\n", [
                t('mail.dear', $admin), '',
                'Slot:    ' . $slot,
                'Months:  ' . $months,
                'Amount:  ' . $amount . ' ' . trim((string)setting('ad_currency', 'CNY')),
                'Title:   ' . $title,
                'Link:    ' . $url,
                'Contact: ' . $name . ' <' . $email . '>' . ($way === '' ? '' : ' / ' . $way),
                '',
                $message, '',
                rtrim(app_base_url(), '/') . '/admin/ads.php',
            ]));
            lang_switch($previous);
        }

        $done = $zh
            ? '申请已提交，编号为 #' . $id . '。我们会通过你留下的邮箱联系你确认内容与收款方式；收到款后广告即上架。'
            : 'Your order #' . $id . ' was received. We will write to the address you gave to confirm the wording '
              . 'and how to pay; the advertisement goes live once the money has arrived.';
    }
}

/* ------------------------------------------------------------------ the page */
$slots  = function_exists('ad_slots') ? ad_slots() : [];
$mine   = [];
if ($user !== null) {
    try {
        $mine = db_all('SELECT * FROM ad_orders WHERE user_id = ? ORDER BY id DESC LIMIT 20', [(int)$user['id']]);
    } catch (Throwable $e) {
        $mine = [];
    }
}

page_header($zh ? '广告投放' : 'Advertise here');
?>
<p><?= $zh
    ? '本站面向域名、主机与技术类服务开放广告位。广告是**纯文字**（标题 + 链接 + 一行说明），可选一张由你们自己托管的图片；我们不放追踪脚本、不弹窗、不自动播放。'
    : 'This site offers advertising to domain, hosting and technology services. An advertisement is <strong>text</strong> '
      . '(a title, a link and one line of explanation), with an optional picture hosted on your own server. No '
      . 'tracking scripts, no pop-ups, nothing that plays by itself.' ?></p>

<?php if ($done !== ''): ?><div class="msg ok"><?= h($done) ?></div><?php endif; ?>
<?= error_list($errors) ?>

<h2><?= $zh ? '广告位与价格' : 'Slots and prices' ?></h2>
<table>
  <tr><th><?= $zh ? '位置' : 'Slot' ?></th><th><?= $zh ? '在哪里显示' : 'Where it shows' ?></th>
      <th><?= $zh ? '价格（每月）' : 'Price (per month)' ?></th></tr>
  <?php foreach ($slots as $key => $info): ?>
  <?php $price = ad_price($key); ?>
  <tr>
    <td><strong><?= h(function_exists('ad_slot_label') ? ad_slot_label($key) : $info[0]) ?></strong>
        <div class="small"><code><?= h($key) ?></code></div></td>
    <td><?= h($info[0]) ?></td>
    <td><?= $price > 0 ? h(ad_money($price)) : '<span class="hint">' . h($zh ? '面议' : 'ask us') . '</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  <?php $bundle = max(0, (int)setting('ad_price_bundle', '0')); ?>
  <?php if ($bundle > 0): ?>
  <tr>
    <td><code>both rails</code></td>
    <td><?= $zh ? '左右两侧固定栏一起投放' : 'Left and right rail together' ?></td>
    <td><?= h(ad_money($bundle)) ?></td>
  </tr>
  <?php endif; ?>
</table>
<p class="hint"><?= $zh
    ? '左右两侧只在宽屏（≥1400px）显示，手机与窄窗口不显示；申请页下方与控制台中部则所有设备可见。'
    : 'The two rails only appear on a wide screen (1400px and up) and never on a phone; the slot under the '
      . 'application form and the one inside the console are visible everywhere.' ?></p>

<?php $contactNote = trim((string)setting_lang('ad_contact_note')); ?>
<?php $terms = trim((string)setting_lang('ad_terms')); ?>
<?php if ($contactNote !== '' || $terms !== ''): ?>
<h2><?= $zh ? '流程与付款' : 'How it works and how to pay' ?></h2>
<div class="msg info" style="white-space:pre-wrap"><?= h($contactNote . ($contactNote !== '' && $terms !== '' ? "\n\n" : '') . $terms) ?></div>
<?php endif; ?>

<h2><?= $zh ? '提交投放申请' : 'Send an order' ?></h2>
<?php if (!setting_bool('ad_enabled', true)): ?>
<div class="msg error"><?= $zh ? '广告申请暂时关闭，请稍后再来。' : 'Orders are closed at the moment; please come back later.' ?></div>
<?php else: ?>
<form method="post" action="<?= h(url('advertise.php')) ?>">
<?= csrf_field() ?>
<label for="slot"><?= $zh ? '广告位' : 'Slot' ?></label>
<select id="slot" name="slot">
  <?php foreach ($slots as $key => $info): ?>
  <option value="<?= h($key) ?>"><?= h((function_exists('ad_slot_label') ? ad_slot_label($key) : $info[0])
      . ' — ' . ($info[2] ?? $info[0]) . ' — ' . (ad_price($key) > 0
          ? ad_money(ad_price($key)) . '/' . ($zh ? '月' : 'mo')
          : ($zh ? '面议' : 'ask us'))) ?></option>
  <?php endforeach; ?>
</select>
<p class="hint"><?= $zh
    ? '上面列出的广告位都可以下单：标了价格的直接下单，写「面议」的我们收到申请后会联系你报价，两者都能先提交。'
    : 'Every slot above can be ordered: the ones with a price are charged as shown, the ones marked "ask us" are '
      . 'quoted after your order arrives. Either way, submit the form and we answer by e-mail.' ?></p>

<label for="months"><?= $zh ? '投放时长（月）' : 'How many months' ?></label>
<input type="text" id="months" name="months" maxlength="2" size="3" value="<?= (int)$minMonths ?>">
<span class="hint"><?= h(($zh ? '最少 ' : 'at least ') . $minMonths . ($zh ? ' 个月，最多 ' : ' month(s), at most ')
    . $maxMonths) ?></span>

<label for="title"><?= $zh ? '广告标题（一行）' : 'Title (one line)' ?></label>
<input type="text" id="title" name="title" maxlength="120">

<label for="url"><?= $zh ? '点击后打开的链接' : 'Where a click goes' ?></label>
<input type="text" id="url" name="url" maxlength="190" placeholder="https://">

<label for="note"><?= $zh ? '一行说明（可选）' : 'One line of explanation (optional)' ?></label>
<input type="text" id="note" name="note" maxlength="190">

<label for="image_url"><?= $zh ? '图片链接（可选，图片由你们自己托管）' : 'Picture link (optional, hosted on your side)' ?></label>
<input type="text" id="image_url" name="image_url" maxlength="190" placeholder="https://">

<label for="contact_name"><?= $zh ? '联系人' : 'Contact name' ?></label>
<input type="text" id="contact_name" name="contact_name" maxlength="120"
       value="<?= $user === null ? '' : h((string)$user['username']) ?>">

<label for="contact_email"><?= $zh ? '联系邮箱' : 'Contact e-mail' ?></label>
<input type="text" id="contact_email" name="contact_email" maxlength="190"
       value="<?= $user === null ? '' : h((string)$user['email']) ?>">

<label for="contact_way"><?= $zh ? '其它联系方式（QQ / 微信 / 电话，可选）' : 'Another way to reach you (QQ / WeChat / phone, optional)' ?></label>
<input type="text" id="contact_way" name="contact_way" maxlength="190">

<label for="message"><?= $zh ? '备注（可选）' : 'Anything else (optional)' ?></label>
<textarea id="message" name="message" rows="4"></textarea>

<p class="hint"><?= $zh
    ? '提交后我们会用你留下的邮箱确认文案与收款方式；收到款后广告才会上架，到期自动下架。'
    : 'We reply to the address you give to confirm the wording and how to pay; the advertisement goes live '
      . 'after the money arrives and stops by itself when the period is over.' ?></p>
<p><input type="submit" value="<?= $zh ? '提交申请' : 'Send the order' ?>"></p>
</form>
<?php endif; ?>

<?php if ($mine !== []): ?>
<h2><?= $zh ? '我的投放申请' : 'My orders' ?></h2>
<table>
  <tr><th>#</th><th><?= $zh ? '广告位' : 'Slot' ?></th><th><?= $zh ? '时长' : 'Months' ?></th>
      <th><?= $zh ? '金额' : 'Amount' ?></th><th><?= $zh ? '状态' : 'State' ?></th>
      <th><?= $zh ? '投放期' : 'Runs' ?></th></tr>
  <?php foreach ($mine as $order): ?>
  <tr>
    <td><?= (int)$order['id'] ?></td>
    <td><code><?= h((string)$order['slot']) ?></code></td>
    <td><?= (int)$order['months'] ?></td>
    <td><?= h(ad_money((int)$order['amount'])) ?></td>
    <td><?= h($zh
        ? (['pending' => '待审核', 'approved' => '已通过，待付款', 'paid' => '已上架', 'rejected' => '已拒绝',
            'cancelled' => '已取消', 'expired' => '已到期'][(string)$order['status']] ?? (string)$order['status'])
        : (string)$order['status']) ?>
        <?= trim((string)($order['reason'] ?? '')) === '' ? '' : '<br><span class="small err">'
            . h((string)$order['reason']) . '</span>' ?></td>
    <td class="small"><?= h((string)($order['start_at'] ?? '-')) ?><br><?= h((string)($order['end_at'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php elseif ($user !== null): ?>
<p class="hint"><?= $zh ? '你还没有提交过广告申请。' : 'You have not sent an order yet.' ?></p>
<?php endif; ?>
<?php
page_footer();
