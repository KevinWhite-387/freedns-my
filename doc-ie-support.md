# FreeDns 对旧浏览器（IE 4–11 / EdgeHTML）的适配评估

> 结论先说：
> **IE 6–11 ＋ EdgeHTML：已经支持（classic 主题 ＋ legacy-ie.css）** ✓ 需要的是**验证与补漏**
> **IE 4 / 5 / 5.5：物理上做不到** ✗ 不是 CSS 问题，是**握手阶段就连不上** ✗✗

---

## 一、IE 4–5.5 为什么不可能 ✗

| 障碍 | 具体原因 |
| --- | --- |
| **① TLS 握手失败** ✗✗ | IE 4/5/5.5 最高只支持 **SSL 3.0 / TLS 1.0**（IE 5.5 是 SSL3）✗ 而现代 nginx 与 Let's Encrypt 早已**关闭 SSL3**、多数也关了 **TLS 1.0** ✗ → 浏览器连 HTML 都收不到 ✗ → 页面在任何 CSS 生效之前就已经失败了 ✗ |
| **② 没有 UTF-8** ✗ | IE 4 完全不认 UTF-8 ✗；IE 5/5.5 只有部分支持 ✗ → 中文页面需要改成 **GB2312/GBK** 并加 `<meta http-equiv="Content-Type" content="text/html; charset=gb2312">` ✗ 而整站是 UTF-8 + 六种语言 ✗ → 等于重做编码层 ✗ |
| **③ 没有 CSS2** ✗ | IE 4 只有 CSS1 的极小部分 ✗ `max-width`、`min-height`、`position`、`@media`、`border-collapse` 全没有 ✗ → 布局只能靠 `<table>` ✗ |
| **④ 没有 HTTPS 的现代证书** ✗ | IE 4 不认识 SNI ✗（一个 IP 一个站点 ✗）、不认识 SHA-2 ✗（只认 SHA-1 ✗，2017 年起证书机构已停发 ✗） |

⇒ 真要支持 IE 4，唯一办法是：
```
① 放弃 HTTPS（只用 http）✗   ② 全站改 GBK 编码 ✗   ③ 布局全部改成 <table> ✗   ④ 放弃六个语言 ✗
```
这四条都会**摧毁现在的站点** ✗✓ → **不建议，也不该做** ✗

---

## 二、IE 6–11 ＋ EdgeHTML：现状「已经支持」

### 已经写好的部分 ✓

| 文件 | 作用 |
| --- | --- |
| `inc/views.php:535` ✓ | `<meta http-equiv="X-UA-Compatible" content="IE=edge">` —— 阻止 IE 掉进"兼容性视图"（IE7 模式）✗ |
| `inc/views.php:537` ✓ | `<!--[if lte IE 8]><link …legacy-ie.css><![endif]-->` —— **只有 IE8 及以下**才加载补丁 ✓ |
| `assets/legacy-ie.css` ✓ 4,263 B | IE8 及以下的补丁：`text-align` 居中术（无 `max-width`）✓ `inline-block` 的 `zoom:1` ✓ 无 `@media` 时隐藏边栏 ✓ 选择器降级 ✓ |
| `inc/theme.php` ✓ | `theme_is_modern()` → **IE 自动用 classic 主题** ✓✓（classic = 纯 `<table>`/`<hr>` 布局 ✓ IE 最友好 ✓） |
| `inc/browser.php` ✓ | UA 识别 IE 6–11 ✓（含 IE11 的 Trident ✓、兼容模式的 IE7 ✓） |
| `admin/ui-browser.php` ✓ | 已经有一张**支持矩阵**告诉你每种引擎看到什么 ✓ |

### 现有矩阵（`admin/ui-browser.php` 里写的）

| 引擎 | 主题 | 广告边栏 |
| --- | --- | --- |
| Internet Explorer 6, 7, 8 | classic ✓ | 无（`@media` 不支持 ✓ 已隐藏 ✓）|
| Internet Explorer 9 | classic ✓ | 有 ✓ |
| Internet Explorer 10, 11 | classic ✓ | 无 ✓ |
| 360 / QQ / 搜狗 兼容模式 | classic ✓（UA 报成 IE 7）| 无 ✓ |
| EdgeHTML（Edge 12–18）| **modern** 或 classic ✓（按 `theme()` ✓）| 有 ✓ |

---

## 三、要"完全适配"，还差这三件事 ✓

### ① 真机验证（最重要 ✗ 因为只能靠人 ✗）

逐项点一遍，任何一项坏了回来告诉我：

```
IE 8   ① 首页能开 ✓ ② 导航链接都能点 ✓ ③ 申请表单能提交（含中文）✓
       ④ 后台能登录 ✓ ⑤ 记录编辑器能保存 ✓ ⑥ 六语言切换能用 ✓
IE 9   同上 ＋ 边栏广告正常显示 ✓
IE 11  同上 ＋ Ctrl+F5 无空白 ✓
EdgeHTML（Edge 18 或更早） 同上 ＋ 界面不应错位 ✓
```

⚠️ 特别看这几处（最容易在 IE 里崩 ✗）：
```
· 记录编辑器（多行输入 + 表格）✓
· 广告位（新加的固定宽度 160/300/728）✗  ← IE8 无 max-width，会顶破
· 六语言切换（/zh/ /ja/ 这种路径 ✓ 与 IE 无关 ✓）
· 博客页（/blog/ ✓ 我新写的，没在 IE 里测过 ✗✗）
```

### ② 把**新页面**也补进 `legacy-ie.css` ✓

这些是我这轮新加的，**还没做 IE 检查** ✗：

```
· /blog/ 全站（index/post/console/admin）  → 用了 <table> ✓ 但有几处 inline style ✗
· admin/dns-providers.php                  → <table> ＋ <input> ✓ 应该没问题
· admin/vcci.php                            → 同上 ✓
· admin/developers.php                      → 同上 ✓
· account/developer.php                     → 同上 ✓
· 广告位固定尺寸（width:160px;max-width:100%）→ IE8 不认 max-width ✗ 会横向溢出 ✗✗
```

**广告位最需要补** ✗ —— 加一条：
```css
/* assets/legacy-ie.css 末尾 */
.ad { overflow: hidden; }        /* IE 没有 max-width 时的兜底 ✓ */
```
或者给广告容器包一层固定宽度 ✓。

### ③ 确认 `X-UA-Compatible` 对 **EdgeHTML** 的影响 ✓

`IE=edge` 在 **EdgeHTML** 上无效 ✓（它本来就 edge ✓ 不报错 ✓✓）→ **不用改** ✓

⚠️ 但如果你是 **IE 11 + 企业兼容性视图** ✗ → `IE=edge` 会救回来 ✓✓

---

## 四、如果一定要"到 IE 4" ✗ 我建议换个说法

把目标改成 ✓：

```
✓ IE 6+ 可读可用（当前状态：基本可以，需按第三节验证）
✓ IE 8+ 布局不破（legacy-ie.css 覆盖 ✓）
✓ IE 11 + EdgeHTML 完全正常
✗ IE 4/5.5：不支持，并在文档里写清楚原因（TLS / 编码）
```

**在 `about.php` 或 `doc.php` 里写一句** ✓（我在"浏览器提示"那条黑框里加也行 ✓）：
> 本站支持 Internet Explorer 8 及以上（IE 6/7 可阅读，布局为简化版）；IE 4–5.5 因 TLS 与编码限制无法访问。

---

## 五、需要你决定的三件事 ✗

1. **IE 4/5.5 真的必须吗** ✗ → 如果是"客户要求"，我建议回复对方：**技术上做不到，原因是 TLS 与编码，不是我们没做** ✓
2. **要"能用"还是"像素级一样"** ✗ → IE6 只能用简化布局（无圆角、无固定宽度、无广告边栏）✓
3. **要不要我把 `legacy-ie.css` 补到覆盖新页面（博客 ＋ 广告位）** ✗ → 我 20 分钟能做完 ✓

---

## 附：怎么快速测（不用装 IE）

```
① 用 Edge 的「IE 模式」：设置 → 默认浏览器 → 允许 IE 模式 → 打开站点
② 或者在 admin/ui-browser.php 里看矩阵（它已有 UA 识别与预期行为）
③ 或者发我一份 UA 字符串，我用 curl -A 模拟，检查返回的 HTML 里有没有加载 legacy-ie.css ✓
   例：curl -s -A "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)" https://freedns.my/ | grep legacy-ie
```
