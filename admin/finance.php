<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the About us page, including the money.
 *
 * Everything the public page shows that is not counted by the site itself is written here: the introduction,
 * the donations one by one, the expenses one by one, where the money goes, the totals, how to donate, and the
 * cryptocurrency addresses. Plain text, line breaks kept, any currency - the advertising income needs no
 * typing, the site adds that up by itself.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('About us');
$errors = [];

$fields = [
    'about_intro'        => 'Introduction, above the money section',
    'finance_donations'  => 'Donations, one per line (date, amount, from, what for)',
    'finance_expenses'   => 'Expenses, one per line (date, item, amount)',
    'finance_uses'       => 'Where the money goes (a paragraph or a list)',
    'finance_totals'     => 'Totals (income, expenses, balance)',
    'finance_donate_how' => 'How to donate (Alipay / WeChat / bank details)',
    'finance_crypto'     => 'Cryptocurrency (chain, address, and any note)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $changed = 0;
        foreach (array_keys($fields) as $key) {
            foreach ([$key, $key . '_zh'] as $name) {
                $value = trim((string)($_POST[$name] ?? ''));
                if ((string)setting($name, '') !== $value) {
                    set_setting($name, $value);
                    $changed++;
                }
            }
        }
        log_action('about_save', 'settings', null, $changed . ' about/finance value(s) changed');
        flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
        redirect('finance.php');
    }
}

$row = null;
try {
    $row = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total
                   FROM ad_orders WHERE status IN ('paid', 'active', 'expired')");
} catch (Throwable $e) {
    $row = null;
}

page_header('About us and the money');
?>
<p>
The public page is <a href="<?= h(url('about.php')) ?>">/about.php</a> (Chinese: <code>/zh/about.php</code>).
Advertising income is counted by the site; everything else on that page comes from the boxes below.
</p>
<?php if ($row !== null): ?>
<div class="msg ok">Advertising income counted by the site right now:
<strong><?= (int)$row['total'] ?></strong> in <?= (int)$row['n'] ?> paid order(s). It is added to the page
automatically - do not type it into the totals below.</div>
<?php else: ?>
<div class="msg error">The advertising orders table is not there yet, so the income figure cannot be counted.
Import <code>upgrade-all-in-one.sql</code> first.</div>
<?php endif; ?>
<p class="hint">Every box has an English and a Chinese version. Leave the English one empty to use only Chinese.
Line breaks are kept, so write one entry per line, for example:<br>
<code>2026-10-01  ¥50.00  匿名网友  服务器续费</code><br>
<code>2026-10-03  $10.00 (≈¥72)  A. Someone  mail server</code></p>

<?= error_list($errors) ?>

<form method="post" action="finance.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<?php foreach ($fields as $key => $label): ?>
<h2><?= h($label) ?></h2>
<label for="<?= h($key) ?>">English</label>
<textarea id="<?= h($key) ?>" name="<?= h($key) ?>" rows="6"><?= h((string)setting($key, '')) ?></textarea>
<label for="<?= h($key) ?>_zh">Chinese</label>
<textarea id="<?= h($key) ?>_zh" name="<?= h($key) ?>_zh" rows="6"><?= h((string)setting($key . '_zh', '')) ?></textarea>
<?php endforeach; ?>
<p><input type="submit" value="Save"></p>
</form>

<h2>Preview</h2>
<p class="hint">What the donation and expense boxes look like as they are now.</p>
<pre style="white-space:pre-wrap;border:1px solid #ccc;padding:.6em"><?= h(setting_lang('finance_donations')) ?>

<?= h(setting_lang('finance_expenses')) ?></pre>
<?php
page_footer();
