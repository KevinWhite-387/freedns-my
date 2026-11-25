<?php
declare(strict_types=1);

/**
 * freedns.my - about us, including where the money comes from and where it goes.
 *
 * One page rather than two: what this registry is, and how it is paid for. Nobody has to pay to use it, so the
 * honest answer to "how does it keep running" belongs on the same page as the introduction.
 *
 * Advertising income is counted by the site itself (only orders that were really paid for), so that figure
 * cannot drift away from reality. Donations and expenses happen outside the site, so the registry writes them
 * down by hand in the back office (Back office -> Finance), one line per entry, in any currency - foreign
 * donations are recorded with the amount in yuan at the rate of the day.
 */

require __DIR__ . '/inc/bootstrap.php';

$zh = function_exists('lang') && is_zh();

/** The advertising money that actually arrived. */
function about_ad_income(): array
{
    $out = ['total' => 0, 'count' => 0, 'since' => '', 'currency' => trim((string)setting('ad_currency', 'CNY'))];
    try {
        $row = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total, MIN(paid_at) AS first_paid
                       FROM ad_orders WHERE status IN ('paid', 'active', 'expired')");
        if ($row !== null) {
            $out['total'] = (int)$row['total'];
            $out['count'] = (int)$row['n'];
            $out['since'] = trim((string)($row['first_paid'] ?? ''));
        }
    } catch (Throwable $e) {
        // Table not imported yet, or the database is away: the rest of the page still works.
    }

    return $out;
}

function about_money(int $amount, string $currency): string
{
    return ($currency === 'CNY' ? '¥' : $currency . ' ') . number_format($amount, 2, '.', ',');
}

/** A block the registry wrote in the back office: plain text, line breaks kept. */
function about_block(string $key): string
{
    return trim((string)setting_lang($key));
}

$ads       = about_ad_income();
$intro     = about_block('about_intro');
$donations = about_block('finance_donations');
$expenses  = about_block('finance_expenses');
$uses      = about_block('finance_uses');
$totals    = about_block('finance_totals');
$how       = about_block('finance_donate_how');
$crypto    = about_block('finance_crypto');

page_header($zh ? '关于我们' : 'About us');
?>
<p><?= $zh
    ? 'freedns.my 是一个免费二级域名注册局：注册域名、DNS 解析、邮箱都不收费，也不需要绑卡。它由志愿者维护，靠捐赠与少量广告收入维持。'
    : 'freedns.my is a free second level domain registry: names, DNS and mailboxes cost nothing and need no card. '
      . 'It is run by volunteers and kept alive by donations and a small amount of advertising.' ?></p>

<?php if ($intro !== ''): ?>
<div style="white-space:pre-wrap"><?= h($intro) ?></div>
<?php endif; ?>

<h2><?= $zh ? '免费的东西，钱从哪来' : 'It is free, so where does the money come from' ?></h2>
<p><?= $zh
    ? '域名本身免费，但注册局不是：域名托管、DNS 解析、服务器、邮件服务与备份都要花钱，还有维护它所需的时间。'
      . '我们没有付费套餐，也不打算把用户数据拿去变现。'
    : 'The names are free, but running a registry is not: registration, DNS service, servers, the mail service and '
      . 'backups all cost money, and so does the time it takes to keep them working. There is no paid tier here, '
      . 'and no plan to turn anybody\'s data into income.' ?></p>

<h2><?= $zh ? '广告收入（本站自动统计）' : 'Advertising income (counted by the site itself)' ?></h2>
<table>
  <tr><th><?= $zh ? '项目' : 'Item' ?></th><th><?= $zh ? '数值' : 'Figure' ?></th></tr>
  <tr>
    <td><?= $zh ? '已收到的广告费合计' : 'Advertising paid for, in total' ?></td>
    <td><strong><?= h(about_money($ads['total'], $ads['currency'])) ?></strong>
      <?= $zh ? '（' . (int)$ads['count'] . ' 笔订单）' : '(' . (int)$ads['count'] . ' order(s))' ?></td>
  </tr>
  <?php if ($ads['since'] !== ''): ?>
  <tr><td><?= $zh ? '最早一笔收款时间' : 'First payment received' ?></td><td><?= h($ads['since']) ?></td></tr>
  <?php endif; ?>
  <tr>
    <td><?= $zh ? '广告位与价格' : 'Slots and prices' ?></td>
      &middot; <a href="<?= h(url('jobs.php')) ?>"><?= $zh ? '加入我们' : 'Work with us' ?></a></td>
  </tr>
</table>
<p class="hint"><?= $zh
    ? '这个数字由系统统计：只有状态为「已收款 / 投放中 / 已到期」的订单会被计入，所以它不会与实际收款脱节。'
    : 'This figure comes from the site: only orders that were really paid for (paid, running, or finished) are '
      . 'counted, so it cannot drift away from what was actually received.' ?></p>

<h2><?= $zh ? '捐赠明细' : 'Donations, one by one' ?></h2>
<?php if ($donations !== ''): ?>
<div class="msg info" style="white-space:pre-wrap"><?= h($donations) ?></div>
<?php else: ?>
<p class="hint"><?= $zh
    ? '还没有记录捐赠明细（或暂未公开）。有捐赠时，这里会按时间列出金额、来源与用途；不愿具名的捐赠人会写为“匿名网友”。'
    : 'No donations are recorded yet (or none are published yet). When there are any, the amount, where it came '
      . 'from and what it was used for are listed here; a donor who prefers to stay unnamed is listed as '
      . '"anonymous".' ?></p>
<?php endif; ?>

<h2><?= $zh ? '支出明细' : 'Expenses, one by one' ?></h2>
<?php if ($expenses !== ''): ?>
<div class="msg info" style="white-space:pre-wrap"><?= h($expenses) ?></div>
<?php else: ?>
<p class="hint"><?= $zh ? '支出明细暂未公开。' : 'No expenses are published yet.' ?></p>
<?php endif; ?>

<?php if ($uses !== ''): ?>
<h2><?= $zh ? '这些钱花在哪里' : 'Where it actually goes' ?></h2>
<div style="white-space:pre-wrap"><?= h($uses) ?></div>
<?php endif; ?>

<?php if ($totals !== ''): ?>
<h2><?= $zh ? '合计' : 'Totals' ?></h2>
<div style="white-space:pre-wrap"><?= h($totals) ?></div>
<?php endif; ?>

<h2><?= $zh ? '怎么捐赠' : 'How to donate' ?></h2>
<?php if ($how !== ''): ?>
<div style="white-space:pre-wrap"><?= h($how) ?></div>
<?php else: ?>
<p class="hint"><?= $zh
    ? '捐赠方式暂未公开。可以通过页脚的邮箱与我们联系。'
    : 'The ways to donate are not published yet. Write to the address in the footer and we will tell you.' ?></p>
<?php endif; ?>

<?php if ($crypto !== ''): ?>
<h2><?= $zh ? '加密货币' : 'Cryptocurrency' ?></h2>
<div style="white-space:pre-wrap"><?= h($crypto) ?></div>
<p class="hint"><?= $zh
    ? '请在链上确认后再算作捐赠；到账金额按当时汇率折算为人民币记录在明细里。务请核对地址首尾几位，链上转账不可撤回。'
    : 'Please confirm on chain before counting it as a donation; the amount is recorded in yuan at the rate of '
      . 'that day. Check the first and last characters of the address: a transfer on chain cannot be undone.' ?></p>
<?php endif; ?>

<h2><?= $zh ? '我们承诺什么' : 'What we promise about this page' ?></h2>
<ul>
  <li><?= $zh ? '数字如实：广告收入由系统统计，捐赠与支出由维护者手工逐笔记录，多币种按当时汇率折算，有错我们会更正。'
               : 'The figures are honest: advertising income is counted by the site, donations and expenses are '
                 . 'written down by hand one by one, foreign amounts are converted at the rate of the day, and a '
                 . 'mistake is ours to correct.' ?></li>
  <li><?= $zh ? '捐赠不换取特权：不会因此优先审核、优先分配服务器或影响任何审核结论。'
               : 'A donation buys no privileges: no faster review, no preferred server, no influence on any '
                 . 'decision about a request.' ?></li>
  <li><?= $zh ? '广告与审核分离：广告主要遵守同样的规则，违规照样下架；广告不会影响任何域名的审核。'
               : 'Advertising is kept apart from review: an advertiser follows the same rules and is taken down '
                 . 'like anybody else, and advertising never influences a decision about a name.' ?></li>
  <li><?= $zh ? '数据不卖：我们不出售、不交换用户数据，本站也不运行第三方追踪代码。'
               : 'Data is not for sale: user data is not sold or traded, and this site runs no third-party '
                 . 'tracking code.' ?></li>
  <li><?= $zh ? '有结余就留着：结余用于下一年的域名、服务器与邮件服务，不用于分红。'
               : 'A surplus stays in the project: it goes towards next year\'s registration, servers and mail '
                 . 'service, never to anybody\'s pocket.' ?></li>
</ul>
<p class="hint"><?= $zh
    ? '记录有疑问（金额、时间、用途）请通过页脚的邮箱联系我们，我们会核对并更正。'
    : 'If a figure looks wrong, write to the address in the footer: we check it and correct it.' ?></p>
<h2><?= $zh ? '也看看' : 'Also worth a look' ?></h2>
<ul>
  <li><a href="<?= h(url('partners.php')) ?>"><?= $zh ? '其它免费域名与 DNS 平台' : 'Other registries and DNS services' ?></a></li>
  <li><a href="<?= h(url('jobs.php')) ?>"><?= $zh ? '加入我们' : 'Work with us' ?></a></li>
</ul>
<?php
page_footer();
