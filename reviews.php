<?php
declare(strict_types=1);

/**
 * freedns.my - the rating page: 1 to 5 stars for the registry itself.
 *
 * One vote per account, and it can be changed whenever the holder likes. The score is drawn with characters
 * (★★★★☆) because the site has no images and no JavaScript.
 *
 * A written review appears straight away, except when it looks like advertising (a link or a contact handle) or
 * mentions gambling, adult content or drugs - those wait for the registry to look at them. In both cases the
 * writer is told the review was received, and he can see his own text at the top of this page whatever its
 * state.
 */

require __DIR__ . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
评价本站
');

/*
 * Loaded here rather than from the bootstrap: this page is the only one that needs it so far, and requiring it
 * once keeps a second include harmless (requiring it twice would redeclare the functions and take the page
 * down, which is a mistake this project has made before).
 */
require_once __DIR__ . '/inc/reviews.php';

$zh     = function_exists('lang') && is_zh();
$user   = function_exists('current_user') ? current_user() : null;
$errors = [];
$done   = '';

if ($server_method = ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
    if ($server_method === 'POST') {
        csrf_verify();
        if ($user === null) {
            $errors[] = $zh ? '评价需要登录。' : 'Rating needs a signed-in account.';
        } else {
            $result = review_save($user, (int)($_POST['stars'] ?? 0), (string)($_POST['body'] ?? ''));
            if ($result['ok']) {
                // Deliberately the same message either way: whether it is already live or waiting for a look is
                // something the writer can see further down this page, and saying more would only help people
                // work out how to get past the filter.
                $done = $zh
                    ? '评价已提交，谢谢。你可以随时回到这一页修改自己的评价。'
                    : 'Your review was received, thank you. You can come back and change it whenever you like.';
            } else {
                $errors[] = (string)$result['error'];
            }
        }
    }
}

$stats = review_stats();
$mine  = $user === null ? null : review_mine((int)$user['id']);
$list  = review_list(30);

page_header($zh ? '评价本站' : 'Rate this registry');
?>
<p><?= $zh
    ? '这里可以给 freedns.my 整体打分（1 到 5 星）：服务是否稳定、解析是否够快、邮箱好不好用、遇到问题时我们回得怎么样。'
      . '每个账号一票，随时可以改。'
    : 'This is where you score freedns.my as a whole, from one to five stars: whether it stays up, whether DNS '
      . 'answers quickly, whether the mailboxes are usable, and how we answer when something goes wrong. One '
      . 'vote per account, changeable at any time.' ?></p>

<h2><?= $zh ? '现在的评分' : 'The score so far' ?></h2>
<?php if ($stats['count'] === 0): ?>
<p class="hint"><?= $zh
    ? '还没有公开的评价。第一个评分的人就是你。'
    : 'No published reviews yet - the first one would be yours.' ?></p>
<?php else: ?>
<table>
  <tr>
    <td><strong style="font-size:1.4em"><?= h(review_stars((int)round($stats['average']))) ?></strong></td>
    <td><strong><?= h(number_format($stats['average'], 2)) ?></strong> / 5
      &middot; <?= $zh ? $stats['count'] . ' 条评价' : $stats['count'] . ' review(s)' ?></td>
  </tr>
  <?php for ($stars = 5; $stars >= 1; $stars--): ?>
  <tr>
    <td><code><?= h(review_stars($stars)) ?></code></td>
    <td><?= (int)$stats['dist'][$stars] ?>
      <?= $zh ? '条' : '' ?>
      (<?= $stats['count'] > 0 ? (int)round($stats['dist'][$stars] * 100 / $stats['count']) : 0 ?>%)</td>
  </tr>
  <?php endfor; ?>
</table>
<?php endif; ?>

<h2><?= $zh ? '你的评价' : 'Your review' ?></h2>
<?php if ($user === null): ?>
<div class="msg info"><?= $zh
    ? '评价需要登录，这是为了让每个账号只有一票。'
    : 'Rating needs a signed-in account, so that every account counts once.' ?>
  <a href="<?= h(url('account/login.php')) ?>"><?= $zh ? '登录' : 'Sign in' ?></a>
  &middot; <a href="<?= h(url('account/register.php')) ?>"><?= $zh ? '注册' : 'Register' ?></a>
</div>
<?php else: ?>
<?php if ($done !== ''): ?><div class="msg ok"><?= h($done) ?></div><?php endif; ?>
<?= error_list($errors) ?>

<?php if ($mine !== null): ?>
<div class="msg <?= (string)$mine['status'] === 'published' ? 'ok' : 'info' ?>">
  <strong><?= $zh ? '你现在的评价' : 'What you wrote' ?>:</strong>
  <?= h(review_stars((int)$mine['stars'])) ?>
  <span class="small">(<?= h((string)$mine['updated_at']) ?>)</span>
  <?php if ((string)$mine['status'] !== 'published'): ?>
  <br><span class="small"><?= $zh
      ? '这条评价正在人工确认中，暂时只有你自己能看到。'
      : 'This one is waiting for a human look, so only you can see it for now.' ?></span>
  <?php endif; ?>
  <?php if (trim((string)($mine['body'] ?? '')) !== ''): ?>
  <div style="white-space:pre-wrap;margin-top:6px"><?= h((string)$mine['body']) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="<?= h(url('reviews.php')) ?>">
<?= csrf_field() ?>
<label for="stars"><?= $zh ? '给几分' : 'How many stars' ?></label>
<select id="stars" name="stars">
  <?php for ($stars = 5; $stars >= 1; $stars--): ?>
  <option value="<?= $stars ?>"<?= $mine !== null && (int)$mine['stars'] === $stars ? ' selected' : '' ?>>
    <?= h(review_stars($stars)) ?> (<?= $stars ?>)
  </option>
  <?php endfor; ?>
</select>
<label for="body"><?= $zh ? '想说点什么（可留空，最多 500 字）' : 'Anything to add (optional, up to 500 characters)' ?></label>
<textarea id="body" name="body" rows="6"><?= h((string)($mine['body'] ?? '')) ?></textarea>
<p class="hint"><?= $zh
    ? '请勿在评价里放链接或联系方式（会被转入人工确认）。想匿名就在开头写“匿名”。评价随时可以改，改完覆盖原来那条。'
    : 'Please do not put links or contact details in a review (those wait for a human). To stay unnamed, start '
      . 'the text with "anonymous". You can change your review at any time; it replaces the old one.' ?></p>
<p><input type="submit" value="<?= $mine === null
    ? ($zh ? '提交评价' : 'Send my review')
    : ($zh ? '更新我的评价' : 'Update my review') ?>"></p>
</form>
<?php endif; ?>

<h2><?= $zh ? '大家怎么说' : 'What others wrote' ?></h2>
<?php if ($list === []): ?>
<p class="hint"><?= $zh ? '还没有公开的评价。' : 'No published reviews yet.' ?></p>
<?php else: ?>
<table>
  <?php foreach ($list as $row): ?>
  <tr>
    <td><code><?= h(review_stars((int)$row['stars'])) ?></code></td>
    <td class="small"><?= h(review_author($row)) ?> &middot; <?= h((string)$row['updated_at']) ?></td>
    <td><?php $body = trim((string)($row['body'] ?? '')); ?>
      <?= $body === '' ? '<span class="hint">' . h($zh ? '只打了分' : 'a score, no words') . '</span>'
                       : '<span style="white-space:pre-wrap">' . h($body) . '</span>' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= $zh ? '说明' : 'How this works' ?></h2>
<ul>
  <li><?= $zh ? '每个账号一票，可以随时修改，改完覆盖原来那条，不会重复计数。'
               : 'One vote per account. Changing it replaces the old one, so nothing is counted twice.' ?></li>
  <li><?= $zh ? '评分不换取任何特权：给几星都不影响你的域名、邮箱或服务器，也不影响任何审核结论。'
               : 'A score buys nothing: it has no effect on your names, mailboxes or servers, and none on any '
                 . 'decision we make.' ?></li>
  <li><?= $zh ? '我们不会因为评价不好看就删掉它；只有广告、违法内容与人身攻击会被下架。'
               : 'An unflattering review is not removed. Advertising, unlawful content and personal attacks are.' ?></li>
  <li><?= $zh ? '评价里不放链接与联系方式；写了会被转入人工确认。'
               : 'Reviews carry no links or contact details; those wait for a human look.' ?></li>
  <li><?= $zh ? '平均分只统计已公开的评价。'
               : 'The average counts published reviews only.' ?></li>
</ul>
<h2><?= $zh ? '也看看' : 'Also worth a look' ?></h2>
<ul>
  <li><a href="<?= h(url('about.php')) ?>"><?= $zh ? '关于我们与资金公开' : 'About us, and the money' ?></a></li>
  <li><a href="<?= h(url('partners.php')) ?>"><?= $zh ? '其它免费域名与 DNS 平台' : 'Other registries and DNS services' ?></a></li>
  <li><a href="<?= h(url('doc.php')) ?>"><?= $zh ? '使用文档' : 'Documentation' ?></a></li>
  <li><a href="<?= h(url('abuse.php')) ?>"><?= $zh ? '举报滥用' : 'Report abuse' ?></a></li>
</ul>
<?php
page_footer();
