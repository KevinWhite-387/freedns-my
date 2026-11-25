<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the FAQ.
 *
 * Categories and entries live on one page because a category exists only to group entries.
 * English and Chinese sit side by side; leaving one empty is allowed and the front page falls back
 * to the other. Deletions ask once; buttons stay small so the tables stay readable in 2.0.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_owner('FAQ');

$zh    = function_exists('lang') && is_zh();
$error = '';
$ok    = '';

/* small buttons: the 2.0 sheet styles bare submits as large pills */
$btn = 'style="padding:2px 10px;font-size:.85em;border-radius:8px"';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'cat') {
        [$good, $why] = faq_save_category(
            (int)($_POST['cat_id'] ?? 0),
            (string)($_POST['slug'] ?? ''),
            (string)($_POST['title_en'] ?? ''),
            (string)($_POST['title_zh'] ?? ''),
            (int)($_POST['sort'] ?? 0)
        );
        if ($good) { $ok = $zh ? '分类已保存。' : 'Category saved.'; } else { $error = $why; }
    }

    if ($action === 'item') {
        $catId = (int)($_POST['category_id'] ?? 0);
        [$good, $why] = faq_save_item(
            (int)($_POST['item_id'] ?? 0),
            $catId > 0 ? $catId : null,
            (string)($_POST['question_en'] ?? ''),
            (string)($_POST['question_zh'] ?? ''),
            (string)($_POST['answer_en'] ?? ''),
            (string)($_POST['answer_zh'] ?? ''),
            (int)($_POST['sort'] ?? 0),
            !empty($_POST['is_public'])
        );
        if ($good) { $ok = $zh ? '条目已保存。' : 'Entry saved.'; } else { $error = $why; }
    }

    if ($action === 'del_item') {
        faq_delete_item((int)($_POST['item_id'] ?? 0));
        $ok = $zh ? '条目已删除。' : 'Entry deleted.';
    }

    if ($action === 'del_cat') {
        faq_delete_category((int)($_POST['cat_id'] ?? 0));
        $ok = $zh ? '分类已删除（其中的条目变为未归类）。' : 'Category deleted; its entries became uncategorised.';
    }
}

$cats  = faq_categories();
$items = faq_items_all();
$editItem = isset($_GET['edit']) ? db_one('SELECT * FROM faq_items WHERE id = ? LIMIT 1', [(int)$_GET['edit']]) : null;
$editCat  = isset($_GET['cat'])  ? db_one('SELECT * FROM faq_categories WHERE id = ? LIMIT 1', [(int)$_GET['cat']])  : null;

$catCount = [];
foreach ($items as $it) {
    $cid = (int)($it['category_id'] ?? 0);
    $catCount[$cid] = ($catCount[$cid] ?? 0) + 1;
}

page_header($zh ? '常见问题管理' : 'FAQ');
?>
<h1><?= h($zh ? '常见问题管理' : 'FAQ') ?></h1>
<p class="sub"><?= h($zh
    ? '分类用来分组；条目是中英两份问答（其它语言在「常见问题翻译」里补）。'
    : 'Categories group entries; each entry carries an English and a Chinese version (other languages live under FAQ translations).') ?></p>
<hr>

<?php if (!faq_ready()): ?>
  <p class="msg error"><?= h($zh ? '缺表：先导入 upgrade-faq.sql。' : 'Tables missing: import upgrade-faq.sql first.') ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>
<?php if ($ok !== ''): ?><p class="msg ok"><?= h($ok) ?></p><?php endif; ?>

<h2><?= h($zh ? '一、分类' : '1. Categories') ?></h2>
<table>
<thead>
  <tr>
    <th style="width:7em">slug</th>
    <th><?= h($zh ? '英文名' : 'English') ?></th>
    <th><?= h($zh ? '中文名' : 'Chinese') ?></th>
    <th style="width:4em"><?= h($zh ? '排序' : 'Sort') ?></th>
    <th style="width:4em"><?= h($zh ? '条目' : 'Items') ?></th>
    <th style="width:11em"><?= h($zh ? '操作' : 'Actions') ?></th>
  </tr>
</thead>
<tbody>
<?php foreach ($cats as $c): ?>
  <tr>
    <td><code><?= h((string)$c['slug']) ?></code></td>
    <td><?= h((string)$c['title_en']) ?></td>
    <td><?= h((string)$c['title_zh']) ?></td>
    <td><?= (int)$c['sort_order'] ?></td>
    <td><?= (int)($catCount[(int)$c['id']] ?? 0) ?></td>
    <td class="small" style="white-space:nowrap">
      <a href="<?= h(url('admin/faq.php?cat=' . (int)$c['id'])) ?>"><?= h($zh ? '编辑' : 'Edit') ?></a>
      &nbsp;
      <form method="post" action="<?= h(url('admin/faq.php')) ?>" style="display:inline"
            onsubmit="return confirm('<?= h($zh ? '删除这个分类？其中的条目会变成未归类。' : 'Delete this category? Its entries become uncategorised.') ?>')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="del_cat">
        <input type="hidden" name="cat_id" value="<?= (int)$c['id'] ?>">
        <input type="submit" <?= $btn ?> value="<?= h($zh ? '删除' : 'Delete') ?>">
      </form>
    </td>
  </tr>
<?php endforeach; ?>
<?php if ($cats === []): ?>
  <tr><td colspan="6"><?= h($zh ? '还没有分类。' : 'No category yet.') ?></td></tr>
<?php endif; ?>
</tbody>
</table>

<h3><?= $editCat === null ? h($zh ? '新增分类' : 'New category') : h($zh ? '编辑分类' : 'Edit category') ?></h3>
<form method="post" action="<?= h(url('admin/faq.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="cat">
  <input type="hidden" name="cat_id" value="<?= $editCat === null ? 0 : (int)$editCat['id'] ?>">
  <table style="max-width:46em">
    <tr>
      <th style="width:8em"><label for="slug">slug</label></th>
      <td>
        <input type="text" id="slug" name="slug" size="16"
               value="<?= $editCat === null ? '' : h((string)$editCat['slug']) ?>" required>
        <span class="small"><?= h($zh ? '只用 a-z、0-9、-（用于筛选链接）' : 'a-z, 0-9 and - only (used in the filter link)') ?></span>
      </td>
    </tr>
    <tr>
      <th><label for="title_en"><?= h($zh ? '英文名' : 'English') ?></label></th>
      <td><input type="text" id="title_en" name="title_en" size="40" value="<?= $editCat === null ? '' : h((string)$editCat['title_en']) ?>"></td>
    </tr>
    <tr>
      <th><label for="title_zh"><?= h($zh ? '中文名' : 'Chinese') ?></label></th>
      <td><input type="text" id="title_zh" name="title_zh" size="40" value="<?= $editCat === null ? '' : h((string)$editCat['title_zh']) ?>"></td>
    </tr>
    <tr>
      <th><label for="sort"><?= h($zh ? '排序' : 'Sort') ?></label></th>
      <td>
        <input type="text" id="sort" name="sort" size="4" value="<?= $editCat === null ? 50 : (int)$editCat['sort_order'] ?>">
        <span class="small"><?= h($zh ? '小的排前面' : 'smaller comes first') ?></span>
      </td>
    </tr>
    <tr>
      <th></th>
      <td>
        <input type="submit" <?= $btn ?> value="<?= h($zh ? '保存分类' : 'Save category') ?>">
        <?php if ($editCat !== null): ?>
          <a href="<?= h(url('admin/faq.php')) ?>" class="small"><?= h($zh ? '取消编辑' : 'Cancel') ?></a>
        <?php endif; ?>
      </td>
    </tr>
  </table>
</form>

<hr>
<h2><?= h($zh ? '二、条目' : '2. Entries') ?></h2>
<table>
<thead>
  <tr>
    <th style="width:3em">#</th>
    <th style="width:7em"><?= h($zh ? '分类' : 'Category') ?></th>
    <th><?= h($zh ? '问题' : 'Question') ?></th>
    <th style="width:4em"><?= h($zh ? '公开' : 'Public') ?></th>
    <th style="width:13em"><?= h($zh ? '操作' : 'Actions') ?></th>
  </tr>
</thead>
<tbody>
<?php foreach ($items as $it): ?>
  <tr>
    <td class="small"><?= (int)$it['id'] ?></td>
    <td class="small"><?= h((string)($it['category_slug'] ?? '—')) ?></td>
    <td><?= h(faq_text((string)$it['question_en'], (string)$it['question_zh'])) ?></td>
    <td><?= (int)$it['is_public'] === 1 ? '✓' : '—' ?></td>
    <td class="small" style="white-space:nowrap">
      <a href="<?= h(url('admin/faq.php?edit=' . (int)$it['id'])) ?>"><?= h($zh ? '编辑' : 'Edit') ?></a>
      &nbsp;
      <a href="<?= h(url('admin/faq-i18n.php?item=' . (int)$it['id'])) ?>"><?= h($zh ? '翻译' : 'Translate') ?></a>
      &nbsp;
      <form method="post" action="<?= h(url('admin/faq.php')) ?>" style="display:inline"
            onsubmit="return confirm('<?= h($zh ? '删除这条？' : 'Delete this entry?') ?>')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="del_item">
        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
        <input type="submit" <?= $btn ?> value="<?= h($zh ? '删除' : 'Delete') ?>">
      </form>
    </td>
  </tr>
<?php endforeach; ?>
<?php if ($items === []): ?>
  <tr><td colspan="5"><?= h($zh ? '还没有条目。' : 'No entry yet.') ?></td></tr>
<?php endif; ?>
</tbody>
</table>

<h3><?= $editItem === null ? h($zh ? '新增条目' : 'New entry') : h($zh ? '编辑条目 #' . (int)$editItem['id'] : 'Edit entry #' . (int)$editItem['id']) ?></h3>
<form method="post" action="<?= h(url('admin/faq.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="item">
  <input type="hidden" name="item_id" value="<?= $editItem === null ? 0 : (int)$editItem['id'] ?>">

  <table style="max-width:52em">
    <tr>
      <th style="width:8em"><label for="category_id"><?= h($zh ? '分类' : 'Category') ?></label></th>
      <td>
        <select id="category_id" name="category_id">
          <option value="0"><?= h($zh ? '（未归类）' : '(none)') ?></option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"<?= $editItem !== null && (int)$editItem['category_id'] === (int)$c['id'] ? ' selected' : '' ?>>
              <?= h((string)$c['slug']) ?> — <?= h(faq_text((string)$c['title_en'], (string)$c['title_zh'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    <tr>
      <th><?= h($zh ? '排序 / 显示' : 'Sort / visible') ?></th>
      <td>
        <input type="text" name="sort" size="4" value="<?= $editItem === null ? 50 : (int)$editItem['sort_order'] ?>">
        &nbsp;
        <label class="inline">
          <input type="checkbox" name="is_public" value="1"<?= $editItem === null || (int)$editItem['is_public'] === 1 ? ' checked' : '' ?>>
          <?= h($zh ? '在前台显示' : 'Visible on the site') ?>
        </label>
      </td>
    </tr>
  </table>

  <table style="max-width:66em;margin-top:.8em">
    <thead>
      <tr>
        <th style="width:6em"></th>
        <th><?= h($zh ? '英文' : 'English') ?></th>
        <th><?= h($zh ? '中文' : 'Chinese') ?></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th><?= h($zh ? '问题' : 'Question') ?></th>
        <td><input type="text" name="question_en" style="width:100%" value="<?= $editItem === null ? '' : h((string)$editItem['question_en']) ?>"></td>
        <td><input type="text" name="question_zh" style="width:100%" value="<?= $editItem === null ? '' : h((string)$editItem['question_zh']) ?>"></td>
      </tr>
      <tr>
        <th><?= h($zh ? '答案' : 'Answer') ?></th>
        <td><textarea name="answer_en" rows="7" style="width:100%"><?= $editItem === null ? '' : h((string)$editItem['answer_en']) ?></textarea></td>
        <td><textarea name="answer_zh" rows="7" style="width:100%"><?= $editItem === null ? '' : h((string)$editItem['answer_zh']) ?></textarea></td>
      </tr>
    </tbody>
  </table>

  <p>
    <input type="submit" <?= $btn ?> value="<?= h($zh ? '保存条目' : 'Save entry') ?>">
    <?php if ($editItem !== null): ?>
      <a href="<?= h(url('admin/faq.php')) ?>" class="small"><?= h($zh ? '取消编辑' : 'Cancel') ?></a>
      &nbsp;·&nbsp;
      <a href="<?= h(url('admin/faq-i18n.php?item=' . (int)$editItem['id'])) ?>" class="small"><?= h($zh ? '编辑其它语言' : 'Edit other languages') ?></a>
    <?php endif; ?>
  </p>
</form>

<hr>
<p class="small">
  <a href="<?= h(url('faq.php')) ?>" target="_blank"><?= h($zh ? '前台查看' : 'Open the public page') ?></a>
  &nbsp;·&nbsp;
  <a href="<?= h(url('admin/faq-i18n.php')) ?>"><?= h($zh ? '常见问题翻译（其它语言）' : 'FAQ translations') ?></a>
</p>

<?php page_footer(); ?>