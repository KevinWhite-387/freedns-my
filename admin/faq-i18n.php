<?php
declare(strict_types=1);

/**
 * freedns.my - back office: FAQ translations.
 *
 * One language at a time, on a page of its own, so the item form on admin/faq.php stays readable.
 * Leaving the question empty deletes that language and the front page falls back to English.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_owner('FAQ translations');

$zh   = function_exists('lang') && is_zh();
$langs = [
    'en'      => 'English',
    'zh'      => '简体中文',
    'zh-hant' => '繁體中文',
    'ja'      => '日本語',
    'ko'      => '한국어',
    'ms'      => 'Bahasa Melayu',
];

$itemId = (int)($_GET['item'] ?? $_POST['item'] ?? 0);
$items  = faq_items_all();
if ($itemId <= 0 && $items !== []) {
    $itemId = (int)$items[0]['id'];
}
$item = $itemId > 0 ? db_one('SELECT * FROM faq_items WHERE id = ? LIMIT 1', [$itemId]) : null;

$ok    = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $item !== null) {
    csrf_verify();
    $posted = (array)($_POST['t'] ?? []);
    foreach ($langs as $code => $_) {
        if (!isset($posted[$code])) {
            continue;
        }
        faq_i18n_save(
            $itemId,
            $code,
            (string)($posted[$code]['q'] ?? ''),
            (string)($posted[$code]['a'] ?? '')
        );
    }
    $ok = $zh ? '翻译已保存。' : 'Translations saved.';
}

$map = faq_i18n_all();
$current = $map[$itemId] ?? [];

page_header($zh ? '常见问题翻译' : 'FAQ translations');
?>
<h1><?= h($zh ? '常见问题翻译' : 'FAQ translations') ?></h1>
<hr>

<?php if ($item === null): ?>
  <p class="msg error"><?= h($zh ? '还没有任何条目，先去 /admin/faq.php 建一条。' : 'No entry yet - create one on /admin/faq.php first.') ?></p>
<?php else: ?>

<form method="get" action="<?= h(url('admin/faq-i18n.php')) ?>">
  <p>
    <?= h($zh ? '条目' : 'Entry') ?>
    <select name="item" onchange="this.form.submit()">
      <?php foreach ($items as $it): ?>
        <option value="<?= (int)$it['id'] ?>"<?= (int)$it['id'] === $itemId ? ' selected' : '' ?>>
          #<?= (int)$it['id'] ?> <?= h(faq_text((string)$it['question_en'], (string)$it['question_zh'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="submit" value="<?= h($zh ? '切换' : 'Switch') ?>">
  </p>
</form>

<?php if ($ok !== ''): ?><p class="msg ok"><?= h($ok) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<p class="small"><?= h($zh
    ? '每个语言填一份。题目留空 = 删除该语言，前台自动回退英文。'
    : 'Fill one row per language. An empty question deletes that language and the site falls back to English.') ?></p>

<form method="post" action="<?= h(url('admin/faq-i18n.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="item" value="<?= $itemId ?>">
  <table>
    <thead>
      <tr><th style="width:9em"><?= h($zh ? '语言' : 'Language') ?></th><th><?= h($zh ? '题目' : 'Question') ?></th><th><?= h($zh ? '答案' : 'Answer') ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ($langs as $code => $label): ?>
      <?php
        $q = (string)($current[$code]['question'] ?? '');
        $a = (string)($current[$code]['answer'] ?? '');
        if ($q === '' && $code === 'en') { $q = (string)$item['question_en']; $a = (string)$item['answer_en']; }
        if ($q === '' && $code === 'zh') { $q = (string)$item['question_zh']; $a = (string)$item['answer_zh']; }
      ?>
      <tr>
        <td><strong><?= h($label) ?></strong><br><span class="small"><?= h($code) ?></span></td>
        <td><input type="text" name="t[<?= h($code) ?>][q]" size="44" value="<?= h($q) ?>"></td>
        <td><textarea name="t[<?= h($code) ?>][a]" rows="4" cols="46"><?= h($a) ?></textarea></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p><input type="submit" value="<?= h($zh ? '保存全部语言' : 'Save all languages') ?>"></p>
</form>

<p class="small">
  <?= h($zh ? '前台预览：' : 'Public page: ') ?><a href="<?= h(url('faq.php')) ?>" target="_blank"><?= h(url('faq.php')) ?></a>
</p>
<?php endif; ?>

<?php page_footer(); ?>