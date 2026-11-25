<?php
declare(strict_types=1);

/**
 * freedns.my - choose a language.
 *
 * The footer used to list every language, which grew into a long line. It now reads
 * "English - 简体中文 - 其它語言", and this page is what the last item opens: every language the site knows
 * about, the one in use marked, and an honest note about how far each translation has got.
 *
 * No JavaScript, no redirects, no guessing: each entry is a plain link to the same page in that language.
 */

require __DIR__ . '/inc/bootstrap.php';

$current = function_exists('lang') ? lang() : 'en';
$root    = defined('FD_ROOT') ? FD_ROOT : __DIR__;

/** The address of one language's version of a given page. */
function language_url_for(string $code, string $path, string $query): string
{
    $prefix = $code === 'en' ? '' : '/' . $code;

    return $prefix . $path . ($query === '' ? '' : '?' . $query);
}

/**
 * How far along a translation is, in round numbers.
 *
 * Traditional Chinese is deliberately built on top of the Simplified catalogue, so its entry count is already
 * that of English while the wording is still being converted; the note below says so instead of pretending the
 * translation is finished.
 */
function language_state(string $code): string
{
    $zh = function_exists('is_zh') && is_zh();

    if ($code === 'en' || $code === 'zh') {
        return $zh ? '完整' : 'complete';
    }

    $count  = count(function_exists('lang_catalogue_for') ? lang_catalogue_for($code) : []);
    $target = count(lang_catalogue_for('en'));
    $share  = $target > 0 ? (int)round($count * 100 / $target) : 0;

    if ($code === 'zh-hant') {
        return $zh ? '以簡體為底，繁體逐條轉換中' : 'built on the Simplified text, being converted';
    }

    return $zh ? '翻譯中（約 ' . $share . '%）' : 'being translated (about ' . $share . '%)';
}

// Where the visitor came from decides where a language should take him: the same page, in that language.
$path = '/' . ltrim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
foreach (array_keys(fd_languages()) as $code) {
    if ($code !== 'en' && str_starts_with($path, '/' . $code . '/')) {
        $path = substr($path, strlen($code) + 1);
        break;
    }
}
if (basename($path) === 'language.php') {
    $path = '/index.php';                   // this page itself: a language change lands on the home page
}
$query = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);

$zh = function_exists('is_zh') && is_zh();

page_header($zh ? '選擇語言' : 'Choose a language');
?>
<p><?= $zh
    ? '本站提供下列語言。未翻譯完成的部分會顯示為英文（繁體中文則以簡體為底），因此你可以放心切換。'
    : 'The site is available in the languages below. Anything not translated yet falls back to English '
      . '(Traditional Chinese falls back to the Simplified text instead), so switching is always safe.' ?></p>

<table>
  <tr>
    <th><?= $zh ? '語言' : 'Language' ?></th>
    <th><?= $zh ? '英文名稱' : 'In English' ?></th>
    <th><?= $zh ? '翻譯狀態' : 'Translation' ?></th>
    <th></th>
  </tr>
  <?php foreach (fd_languages() as $code => $names): ?>
  <tr>
    <td>
      <?php if ($code === $current): ?>
        <strong><?= h($names[0]) ?></strong>
      <?php else: ?>
        <a href="<?= h(language_url_for($code, $path, $query)) ?>" hreflang="<?= h($code) ?>"><?= h($names[0]) ?></a>
      <?php endif; ?>
    </td>
    <td class="small"><?= h($names[1]) ?></td>
    <td class="small"><?= h(language_state($code)) ?></td>
    <td class="small">
      <?php if ($code === $current): ?>
        <strong><?= $zh ? '目前使用中' : 'in use now' ?></strong>
      <?php else: ?>
        <a href="<?= h(language_url_for($code, $path, $query)) ?>"><?= $zh ? '切換' : 'switch' ?></a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= $zh
    ? '語言選擇不是帳號設定：它只影響你當下看到的頁面。喜歡簡體就把 /zh/ 加入書籤，喜歡繁體就記住 /zh-hant/。'
    : 'The choice is not an account setting: it only affects the pages you are reading now. If you prefer one '
      . 'language, bookmark its address - /zh/ for Simplified, /zh-hant/ for Traditional, /ja/, /ko/, /ms/.' ?></p>

<h2><?= $zh ? '想幫忙翻譯？' : 'Would you like to help translate?' ?></h2>
<p><?= $zh
    ? '翻譯完全靠志願者。若你願意把某個語言補完，請看加入我們那一頁，或直接用頁腳的電子郵件與我們聯絡 —— '
      . '大型語言模型翻出來的初稿我們也樂於接受，但需要有人願意校對。'
    : 'Translation is done by volunteers. If you would like to finish a language, see the "Work with us" page or '
      . 'write to the address in the footer. A machine-assisted first draft is welcome too, as long as somebody '
      . 'is willing to read it through.' ?>
  <a href="<?= h(url('jobs.php')) ?>"><?= $zh ? '加入我們' : 'Work with us' ?></a></p>
<?php
page_footer();
