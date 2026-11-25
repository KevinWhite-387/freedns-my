# FreeDns 版本更新日志（V1 → 最新）

> 编号 = 我交付给你的**更新包顺序**（V1 是第一个包）。
> 每个版本四段：**新增**（加了什么）、**修改**（改了什么）、**删除**（去掉了什么）、**修复**（修了哪个 bug）。
> 没有的那一段就写「—」。文件路径都是相对站点根目录。
> 标 `⚠️` 的版本是**我引入过问题、随后在本版或下一版修掉**的。

---

## V1 —— vc.ci → freedns.my 品牌迁移

- **新增**
  - `inc/vcci.php`（441 行）：OAuth2 + PKCE（S256）客户端，`vcci_begin / vcci_callback / vcci_identity / vcci_bind / vcci_unbind / vcci_login / vcci_sign_in` 等 23 个函数。
  - `inc/vcci-migrate.php`（281 行）：`vcci_migrate_user()`，把用户在 vc.ci 的域名与记录迁到本注册局。
  - `account/vcci-callback.php`（9.4 KB）：`?do=login`、`?do=link`、`?do=unlink`、`?do=migrate` 四个动作。
  - `account/vcci.php`（307 B 别名）、`upgrade-vcci.sql`（`users.vcci_id / vcci_email / vcci_linked_at / vcci_migrated_at` ＋ 10 项设置）。
  - `account/login.php` 增加「用 vc.ci 登录」区块。
- **修改**：`inc/bootstrap.php` 增加 `require vcci.php`、`vcci-migrate.php`。
- **删除**：—
- **修复**：—（本版遗留：`inc/bootstrap` 未上传会导致 `Call to undefined function vcci_migrate_pdo()`）

## V2 —— vc.ci 后台配置 ＋ 迁移页

- **新增**
  - `admin/vcci.php`（210 行）：填 client id / secret / issuer / redirect，含「测试连接」。
  - `admin/developers.php`（162 行）：开发者申请审批。
  - 迁移入口页体验修正：首次登录自动迁移，失败写入 `vcci_migrate_failed` 审计。
- **修改**：`upgrade-vcci.sql` 里旧的重定向地址被 UPDATE 成新值。
- **删除**：—
- **修复**：—

## V3 —— 接入 Spaceship ＋ ANYNS（DNS 服务商）

- **新增**
  - `inc/spaceship.php`（566 行）：`X-Api-Key/X-Api-Secret` 双头；`GET /api/v1/dns/records/{zone}?take=500&skip=0`，PUT `{force:false,items:[…]}`，DELETE 裸数组；MX `exchange+preference`、NS `nameserver`、SRV `priority/weight/port/target`、CAA `flag/tag/value`。
  - `inc/anyns.php`（621 行）：`Authorization: Bearer adns_…`，`/v1/zones/{id}/records`，批量 `{"patches":[…]}` / `{"deletes":[{"id":…}]}`。
  - `admin/dns-providers.php`（255 行）：两个新服务商的凭据与测试。
  - `upgrade-dns-providers.sql`：`zones.spaceship_key/secret`、`zones.anyns_token`、设置 `spaceship_enabled=0`、`anyns_enabled=0`。
- **修改**：`inc/publish.php` 增加 dispatch 分支；`admin/dns.php` 服务商下拉从 3 项变 5 项（`'' / cloudflare / dnshe / spaceship / anyns`）。
- **删除**：—
- **修复**：—

## V4 —— 记录类型 4 种 → 21 种

- **新增**
  - `inc/record-types.php`（174 行）：`provider_record_types()`、`zone_record_types()`、`record_type_supported()`、`record_types_open()`、`record_type_allowed()`、`record_types_for_ui()`、`provider_record_types_summary()`。
  - `upgrade-record-types.sql`：`records.type` ENUM 扩到 21 种，新增 `record_types_open`、`users.developer`、`users.developer_since`、`developer_requests` 表。
- **修改**：`request.php` 的类型下拉改为 `record_types_for_ui($zone, $user)`；`inc/publish.php` 增加 `publish_drop_unsupported()`。
- **删除**：`request.php` 里写死的 `request_record_types()`。
- **修复**：—

## V5 —— 开发者申请（按需开通记录类型）

- **新增**
  - `inc/developer.php`（291 行）：`developer_apply / request_record / approve / reject / status_pending / requests_of_user / requests_queue / pending_count / types_for_user`。
  - `account/developer.php`（211 行）：用户提交申请与查看状态。
- **修改**：`admin/developers.php` 接上审批流程；`inc/views.php` 后台菜单增加「Developer requests」。
- **删除**：—
- **修复**：—

## V6 —— 去掉邀请码 ＋ 域名配额无限

- **新增**：`records_unlimited()`；`upgrade-quota-unlimited.sql`（`quota_enabled=0`、`zones.max_domains_per_email=0`）。
- **修改**：`max_records_per_domain(?string $zoneName = null)`：Cloudflare 按 `cf_record_cap`（默认 120，夹在 1–200），其余服务商返回 100000；`account/manage.php`、`manage.php`、`admin/edit.php` 都改为传后缀名。
- **删除**：`account/register.php` 里的**邀请码字段与校验**（`invite` 出现次数归零）。
- **修复**：—（本版遗留：注册成功提示重复，见 V7）

## V7 —— 中文界面字符串表 ＋ 注册提示修正

- **新增**：`inc/ui-strings.php`（419 行，**53 个键 × 6 种语言**）：`ui('key')` 一处改六语言，回落顺序「当前语言 → en → 键名」。
- **修改**：`inc/bootstrap.php` 加载 `ui-strings.php`；首页黑框提示改用 `ui('home.browser_notice')`（1px 黑边、`.5em .8em`、行高 1.55）。
- **删除**：`account/register.php` 里重复的 `flash(t('account.registered_flash'),'ok')`。
- **修复**：注册页出现两条「账号已创建」。

## V8 —— 「浏览器不支持」提示条

- **新增**：`inc/browser.php` 的 `fd_browser_notice()` 红框（`<div class="msg error fd-browser-warn">`），IE6–8 附「请切到极速模式」提示。
- **修改**：`inc/views.php` 在 `<body>` 后立刻输出该提示（`<!--[if lte IE 8]>` 条件加载 `assets/legacy-ie.css`）。
- **删除**：—
- **修复**：`account/verify.php` 首次点击即失败 → 改为 GET 显示「还差一步 ＋ 确认验证」按钮，POST 才验证。

## V9 —— 博客系统（`/blog/`）

- **新增**
  - `upgrade-blog.sql`：`blog_posts / blog_categories / blog_tags / blog_post_tags / blog_comments / blog_authors / blog_messages` 七张表 ＋ 6 项设置 ＋ 3 个默认分类。
  - `inc/blog.php`（511 行 / 28 个函数）、`blog/index.php`(231)、`blog/post.php`(115)、`blog/console.php`(213)、`blog/rss.php`(60)。
  - `blog/admin/index.php`(324)、`comments.php`(134)、`authors.php`(181)、`message.php`(171)。
- **修改**：`inc/bootstrap.php` 加载 `blog.php`；`inc/views.php` 前后台菜单各加博客入口。
- **删除**：—
- **修复**：—
- **规则**：共用 `users` 表与会话；**无注册页**；发帖需 `blog_authors.status='approved'`；徽章＝`verified=1`；评论默认 `pending`；官方账号 `freedns` 首次发帖自动建号并认证。

## V10 —— 博客导航修复（绝对路径）

- **新增**：—
- **修改**：`nav_admin()` 的渲染处改为「以 `/` 开头的键原样用，其余 `url('admin/' . $href)`」。
- **删除**：—
- **修复** ⚠️：在 `/blog/admin/` 里点后台菜单变成 `/blog/blog/admin/…`。

## V11 —— 博客六语言壳（15 个文件）

- **新增**：`zh / zh-hant / ja / ko / ms` 各 `blog/index.php`、`post.php`、`console.php`（每个 3 行：设 `$FD_FORCE_LANG` 后 `require dirname(__DIR__,2).'/blog/…'`）。
- **修改**：—
- **删除**：—
- **修复**：在 `/blog/` 点「简体中文」跳到 `/zh/blog/` → 目录不存在 → 回首页；现在停在博客。

## V12 —— 作者编辑页（可编辑认证内容）

- **新增**
  - `upgrade-blog-author-fields.sql`：`blog_authors.badge / bio / website`。
  - `blog/admin/author.php`（228 行）：账号信息只读 ＋ 发帖权限 ＋ 徽章勾选 ＋ 徽章文字 ＋ 简介 ＋ 链接 ＋ 发站内信 ＋ 最近文章。
- **修改**：`blog/admin/authors.php` 列表新增「编辑」链接（待批行 ＋ 现有作者行各一处）。
- **删除**：—
- **修复**：—

## V13 —— 文章页展示认证内容

- **新增**：`inc/blog.php` 的 `blog_post_by_slug()` 多取 `author_badge / author_bio / author_website`（改 LEFT JOIN `blog_authors`）；`blog/post.php` 作者行显示徽章文字 ＋ 正文后「关于作者」块（有内容才显示）。
- **修改**：—
- **删除**：—（原先把 `author_verified` 用子查询，改为 JOIN，少一次查询）
- **修复**：—

## V14 —— 广告支持文字 ＋ 固定尺寸

- **新增**
  - `ad_slots()` 每个位置加 `w/h`：`side_left` 160×600、`side_right` 160×600、`request_bottom` 728×90、`console_middle` 300×250、`inline` 600×160。
  - `ad_size()`、`ad_body()`（读 `ad_<slot>_body` / `_body_zh`，最多 300 字）。
- **修改**：`ad_render()` 套固定框并输出多行正文；`admin/ads.php` 每个位置新增 `body`、`body_zh` 两个多行文本框 ＋ 提示「图片留空＝文字广告」。
- **删除**：—
- **修复**：—

## V15 —— 广告显示修正

- **新增**：—
- **修改**：外框样式由 `width:…;min-height:…` 改为 `width:…;overflow:hidden`；图片广告加 `max-width:100%;max-height:200px`。
- **删除**：`min-height`。
- **修复** ⚠️：侧边广告位被强行撑到 600px，短文案下方一大片空白。

## V16 —— 广告功能整体关闭

- **新增**：`ads_enabled()` 总开关（默认 `false`）。
- **修改**：6 个输出点提前返回 —— `ad_render` / `ad_rails` / `ad_inline_html` / `ad_inline_inject`（返回原 HTML）/ `ad_request_bottom` / `ad_console_middle`；`inc/views.php` 导航去掉「广告投放」。
- **删除**：导航里的 `advertise.php` 链接（2 处）。
- **修复**：—

## V17 —— 撤销 9 个功能（统一说明页）

- **新增**
  - `inc/retired.php`：`retired_paths()`、`retired_url()`、`retired_page()`、`fd_nav_filter()`、`nav_admin_groups()` 之外的菜单过滤。
  - 说明页文案：**「因业务调整，本功能已被撤销。感谢您一如既往的支持。如有疑问请联系我们：xxx」**
- **修改**：16 个页面在 `require bootstrap` 后立即 `retired_page('功能名')`；两个前台菜单 `return fd_nav_filter([...])`；后台菜单循环 `continue`；`sitemap.php` 运行时过滤。
- **删除**：从所有菜单移除 —— 我的邮箱、邮件转发（旧）、通知偏好、配额商店、我的奖励、我的云服务器、广告投放、评价本站、新版界面 1.0。
- **修复**：—

## V18 —— 控制台首页清理

- **新增**：—
- **修改**：`account/index.php` 删 8 个区块（275 → 180 行）。
- **删除**：我的奖励、云服务器、广告与合作（含 `ad_console_middle()` 调用与「广告显示」链接）、界面（新旧切换）、通知偏好、我的邮箱＋旧版邮件转发、界面与浏览器、配额商店。
- **修复**：—
- **保留**：共享的域名、关闭账号、两步验证、API、开放平台、DDNS、聊天、**名称服务器**、域名市场、域名转移。

## V19 —— 删除脚本 v2（真删文件）

- **新增**：`delete-retired-v2.sh`（删 16 个页面 ＋ `retired.php`，跑完自动 `php -l` 校验关键文件）。
- **修改**：—
- **删除**：`account/{email,forward,forwarding,notify,shop,bonus,servers,theme,ads}.php`、`advertise.php`、`reviews.php`、`experimental.php`、`admin/{servers,forward-requests,reviews,shop}.php`、`retired.php`。
- **修复**：—

## V20 —— 删除脚本 v3（含 60 个语言壳）

- **新增**：`delete-retired-v3.sh`：除 v2 的清单外，删 5 语言 × 12 页的壳，并**清扫所有目标已不存在的壳**。
- **修改**：—
- **删除**：`zh / zh-hant / ja / ko / ms` 下的对应壳（60 个）。
- **修复**：`/zh/account/shop.php` 打开报 `Failed to open stream` / 500（壳还在、目标没了）。

## V21 —— `views.php` 多余 `}`（全站 500）

- **新增**：—
- **修改**：删掉 `inc/views.php` 第 552 行多出来的 `}`。
- **删除**：—
- **修复** ⚠️：`Parse error: Unmatched '}' in inc/views.php on line 662` → 全站 500。原因：删「Chromium 提醒」三行时多留一个 `}`。

## V22 —— `locale` → `language`


- **新增**：—
- **修改**：—
- **删除**：—
- **修复** ⚠️：注册/绑定报 `Unknown column 'locale'`。`users` 表实际列名是 `language`，改了两处 INSERT（`inc/vcci.php`、`blog/admin/index.php`）。

## V23 —— 清掉 `users.updated_at`


- **新增**：—
- **修改**：—
- **删除**：—
- **修复** ⚠️：`Unknown column 'updated_at'`。`users` 表没有该列，从 `inc/vcci.php`、`inc/developer.php`、`blog/admin/index.php` 全部移除（保留 `created_at`）。

## V24 —— 发帖 500（INSERT 列数）


- **新增**：—
- **修改**：—
- **删除**：—
- **修复** ⚠️：`Column count doesn't match value count`。`blog_authors` 8 列对 9 值、`blog_posts` 14 列对 15 值 —— 我把 `created_at, updated_at` 里的 `updated_at` 列名删了但值没删。两处补回列名。

## V25 —— 错误提示显示真实异常


- **新增**：—
- **删除**：—
- **修改**：`admin/decision.php` 的 catch 不再写「- database error」，改为 `$e->getMessage()`。
- **修复** ⚠️：发布失败只提示「database error」，看不到真实异常。

## V26 —— 提示改用真实服务商名


- **新增**：—
- **删除**：—
- **修改**：`admin/decision.php`、`admin/change.php`、`admin/edit.php` 的「Cloudflare refused」改为 `publish_provider_label()` 动态取（Spaceship / ANYNS / DNSHE）。
- **修复**：用 Spaceship 的后缀发布失败时提示「Cloudflare refused」，误导排查方向。

## V27 —— 发布行数契约（TypeError）


- **新增**：—
- **删除**：—
- **修改**：`inc/spaceship.php`、`inc/anyns.php` 的 sync 返回补 `'rows' => []`；`admin/decision.php`、`admin/edit.php` 调用处加 `(array)($rows ?? [])`。
- **修复** ⚠️：`publish_store_mapping(): Argument #2 ($rows) must be of type array, null given`。

## V28 —— 残留链接清理（8 个文件）


- **新增**：—
- **删除**：—
- **修改**：`home.php`、`index.php`、`about.php`、`partners.php`、`jobs.php`、`account/api.php`、`sitemap.php`、`inc/views.php` 删除指向已删页面的链接与菜单项（`sitemap.php` 删 10 条条目）。
- **修复** ⚠️：首页 `$fdScore` 的三元表达式被我切断 → 语法错误。改为 `$fdScore = '';`。

## V29 —— UI 2.0 恢复（主题系统）

- **新增**：`assets/theme-modern2.css`（26.2 KB）。
- **修改**：重写 `inc/theme.php`（239 行）：`themes()` 认识 `classic` / `modern2`（`modern` 标 `retired`）、`theme_is_modern2()`、`theme_choices()`、`theme_label()`、`theme_default()`、`theme_head()` 按界面内联 CSS。
- **删除**：`modern`（1.0）从菜单消失（旧会话仍可用）。
- **修复** ⚠️：`fd_ui_default()` 早就返回 `modern2`，但 `theme_valid('modern2')=false` → 全员退回 classic。

## V30 —— UI 2.0 页面接线


- **删除**：—
- **新增**：`inc/views-ui2.php`（184 行）承载 7 个 `ui2_*` 构建函数。
- **修改**：`inc/views.php` 4 处接线（topbar / banner / hero / feedback）＋ `inc/bootstrap.php` 加载。
- **修复**：—

## V31 —— UI 2.0 收尾


- **新增**：—
- **删除**：—
- **修改**：`account/theme.php` 切换页只列两项、`target` 改 `modern2`、确认短语 `SWITCH TO UI 2.0`；`account/index.php` 加回一行「界面与浏览器」；`inc/views.php` 首页接 `ui2_hero()`。
- **修复**：—

## V32 —— UI 2.0 控制台侧栏


- **新增**：—
- **删除**：—
- **修改**：`inc/views.php`：`ui2_console_shell()` 判断 → 开 `.ui2-shell` 并输出 `ui2_console_rail()`；页脚 `</body>` 前闭壳。
- **修复** ⚠️：闭壳那段第一次没插进去（`<div class="ui2-shell">` 开着没关）。

## V33 —— UI 2.0 换回原版

- **新增**：`icon.jpg`、`icon.ico`、`icon-180.png`、`icon-192.png`、`logo-transparent.png`。
- **修改**：`inc/views.php` 整份替换为备份版（931 行，内建 7 个 `ui2_*`）；`inc/bootstrap.php` 去掉 `views-ui2.php` 加载（避免函数重复定义）。
- **删除**：`inc/views-ui2.php` 的加载。
- **修复**：拼接版与 Dynadot 式原版差距太大（老板子 ＋ 2.0 零件）；logo 破图。

## V34 —— 切换页解拦截 ＋ 1.0 文案换 2.0


- **新增**：—
- **删除**：—
- **修改**：`account/theme.php` 删掉误加的 `retired_page('新版界面（1.0）')`；页面里 1.0 字样全部换成 2.0。
- **修复** ⚠️：**整个 UI 切换页被我上一版的「撤销 9 功能」误杀**，导致经典 ↔ 2.0 都没了。

## V35 —— 后台也用 UI 2.0


- **删除**：—
- **新增**：设置 `ui_admin_ui2`（默认 1）。
- **修改**：`inc/theme.php` 后台分支由「强制 classic」改为「默认 modern2」。
- **修复**：后台一直是旧版界面。

## V36 —— 后台菜单分组（47 项 → 9 组）


- **删除**：—
- **新增**：`nav_admin_groups()`：Overview / Domains / People / Mail / Servers / Money / Platform / Extras / Blog；**未分组的自动进 Other**（保证不丢入口）。
- **修改**：渲染改为按组分行。
- **修复**：—

## V37 —— 每组一行（flex 修正）


- **新增**：—
- **删除**：—
- **修改**：每组包 `<span style="display:block;flex-basis:100%;width:100%">`。
- **修复** ⚠️：2.0 的 `.ui2-nav` 是 flex，`<br>` 无效 → 47 项仍挤成一坨。

## V38 —— 组名可见（真相：`font-size:0`）


- **新增**：—
- **删除**：—
- **修改**：组名与链接行各加**内联 `font-size:13px`**。
- **修复** ⚠️：组名三次看不见。根因在 CSS：`body.theme-modern2 .ui2-nav{font-size:0;line-height:0}` —— 非 `<a>` 的文本字号为 0。

## V39 —— 后台菜单改下拉（Dynadot 式）


- **删除**：—
- **新增**：`<details class="adnav-grp" name="fd-adnav"><summary>组名</summary><div class="adnav-panel">…</div></details>`；`assets/theme-modern2.css` 追加 `.adnav-grp` / `.adnav-panel` 规则（胶囊按钮、▾ 箭头、浮层、20–26rem）。
- **修改**：—
- **修复**：—
- **效果**：`name` 属性让同名组**互斥**（开一组自动关其它）。

## V40 —— 后台简体中文 ＋ 语言切换

- **新增**：`inc/admin-lang.php`：`admin_languages()`（English / 简体中文）、`admin_lang()`（`?lang=zh|en` 存会话）、`admin_lang_apply()`、`admin_lang_switcher()`、`admin_nav_zh()`（47 条菜单中文）、`admin_group_zh()`（9 个分组中文）。
- **修改**：`inc/bootstrap.php` 仅当 `SCRIPT_NAME` 含 `/admin/` 时套用；后台导航改用 `admin_lang_switcher()`（2.0 与经典两份都改）；后台不显示明暗主题切换器。
- **删除**：后台里的前台语言切换器（它会生成 `/zh/admin/…`）。
- **修复** ⚠️：`/zh/admin/index.php` 变首页（没有 `zh/admin/*` 语言壳）；2.0 下导航被打印两次（一行英文一行中文）。

## V41 —— vc.ci 绑定入口找回


- **修改**：—
- **删除**：—
- **新增**：`account/index.php` 加回「vc.ci 账户」区块（已绑定/未绑定 ＋ 「管理绑定」链接到 `account/vcci.php`）。
- **修复** ⚠️：清控制台 8 区块时把 vc.ci 绑定入口一起删了；同版修掉 `$fdZh` 先用后定义。

## V42 —— `sitemap.php` 数组断裂


- **新增**：—
- **删除**：—
- **修改**：从备份恢复完整 `sitemap.php`，已撤销功能的条目改用**运行时过滤**。
- **修复** ⚠️：`Parse error: unexpected token "," in sitemap.php on line 106`（方括号 81/85）—— 按行删条目切断了嵌套数组。

## V43 —— Flash 版界面（真 SWF）

- **新增**
  - `flash/Main.hx`（Haxe 源码）→ 编译出 `assets/ui.swf`（5.9 KB，CWS，SWF v10）。工具链：Haxe 4.3.7（免 Java），命令 `haxe -swf ../assets/ui.swf -main Main -D swf-header=800:600:24:FFFFFF`。
  - `flash.php`（按 UA 给 `<object>` 或给说明页）、`flash-api.php`（行协议 `TITLE/NAV/LANG/CURLANG/LINE/BLANK/STATUS/FORM/ZONES/TYPES/CSRF/RESULT`）、`inc/ui-flash.php`（`flash_ui_enabled / flash_capable / flash_fallback_reason / flash_notice_html / flash_object_html`）。
- **修改**：`inc/bootstrap.php` 加载 `ui-flash.php`；`inc/views.php` 经典页顶部加提示框（`flash_ui` 默认关闭）。
- **删除**：—
- **修复** ⚠️：SWF 里用相对路径 `flash-api.php` → 实际请求 `/assets/flash-api.php` 404（改绝对 `/flash-api.php`）；第一包漏打 `flash-api.php`；Chrome 被误判为「可播」。

## V44 —— Flash 版加六语言 ＋ 标题字体


- **删除**：—
- **新增**：`?lang=` 支持（`$GLOBALS['FD_FORCE_LANG']`）＋ `LANG` / `CURLANG` 协议行；SWF 里第二行语言栏（English / 简体中文 / 繁體中文 / 日本語 / 한국어 / Bahasa）。
- **修改**：`flash.php` 的 `<h1>` 改 `Courier New` 16px（原来是宋体）；`inc/views.php` 加 `http-equiv="Content-Type"`（IE6 不认 `<meta charset>`）。
- **修复**：—

## V45 —— Flash 版后台统计 ＋ 申请表单


- **修改**：—
- **删除**：—
- **新增**
  - `screen=admin`：用现有 admin cookie 认身份，输出规模（域名/用户/记录）与各后缀已生效数（只读）。
  - `screen=request`：真表单（zone 点选、name 输入、type 点选、address、e-mail、提交、回显申请编号）；**复用 `request.php` 的校验与写库**（`normalize_label / valid_label / collect_records_from_post / generate_request_code / replace_records / notify_admin_new_request`），带 CSRF。
- **修复**：—

## V46 —— IE 兼容补丁（三轮）


- **删除**：—
- **新增**
  - `assets/legacy-ie.css` 追加 **41 条字面颜色**（主站 CSS 用 `var()`，IE9 及以下全部丢弃）。
  - 追加 `word-break:break-all`（CJK 不断行 → 表格列被顶飞）、`table{width:100%}`。
  - 追加 CJK 字体栈：`"Courier New", Courier, "SimSun", "宋体", "MS Gothic", "Malgun Gothic", monospace`；`line-height:1.65`。
- **修改**：`inc/views.php` 加 `http-equiv="Content-Type"`。
- **修复**：IE6 下「英文正常、中文排版不对」；中文表格列错位。

## V47 —— 新版 Chromium 提醒（后撤销）

- **新增**：`fd_chromium_notice()`（红框，`engine==='blink' && version>=100` 才显示）。
- **修改**：`inc/views.php` 接上。
- **删除**：**下一版按要求整体撤销** —— 函数、调用、文案全部移除。
- **修复**：—

## V48 —— 更新日志（本文）


- **修改**：—
- **删除**：—
- **修复**：—
- **新增**：`CHANGELOG.md`（含交付包清单、我引入过的 4 类错误、检查清单、未完成项）。

---

## 附录：阶段性汇总

| 阶段 | 版本 | 主题 |
|---|---|---|
| 迁移与解析 | V1–V5 | vc.ci 迁移、Spaceship/ANYNS、21 种记录类型、开发者申请 |
| 注册与配额 | V6–V8 | 去邀请码、配额无限、中文串、浏览器提示、验证页修复 |
| 博客 | V9–V13 | 博客全站、导航、语言壳、作者编辑、文章页认证展示 |
| 广告 | V14–V16 | 文字广告＋固定尺寸、显示修正、整体关闭 |
| 功能下线 | V17–V20 | 9 功能说明页、控制台清理、两轮真删（含 60 语言壳） |
| 修复潮 | V21–V28 | `}`、`locale`、`updated_at`、INSERT 列数、真实异常、服务商名、`rows` 契约、残留链接 |
| UI 2.0 | V29–V39 | 主题系统、页面接线、原版换回、后台 2.0、菜单分组/下拉/字号 |
| 后台中文 | V40–V42 | `?lang=` 切换、47 条中文菜单、vc.ci 入口、sitemap 修复 |
| Flash | V43–V45 | SWF 编译、六语言、后台统计、申请表单 |
| 旧浏览器 | V46–V47 | IE 颜色/断行/字体、Chromium 提醒（撤销） |

## 附录：我引入过的 4 类错误（每次改完必查）

1. **删代码块破坏配平** → 多余/缺失 `}`（V21、V34）
2. **按行删嵌套结构** → 数组或表达式被切断（V28、V42）
3. **整份替换文件未核对依赖** → 未定义函数（V29、V30）
4. **字符串替换命中过多/过少** → 整行被替换掉（V36、V41）

```
□ 花 { } 配平   □ 圆 ( ) 配平   □ 方 [ ] 配平
□ function 签名与 { 之间不得有其它代码
□ 每条语句以 ; 或 { } 结尾
□ 关键调用仍存在（grep 函数名/变量名）
□ 行数符合预期     □ 服务器 php -l 通过
```
