<?php
declare(strict_types=1);

/**
 * freedns.my - back office: advertising slots.
 *
 * Four places on the site carry an advertisement, and all of them are filled in by hand here - no third party
 * script, no iframe, no tracking, which is why the registry can promise the site stays free of outside code.
 *
 * A slot that is left empty is not hidden: it shows the hiring notice instead, pointing at jobs.php. That way a
 * place nobody has bought still looks deliberate.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Advertising');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    /*
     * Order handling. Approving says "the wording is acceptable"; marking it paid is what actually puts the
     * advertisement up, because the cron job publishes every order that is paid and due. Money is collected
     * outside the site, so this button is the moment the registry confirms it arrived.
     */
    if (in_array($action, ['order_approve', 'order_paid', 'order_reject', 'order_cancel', 'order_renew'], true)) {
        $id    = (int)($_POST['id'] ?? 0);
        $order = $id > 0 ? db_one('SELECT * FROM ad_orders WHERE id = ?', [$id]) : null;
        $note  = trim((string)($_POST['reason'] ?? ''));

        if ($order === null) {
            $errors[] = 'That order does not exist.';
        } elseif ($action === 'order_reject' && $note === '') {
            $errors[] = 'Give a reason when rejecting an order.';
        } elseif ($action === 'order_renew') {
            /*
             * One click to sell another month: the end date moves on by a month, the reminder flag is cleared so
             * the buyer is reminded again next time, and an expired order becomes paid again - which is what
             * makes the cron job put the advertisement back up.
             */
            $base  = trim((string)($order['end_at'] ?? ''));
            $from  = ($base !== '' && strtotime($base) !== false && strtotime($base) > time()) ? $base : date('Y-m-d H:i:s');
            $newEnd = date('Y-m-d H:i:s', strtotime($from . ' +1 month'));
            $back  = in_array((string)$order['status'], ['expired', 'pending'], true) ? 'paid' : (string)$order['status'];

            db_query('UPDATE ad_orders SET end_at = ?, reminded_at = NULL, status = ?, decided_by = ?,
                      decided_at = NOW() WHERE id = ?',
                [$newEnd, $back, (string)$admin['username'], $id]);
            log_action('ad_order_renew', 'settings', null, 'order #' . $id . ' extended to ' . $newEnd
                . ' (status ' . $back . ')');

            $to = (string)$order['contact_email'];
            if (valid_email_addr($to) && mail_pref_allows($to, 'account')) {
                $previous = lang_switch('en');
                send_mail($to, 'Your advertisement was extended', implode("\n", [
                    t('mail.dear', (string)$order['contact_name']), '',
                    'The advertisement below now runs until ' . $newEnd . ':',
                    '  ' . (string)$order['title'] . ' (' . (string)$order['slot'] . ')',
                    '', rtrim(app_base_url(), '/') . '/advertise.php',
                ]));
                lang_switch($previous);
            }

            flash('Order #' . $id . ' now runs until ' . $newEnd . '.', 'ok');
            redirect('ads.php');
        } else {
            $status = (string)$order['status'];
            if ($action === 'order_approve') {
                db_query("UPDATE ad_orders SET status = 'approved', decided_by = ?, decided_at = NOW() WHERE id = ?",
                    [(string)$admin['username'], $id]);
                $say = 'approved';
            } elseif ($action === 'order_paid') {
                db_query("UPDATE ad_orders SET status = 'paid', paid_at = NOW(), decided_by = ?,
                          decided_at = NOW() WHERE id = ?", [(string)$admin['username'], $id]);
                $say = 'marked as paid (the advertisement goes up on the next cron run)';
            } elseif ($action === 'order_reject') {
                db_query("UPDATE ad_orders SET status = 'rejected', reason = ?, decided_by = ?,
                          decided_at = NOW() WHERE id = ?",
                    [substr($note, 0, 255), (string)$admin['username'], $id]);
                $say = 'rejected';
            } else {
                // Cancelling takes it down at once, but only if the slot really holds this order's wording.
                $slot = (string)$order['slot'];
                if (array_key_exists($slot, ad_slots())
                    && trim((string)setting('ad_' . $slot . '_title', '')) === trim((string)$order['title'])
                    && trim((string)setting('ad_' . $slot . '_url', '')) === trim((string)$order['url'])) {
                    foreach (['title', 'url', 'note', 'image', 'body', 'body_zh'] as $field) {
                        set_setting('ad_' . $slot . '_' . $field, '');
                    }
                }
                db_query("UPDATE ad_orders SET status = 'cancelled', reason = ?, decided_by = ?,
                          decided_at = NOW() WHERE id = ?",
                    [$note === '' ? null : substr($note, 0, 255), (string)$admin['username'], $id]);
                $say = 'cancelled';
            }

            log_action('ad_order_' . $action, 'settings', null, 'order #' . $id . ' ' . $say
                . ' (was ' . $status . ')');

            $to = (string)$order['contact_email'];
            if (valid_email_addr($to) && mail_pref_allows($to, 'account')) {
                $previous = lang_switch('en');
                send_mail($to, 'Your advertising order #' . $id, implode("\n", array_merge(
                    [t('mail.dear', (string)$order['contact_name']), ''],
                    [$action === 'order_approve'
                        ? 'The wording is accepted. Once the payment has arrived the advertisement goes live.'
                        : ($action === 'order_paid'
                            ? 'The payment was received: your advertisement is being placed now.'
                            : ($action === 'order_reject'
                                ? 'Your order was not accepted.'
                                : 'Your order was cancelled.'))],
                    $note === '' ? [] : ['', 'Note: ' . $note],
                    ['', 'Slot: ' . (string)$order['slot'] . ' - ' . (int)$order['months'] . ' month(s)',
                     'Title: ' . (string)$order['title'], '',
                     rtrim(app_base_url(), '/') . '/advertise.php']
                )));
                lang_switch($previous);
            }

            flash('Order #' . $id . ' ' . $say . '.', 'ok');
            redirect('ads.php');
        }
    }

    if ($action === 'save') {
        $changed = 0;
        $values  = [
            'hiring_text'   => trim((string)($_POST['hiring_text'] ?? '')),
            'hiring_text_zh' => trim((string)($_POST['hiring_text_zh'] ?? '')),
            'jobs_text'     => trim((string)($_POST['jobs_text'] ?? '')),
            'jobs_text_zh'  => trim((string)($_POST['jobs_text_zh'] ?? '')),
            // Selling: prices per slot per month, the bundle, the currency and the limits of an order.
            'ad_enabled'    => isset($_POST['ad_enabled']) ? '1' : '0',
            'ad_currency'   => trim((string)($_POST['ad_currency'] ?? 'CNY')),
            'ad_price_bundle' => (string)max(0, (int)($_POST['ad_price_bundle'] ?? 0)),
            'ad_min_months' => (string)max(1, (int)($_POST['ad_min_months'] ?? 1)),
            'ad_max_months' => (string)max(1, (int)($_POST['ad_max_months'] ?? 12)),
            'ad_contact_note' => trim((string)($_POST['ad_contact_note'] ?? '')),
            'ad_contact_note_zh' => trim((string)($_POST['ad_contact_note_zh'] ?? '')),
            'ad_terms'      => trim((string)($_POST['ad_terms'] ?? '')),
            'ad_terms_zh'   => trim((string)($_POST['ad_terms_zh'] ?? '')),
        ];
        foreach (ad_slots() as $slot => $info) {
            $values['ad_price_' . $slot] = (string)max(0, (int)($_POST['ad_price_' . $slot] ?? 0));
        }
        foreach (ad_slots() as $slot => $info) {
            // "image" is part of the set now: an advertisement may carry a picture, hosted by the advertiser.
            foreach (['title', 'url', 'note', 'title_zh', 'note_zh', 'image', 'body', 'body_zh'] as $field) {
                $values['ad_' . $slot . '_' . $field] = trim((string)($_POST['ad_' . $slot . '_' . $field] ?? ''));
            }
        }

        foreach ($values as $key => $value) {
            if (str_ends_with($key, '_url') && $value !== ''
                && preg_match('#^https?://#i', $value) !== 1) {
                $errors[] = 'A link has to start with http:// or https:// (got: ' . $value . ').';
                continue;
            }
            // A picture has to be an absolute address as well - and https, because this site is served over
            // https and a browser silently drops an http picture (mixed content).
            if (str_ends_with($key, '_image') && $value !== '') {
                if (preg_match('#^https://#i', $value) !== 1) {
                    $errors[] = 'A picture link has to start with https:// - an http picture would not be shown '
                        . 'on an https page (got: ' . $value . ').';
                    continue;
                }
            }
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                $changed++;
            }
        }

        if ($errors === []) {
            log_action('ads_save', 'settings', null, $changed . ' advertising value(s) changed');
            flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
            redirect('ads.php');
        }
    }
}

page_header('Advertising');
?>
<p>
Each slot below is plain text and a link. <strong>Leave a slot empty and it shows the hiring notice
instead</strong> - so an unsold place never looks broken. Links must start with <code>http://</code> or
<code>https://</code>; external links carry <code>rel="nofollow noopener sponsored"</code>.
</p>

<table>
  <tr><th>Slot</th><th>Where it shows</th><th>Filled?</th></tr>
  <?php foreach (ad_slots() as $slot => $info): ?>
  <tr>
    <td><code><?= h($slot) ?></code></td>
    <td><?= h($info[0]) ?></td>
    <td><?= ad_filled($slot) ? '<strong>yes</strong>' : '<span class="hint">no - hiring notice</span>' ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?= error_list($errors) ?>

<?php
// Orders. Everything the site collected, newest first, with the one button that moves each one on.
$adOrders = [];
try {
    $adOrders = db_all('SELECT * FROM ad_orders ORDER BY id DESC LIMIT 100');
} catch (Throwable $e) {
    $adOrders = [];
}
?>
<h2>Orders (<?= count($adOrders) ?>)</h2>
<?php if ($adOrders === []): ?>
<p>Nothing ordered yet. The page is at <a href="<?= h(url('advertise.php')) ?>">/advertise.php</a>.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Slot</th><th>Months</th><th>Amount</th><th>Advertisement</th><th>Contact</th>
      <th>State</th><th>Runs</th><th></th></tr>
  <?php foreach ($adOrders as $order): ?>
  <tr>
    <td><?= (int)$order['id'] ?></td>
    <td><code><?= h((string)$order['slot']) ?></code></td>
    <td><?= (int)$order['months'] ?></td>
    <td><?= (int)$order['amount'] ?> <?= h((string)$order['currency']) ?></td>
    <td class="small">
      <strong><?= h((string)$order['title']) ?></strong><br>
      <a href="<?= h((string)$order['url']) ?>" rel="nofollow noopener"><?= h(shorten((string)$order['url'], 40)) ?></a>
      <?= trim((string)($order['note'] ?? '')) === '' ? '' : '<br>' . h((string)$order['note']) ?>
      <?= trim((string)($order['image_url'] ?? '')) === '' ? '' : '<br><span class="hint">image: '
          . h(shorten((string)$order['image_url'], 40)) . '</span>' ?>
      <?= trim((string)($order['message'] ?? '')) === '' ? '' : '<br><span class="hint">'
          . h(shorten((string)$order['message'], 120)) . '</span>' ?>
    </td>
    <td class="small"><?= h((string)$order['contact_name']) ?><br><?= h((string)$order['contact_email']) ?>
      <?= trim((string)($order['contact_way'] ?? '')) === '' ? '' : '<br>' . h((string)$order['contact_way']) ?></td>
    <td><?= h((string)$order['status']) ?>
      <?= trim((string)($order['reason'] ?? '')) === '' ? '' : '<br><span class="small">'
          . h((string)$order['reason']) . '</span>' ?>
      <?= trim((string)($order['paid_at'] ?? '')) === '' ? '' : '<br><span class="small">paid '
          . h((string)$order['paid_at']) . '</span>' ?></td>
    <td class="small"><?= h((string)($order['start_at'] ?? '-')) ?><br><?= h((string)($order['end_at'] ?? '')) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="ads.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
        <input type="text" name="reason" maxlength="190" size="12" placeholder="note / reason">
        <input type="submit" name="action" value="order_approve">
        <input type="submit" name="action" value="order_paid">
        <input type="submit" name="action" value="order_reject">
        <input type="submit" name="action" value="order_cancel">
        <input type="submit" name="action" value="order_renew">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">
<strong>order_approve</strong> = the wording is acceptable &middot;
<strong>order_paid</strong> = the money arrived: the advertisement goes up on the next cron run and comes down
by itself when the paid months are over &middot; <strong>order_reject</strong> needs a reason &middot;
<strong>order_cancel</strong> takes it down straight away if that slot currently shows this order.
The applicant is mailed about every one of these.
</p>
<?php endif; ?>

<form method="post" action="ads.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<?php foreach (ad_slots() as $slot => $info): ?>
<h2><?= h($slot) ?> &mdash; <?= h($info[0]) ?></h2>
<label for="<?= h($slot) ?>_title">Title (English; also used for Chinese when the _zh field is empty)</label>
<input type="text" id="<?= h($slot) ?>_title" name="ad_<?= h($slot) ?>_title" maxlength="190"
       value="<?= h((string)setting('ad_' . $slot . '_title', '')) ?>">
<label for="<?= h($slot) ?>_url">Link (http:// or https://)</label>
<input type="text" id="<?= h($slot) ?>_url" name="ad_<?= h($slot) ?>_url" maxlength="190"
       value="<?= h((string)setting('ad_' . $slot . '_url', '')) ?>">
<label for="<?= h($slot) ?>_note">One line of explanation (optional)</label>
<input type="text" id="<?= h($slot) ?>_note" name="ad_<?= h($slot) ?>_note" maxlength="190"
       value="<?= h((string)setting('ad_' . $slot . '_note', '')) ?>">
<label for="<?= h($slot) ?>_title_zh">Title (Chinese, optional)</label>
<input type="text" id="<?= h($slot) ?>_title_zh" name="ad_<?= h($slot) ?>_title_zh" maxlength="190"
       value="<?= h((string)setting('ad_' . $slot . '_title_zh', '')) ?>">
<label for="<?= h($slot) ?>_note_zh">Explanation (Chinese, optional)</label>
<input type="text" id="<?= h($slot) ?>_note_zh" name="ad_<?= h($slot) ?>_note_zh" maxlength="190"
       value="<?= h((string)setting('ad_' . $slot . '_note_zh', '')) ?>">
<label for="<?= h($slot) ?>_image">Picture link (optional, https, hosted by the advertiser)</label>
<input type="text" id="<?= h($slot) ?>_image" name="ad_<?= h($slot) ?>_image" maxlength="190"

       value="<?= h((string)setting('ad_' . $slot . '_image', '')) ?>" placeholder="https://">

              <label for="<?= h($slot) ?>_body">Body (text advertisement, up to 300 characters)</label>
              <textarea id="<?= h($slot) ?>_body" name="ad_<?= h($slot) ?>_body" rows="4" maxlength="300"><?= h((string)setting('ad_' . $slot . '_body', '')) ?></textarea>
              <p class="hint">Leave the picture field empty and this is a text advertisement. The box is <?= (int)(ad_slots()[$slot]['w'] ?? 0) ?>&times;<?= (int)(ad_slots()[$slot]['h'] ?? 0) ?> px.</p>

              <label for="<?= h($slot) ?>_body_zh">正文（中文，可空，最多 300 字）</label>
              <textarea id="<?= h($slot) ?>_body_zh" name="ad_<?= h($slot) ?>_body_zh" rows="4" maxlength="300"><?= h((string)setting('ad_' . $slot . '_body_zh', '')) ?></textarea>
              <p class="hint">中文访客看到这一栏；留空回落到英文正文。</p>
<?php if (ad_image($slot) !== ''): ?>
<p class="hint">Now showing: <a href="<?= h(ad_image($slot)) ?>" rel="nofollow noopener"><?= h(shorten(ad_image($slot), 60)) ?></a>
&middot; <img src="<?= h(ad_image($slot)) ?>" alt="" style="max-height:60px;vertical-align:middle;border:1px solid #ccc"></p>
<?php else: ?>
<p class="hint">Leave empty for a text-only advertisement. A picture has to be served over <strong>https</strong>
(an http one is dropped by the browser on an https page) and must not be protected against hotlinking, or it
will show as an empty space.</p>
<?php endif; ?>
<?php endforeach; ?>

<h2>Selling: prices and terms</h2>
<p class="hint">A price of <strong>0</strong> means that slot is not for sale: it disappears from the order form
and the advertising page shows "ask us" for it. The buyer sees these prices on
<a href="<?= h(url('advertise.php')) ?>">/advertise.php</a>.</p>
<p>
  <input type="checkbox" id="ad_enabled" name="ad_enabled" value="1"<?= setting_bool('ad_enabled', true) ? ' checked' : '' ?>>
  <label class="inline" for="ad_enabled">Orders are open (the form on the advertising page works)</label>
</p>
<label for="ad_currency">Currency code shown next to prices (CNY, USD, ...)</label>
<input type="text" id="ad_currency" name="ad_currency" maxlength="8"
       value="<?= h((string)setting('ad_currency', 'CNY')) ?>">
<?php foreach (ad_slots() as $slot => $info): ?>
<label for="ad_price_<?= h($slot) ?>">Price per month &mdash; <?= h($slot) ?> (<?= h($info[0]) ?>)</label>
<input type="text" id="ad_price_<?= h($slot) ?>" name="ad_price_<?= h($slot) ?>" maxlength="7" size="7"
       value="<?= (int)setting('ad_price_' . $slot, '0') ?>">
<?php endforeach; ?>
<label for="ad_price_bundle">Price per month &mdash; both rails together (0 = no bundle offer)</label>
<input type="text" id="ad_price_bundle" name="ad_price_bundle" maxlength="7" size="7"
       value="<?= (int)setting('ad_price_bundle', '0') ?>">
<label for="ad_min_months">Shortest order (months)</label>
<input type="text" id="ad_min_months" name="ad_min_months" maxlength="2" size="3"
       value="<?= (int)setting('ad_min_months', '1') ?>">
<label for="ad_max_months">Longest order (months)</label>
<input type="text" id="ad_max_months" name="ad_max_months" maxlength="2" size="3"
       value="<?= (int)setting('ad_max_months', '12') ?>">
<label for="ad_contact_note">How it works and how to pay (English)</label>
<textarea id="ad_contact_note" name="ad_contact_note" rows="4"><?= h((string)setting('ad_contact_note', '')) ?></textarea>
<label for="ad_contact_note_zh">How it works and how to pay (Chinese)</label>
<textarea id="ad_contact_note_zh" name="ad_contact_note_zh" rows="4"><?= h((string)setting('ad_contact_note_zh', '')) ?></textarea>
<label for="ad_terms">Rules the buyer should know (English)</label>
<textarea id="ad_terms" name="ad_terms" rows="4"><?= h((string)setting('ad_terms', '')) ?></textarea>
<label for="ad_terms_zh">Rules the buyer should know (Chinese)</label>
<textarea id="ad_terms_zh" name="ad_terms_zh" rows="4"><?= h((string)setting('ad_terms_zh', '')) ?></textarea>

<h2>Hiring notice (shown in an empty slot)</h2>
<label for="hiring_text">Text (English)</label>
<input type="text" id="hiring_text" name="hiring_text" maxlength="190"
       value="<?= h((string)setting('hiring_text', '')) ?>" placeholder="We are hiring - take a look">
<label for="hiring_text_zh">Text (Chinese)</label>
<input type="text" id="hiring_text_zh" name="hiring_text_zh" maxlength="190"
       value="<?= h((string)setting('hiring_text_zh', '')) ?>" placeholder="我们在招人 —— 点这里看看">
<p class="hint">The notice always points at <code>jobs.php</code>.</p>

<h2>Text of the jobs page</h2>
<label for="jobs_text">Page text (English; leave empty for the built-in wording)</label>
<textarea id="jobs_text" name="jobs_text" rows="10"><?= h((string)setting('jobs_text', '')) ?></textarea>
<label for="jobs_text_zh">Page text (Chinese)</label>
<textarea id="jobs_text_zh" name="jobs_text_zh" rows="10"><?= h((string)setting('jobs_text_zh', '')) ?></textarea>
<p class="hint">Plain text; line breaks are kept. The page is at <code>/jobs.php</code> and
<code>/zh/jobs.php</code>.</p>

<p><input type="submit" value="Save"></p>
</form>
<?php
page_footer();
