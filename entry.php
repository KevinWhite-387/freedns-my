<?php
declare(strict_types=1);

/**
 * freedns.my — 入口选择页（经典版）
 *
 * 两个选项：
 *   ① 去 DNSHE（https://www.dnshe.com/）注册 kz.ci / xyz.ci
 *   ② 继续用 FreeDns（原来那个官网首页 index.php）注册 kz.ci / xyz.ci / asia.ci / vc.ci
 *
 * 样式：内联，**逐字照抄主站 inc/views.php 的 page_css()**
 *   → 等宽字体 ✓ 54em 宽度 ✓ 浏览器三色链接（#0000EE / #551A8B / #EE0000）✓
 *   → h2 全大写 ✓ .msg 左侧 4px 粗边 ✓ 深色跟随系统 ✓
 *   → 所以它和主站其他页面看起来是同一个站 ✓✓
 *
 * 原首页原封不动保存在 index.php ✓
 *
 * 要改的只有下面两个常量。
 */

const FD_DNSHE_URL  = 'https://www.dnshe.com/';   // ✓ 选项 1
const FD_LEGACY_URL = 'index.php';                 // ✓ 选项 2：原官网首页

/* 若以后要恢复「首页就是官网」，取消下面这行注释 ✓ 或把 index.php 保持为官网首页 */
// header('Location: ' . FD_LEGACY_URL, true, 302); exit;

function fd_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="assets/favicon-freedns.png">
<title>freedns.my &mdash; 选择注册方式</title>
<style>
/* ===== 主站经典配色（与 page_css() 的 :root 一致 ✓）===== */
:root {
  color-scheme: light;
  --bg: #ffffff;
  --fg: #111111;
  --link: #0000EE;
  --visited: #551A8B;
  --active: #EE0000;
  --muted: #444444;
  --hint: #555555;
  --border: #bbbbbb;
  --softline: #eeeeee;
  --panel: #fafafa;
  --code: #f2f2f2;
  --field: #ffffff;
  --fieldborder: #999999;
  --button: #f2f2f2;
  --buttonhover: #e4e4e4;
  --error: #990000;
  --ok: #006600;
  --infoline: #666666;
}
@media (prefers-color-scheme: dark) {
  :root {
    color-scheme: dark;
    --bg: #111111;
    --fg: #e8e8e8;
    --link: #7ab7ff;
    --visited: #c9a0ff;
    --active: #ff8a8a;
    --muted: #b0b0b0;
    --hint: #a0a0a0;
    --border: #444444;
    --softline: #2a2a2a;
    --panel: #1a1a1a;
    --code: #1f1f1f;
    --field: #1a1a1a;
    --fieldborder: #555555;
    --button: #2a2a2a;
    --buttonhover: #333333;
    --error: #ff8080;
    --ok: #6fdc6f;
    --infoline: #9a9a9a;
  }
}

/* ===== 以下规则逐字照抄主站 page_css() ✓ ===== */
body { font-family: ui-monospace, "DejaVu Sans Mono", Menlo, Consolas, "Courier New", monospace;
       font-size: 15px; line-height: 1.55; color: var(--fg); background: var(--bg); margin: 0; padding: 0; }
.wrap { max-width: 54em; margin: 0 auto; padding: 1.5em 1em 3em 1em; }
a:link { color: var(--link); text-decoration: underline; }
a:visited { color: var(--visited); text-decoration: underline; }
a:hover { color: var(--link); text-decoration: underline; }
a:active { color: var(--active); }
h1 { font-size: 1.35em; margin: 0 0 .2em 0; letter-spacing: .02em; }
h2 { font-size: 1em; margin: 1.6em 0 .5em 0; text-transform: uppercase; letter-spacing: .08em; }
h3 { font-size: 1em; margin: 1.2em 0 .3em 0; }
p.sub { margin: 0 0 .5em 0; color: var(--muted); }
hr { border: 0; border-top: 1px solid var(--border); margin: 1em 0; }
p { margin: .5em 0; }
ul, ol { margin: .5em 0 .5em 1.4em; padding: 0; }
li { margin: .15em 0; }
table { border-collapse: collapse; width: 100%; margin: .6em 0; }
th, td { text-align: left; vertical-align: top; padding: .25em .8em .25em 0; }
th { border-bottom: 1px solid var(--border); font-weight: normal; text-transform: uppercase; font-size: .85em; letter-spacing: .05em; }
td { border-bottom: 1px solid var(--softline); }
.hint { color: var(--hint); font-size: .85em; }
.msg { border: 1px solid var(--fieldborder); border-left-width: 4px; padding: .5em .8em; margin: 1em 0; }
.msg.error { border-color: var(--error); }
.msg.info { border-color: var(--infoline); }
.msg.ok { border-color: var(--ok); }
.err { color: var(--error); }
.ok { color: var(--ok); }
.small { font-size: .88em; color: var(--muted); }
.mono-box { border: 1px solid var(--fieldborder); padding: .6em .8em; margin: 1em 0; background: var(--panel); }
code { background: var(--code); padding: 0 .2em; }
.pill { border: 1px solid var(--fieldborder); padding: 0 .35em; font-size: .85em; }
form.inline { display: inline; }

/* ===== 本站只为「两个选项」加了两条，不改上面任何一条 ===== */
.choice { border-top: 1px solid var(--softline); padding: 1em 0 .2em 0; }
.choice:first-of-type { border-top: 0; padding-top: .3em; }
.choice .num { color: var(--muted); font-weight: bold; }
.choice .zones { font-family: inherit; margin: .35em 0 .6em 0; }
.choice .zones code { margin-right: .35em; }
.choice .go { font-weight: bold; }
</style>
</head>
<body>
<div class="wrap">

<p style="margin:0 0 .6em 0"><img src="assets/logo-freedns.png" alt="freedns.my" width="48" height="48" style="display:block;border:1px solid var(--softline);border-radius:6px;background:#fff"></p>
<h1>freedns.my</h1>
<p class="sub">免费二级域名注册与解析 &middot; Free second-level domain registration and DNS</p>
<hr>

<h2>选择注册方式 &middot; Choose how to register</h2>

<p class="sub">
本页提供两种办理方式。两种方式登记的域名不同、管理入口也不同，请按你的需要选择。<br>
Two ways to register. The domains and the management console are different for each &mdash; please pick the one you need.
</p>

<div class="choice">

<p><img src="assets/logo-dnshe.png" alt="DNSHE" width="32" height="32" style="width:32px;height:32px;vertical-align:-8px;margin-right:.4em;border-radius:4px"><span class="num">1.</span> <strong>进入 DNSHE 注册 kz.ci 与 xyz.ci 域名</strong><br>
<span class="small">Register kz.ci and xyz.ci domains at DNSHE</span></p>

<p class="zones">
<code>kz.ci</code><code>xyz.ci</code>
</p>

<p>
这是一套独立的注册渠道：在 DNSHE 平台完成注册与续期，域名也在 DNSHE 平台管理。<br>
<span class="small">An independent channel: register, renew and manage these domains on the DNSHE platform.</span>
</p>

<p class="go"><a href="<?php echo fd_h(FD_DNSHE_URL); ?>" rel="noopener">前往 DNSHE 注册 &rarr; https://www.dnshe.com/</a></p>
<p class="hint">Go to DNSHE to register &rarr;</p>

</div>

<div class="choice">

<p><img src="assets/logo-freedns.png" alt="FreeDns" width="32" height="32" style="width:32px;height:32px;vertical-align:-8px;margin-right:.4em;border:1px solid var(--softline);border-radius:4px;background:#fff"><span class="num">2.</span> <strong>继续使用 FreeDns 注册与管理局名</strong><br>
<span class="small">Keep using FreeDns to register and manage your domains</span></p>

<p class="zones">
<code>kz.ci</code><code>xyz.ci</code><code>asia.ci</code><code>vc.ci</code>
</p>

<p>
这是原来的 FreeDns 官网：在这里可以注册与管理上面四个后缀的域名。<br>
<span class="small">This is the original FreeDns site: register and manage domains under all four suffixes above.</span>
</p>

<p class="go"><a href="<?php echo fd_h(FD_LEGACY_URL); ?>">进入 FreeDns 官网 &rarr; 继续使用</a></p>
<p class="hint">Enter the FreeDns site &rarr;</p>

</div>

<hr>

<h2>说明 &middot; Notes</h2>

<div class="msg info">
通过 FreeDns 注册的域名，可以在 <strong>register.vc.ci</strong> 临时管理与审核（BETA）；<br>
通过 DNSHE 注册的域名，请到 DNSHE 平台管理。<br>
<span class="small">
Domains registered through FreeDns can be managed (BETA) at register.vc.ci.
Domains registered at DNSHE are managed on the DNSHE platform.
</span>
</div>

<p class="hint">
服务状态与公告：<a href="/status.php">status.php</a>
&middot; 滥用举报：<a href="/abuse.php">abuse.php</a>
&middot; 使用条款：<a href="/legal.php">legal.php</a>
</p>

<hr>
<p class="small">freedns.my &middot; 免费二级域名注册与解析</p>

</div>
</body>
</html>
