<?php
declare(strict_types=1);

/**
 * freedns.my - jobs.
 *
 * The page the "we are hiring" notice points at, and the page that stands in for an advertising slot that has
 * nobody in it. Its text is editable from the back office (Back office -> Advertising), so the registry can
 * change what is offered without touching code.
 */

require __DIR__ . '/inc/bootstrap.php';

$zh   = function_exists('lang') && is_zh();
$text = trim((string)setting_lang('jobs_text'));

if ($text === '') {
    $text = $zh
        ? "freedns.my 是一个由志愿者维护的免费二级域名注册局。我们没有固定编制，欢迎以任何形式参与：\n\n"
          . "· 后端 / PHP：记录发布、邮件服务、配额与审核流程\n"
          . "· 前端 / 无 JS 的界面设计：让页面更快、更好读\n"
          . "· 翻译：繁体中文、日本語、한국어、Bahasa Melayu\n"
          . "· 文档与社区：写使用说明、回答群里的问题\n"
          . "· 运营：内容、合作、以及这个广告位的对外联系\n\n"
          . "我们不支付报酬（这是一个免费项目），但会在更新日志与关于页面里记下你的贡献。\n"
          . "有兴趣请通过页脚的邮箱联系我们，或加入页脚里的群组。"
        : "freedns.my is a free second level domain registry run by volunteers. There is no payroll and no fixed\n"
          . "roster, and every kind of help is welcome:\n\n"
          . "· Backend / PHP: record publishing, the mail service, quotas and review flows\n"
          . "· Frontend: fast, readable pages without JavaScript\n"
          . "· Translation: Traditional Chinese, Japanese, Korean, Malay\n"
          . "· Documentation and community: guides, and answering questions in the groups\n"
          . "· Operations: content, partnerships, and the advertising slot on this page\n\n"
          . "There is no pay (the project is free), but contributions are recorded in the changelog and on the\n"
          . "about page. Write to the address in the footer, or join one of the groups listed there.";
}

page_header($zh ? '加入我们' : 'Work with us');
?>
<p><?= $zh
    ? '这个项目靠志愿者维持，随时欢迎新面孔。'
    : 'The project runs on volunteers, and new faces are always welcome.' ?></p>

<div class="msg info" style="white-space:pre-wrap"><?= h($text) ?></div>

<h2><?= $zh ? '这个广告位' : 'This advertising slot' ?></h2>
<p><?= $zh
    ? '本站的广告位对外招租，也接受自荐与友链。要求很简单：内容合法、与域名/主机/技术相关、不放追踪脚本与弹窗。'
    : 'The advertising slots on this site are available, and so is a plain link exchange. The rules are short: '
      . 'nothing illegal, related to domains, hosting or technology, and no tracking scripts or pop-ups.' ?></p>
<p>
      ? '查看广告位与价格，直接下单' : 'See the slots and prices, and order one' ?></strong></a>
      ? '了解本站的实验性功能与声明' : 'What the site calls experimental' ?></a>
</p>
<?php
page_footer();
