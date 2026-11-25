<?php
declare(strict_types=1);

/**
 * freedns.my - frequently asked questions.
 *
 * Search and the category tabs are plain links and a GET form: no JavaScript anywhere. Every entry
 * is a <details> element, so it opens without a script and degrades to a plain list of questions
 * with their answers visible when <details> is not supported.
 */

require __DIR__ . '/inc/bootstrap.php';

$zh = function_exists('lang') && is_zh();

$q    = trim((string)($_GET['q'] ?? ''));
$slug = strtolower(trim((string)($_GET['c'] ?? '')));
$cats = faq_categories();
$rows = faq_items($q, $slug);

page_header($zh ? '常见问题' : 'Frequently asked questions');
?>
<h1><?= h($zh ? '常见问题' : 'Frequently asked questions') ?></h1>
<p class="sub"><?= h($zh
    ? '这里是用户问得最多的问题。没找到答案可以直接联系我们。'
    : 'The questions we get asked the most. If yours is not here, just write to us.') ?></p>
<hr>

<form method="get" action="<?= h(url('faq.php')) ?>">
  <p>
    <input type="search" name="q" value="<?= h($q) ?>" size="34"
           placeholder="<?= h($zh ? '搜索问题或关键词' : 'search questions or keywords') ?>">
    <?php if ($slug !== ''): ?><input type="hidden" name="c" value="<?= h($slug) ?>"><?php endif; ?>
    <input type="submit" value="<?= h($zh ? '搜索' : 'Search') ?>">
    <?php if ($q !== '' || $slug !== ''): ?>
      <a href="<?= h(url('faq.php')) ?>"><?= h($zh ? '清除' : 'Clear') ?></a>
    <?php endif; ?>
  </p>
</form>

<?php if ($cats !== []): ?>
<p class="small">
  <?php if ($slug === ''): ?>
    <strong><?= h($zh ? '全部' : 'All') ?></strong>
  <?php else: ?>
    <a href="<?= h(url('faq.php' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>"><?= h($zh ? '全部' : 'All') ?></a>
  <?php endif; ?>
  <?php foreach ($cats as $c): ?>
    &middot;
    <?php $label = faq_text((string)$c['title_en'], (string)$c['title_zh']); ?>
    <?php $href = url('faq.php?c=' . urlencode((string)$c['slug']) . ($q !== '' ? '&q=' . urlencode($q) : '')); ?>
    <?php if ($slug === (string)$c['slug']): ?>
      <strong><?= h($label) ?></strong>
    <?php else: ?>
      <a href="<?= h($href) ?>"><?= h($label) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</p>
<?php endif; ?>

<p class="small"><?= h($zh ? '共 ' : 'Found ') ?><strong><?= count($rows) ?></strong><?= h($zh ? ' 条' : ' entries') ?></p>

<?php if ($rows === []): ?>
  <p class="msg info"><?= h($zh
      ? '没有匹配的问题。换个关键词，或者直接联系我们。'
      : 'No matching question. Try another keyword, or just contact us.') ?></p>
<?php else: ?>
  <?php $lastCat = null; ?>
  <?php foreach ($rows as $r): ?>
    <?php
      $catLabel = faq_text((string)($r['category_en'] ?? ''), (string)($r['category_zh'] ?? ''));
      if ($catLabel === '') {
          $catLabel = $zh ? '其它' : 'Other';
      }
    ?>
    <?php if ($catLabel !== $lastCat): ?>
      <?php $lastCat = $catLabel; ?>
      <h2><?= h($catLabel) ?></h2>
    <?php endif; ?>
    <details style="margin:.4em 0;border-bottom:1px solid #ddd;padding-bottom:.4em">
      <summary style="cursor:pointer;font-weight:600">
        <?= h(faq_item_field($r, 'question')) ?>
      </summary>
      <div style="white-space:pre-wrap;margin:.5em 0 0 1em">
<?= h(faq_item_field($r, 'answer')) ?>
      </div>
    </details>
  <?php endforeach; ?>
<?php endif; ?>

<hr>
<h2><?= h($zh ? '还没解决？' : 'Still stuck?') ?></h2>
<p><?= h($zh
    ? '把问题发给我们，写清你申请的域名、账号邮箱和看到的现象，回复会快很多。'
    : 'Write to us with the name you requested, the account e-mail and what you saw; it speeds things up.') ?></p>
<p>
  <a href="<?= h(url('abuse.php')) ?>"><?= h($zh ? '联系注册局' : 'Contact the registry') ?></a>
  &middot;
  <a href="<?= h(url('doc.php')) ?>"><?= h($zh ? '政策与文档' : 'Policy and docs') ?></a>
  &middot;
  <a href="<?= h(url('chat.php')) ?>"><?= h($zh ? '在线聊天' : 'Live chat') ?></a>
</p>

<?php page_footer(); ?>