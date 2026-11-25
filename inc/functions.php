<?php
declare(strict_types=1);

/**
 * freedns.my - shared helper functions.
 */

/* ------------------------------------------------------------------ *
 * Compatibility helpers (the application runs on PHP 7.4 and newer).  *
 * ------------------------------------------------------------------ */

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/* ------------------------------------------------------------------ *
 * Configuration and settings                                          *
 * ------------------------------------------------------------------ */

function config(string $key, $default = null)
{
    $cfg = $GLOBALS['FD_CONFIG'] ?? [];

    return $cfg[$key] ?? $default;
}

function default_changelog_text(): string
{
    return "What changed in this release\n"
        . "=============================\n\n"
        . "Fixed\n"
        . "-----\n"
        . "1. Chinese pages (everything under /zh/) returned 404 - every page exists now, including the\n"
        . "   console, the market, the shop, the chat and WHOIS.\n"
        . "2. Signing in or linking with GitHub could reload the page for ever without finishing - fixed;\n"
        . "   afterwards you land back on the page you started from.\n"
        . "3. Some links went to a non-existing /account/account/... address - fixed everywhere.\n"
        . "4. Buttons in the back office that test an integration did nothing - they save your values first\n"
        . "   now and show the answer of the other side.\n"
        . "5. Records were refused by DNSHE because of the record-name format - fixed.\n"
        . "6. A database column this version needs could make a page fail or stay blank; the page now says\n"
        . "   exactly which statement to import.\n"
        . "7. Records could still be changed while a name was being transferred - locked now.\n"
        . "8. Actions outside a reviewer's suffixes are refused and explain why.\n"
        . "9. Integration errors are told apart now: missing permission, rate limit, expired token or\n"
        . "   unknown repository each get their own message.\n"
        . "10. The documentation still claimed own name servers are impossible - corrected.\n"
        . "11. Notification mails are sent in the language of the recipient and carry the reason of a\n"
        . "    rejection.\n"
        . "12. DNSHE names can no longer be deleted (DNSHE forbids deleting a registered subdomain); use\n"
        . "    Suspend instead.\n\n"
        . "New\n"
        . "---\n"
        . "* Domain market: offer a registrable domain you bought elsewhere (registration date, expiry,\n"
        . "  registrar, transfer price, asking price, currency, negotiable), reviewed by hand, pinnable;\n"
        . "  buyer and seller receive each other's address by mail. The registry is not part of the deal.\n"
        . "* Quota shop: earn extra registration quota for starring our repository, for promoting us\n"
        . "  (send a link, reviewed by hand) or with your personal invite code.\n"
        . "* Personal invite codes, with the number of people you brought visible in the console; a code\n"
        . "  can also be typed in when you register.\n"
        . "* Live chat: talk to the registry in the page (it refreshes itself, no JavaScript needed); a\n"
        . "  visitor may leave a message and is answered by mail.\n"
        . "* Transfer tickets for names under a DNSHE suffix: apply, the registry prepares the code, the\n"
        . "  recipient accepts it within 72 hours.\n"
        . "* Own name servers for suffixes that allow it (zones in the Public Suffix List, for example\n"
        . "  us.ci and de5.net): set them in the console, the change applies at once.\n"
        . "* WHOIS lookup, dark mode, flood protection with a back-office release panel, reviewer accounts\n"
        . "  limited to their suffixes, user management and a registration allowance per e-mail address.\n\n"
        . "Still worth knowing\n"
        . "-------------------\n"
        . "* Records: A, AAAA, CNAME, TXT, MX, SRV, CAA and PTR, wildcards (*.name), and every record can\n"
        . "  carry its own TTL (60 to 86400 seconds) and, for A/AAAA/CNAME, its own proxy setting.\n"
        . "* No own name servers for the suffixes this registry hands out itself (they are not in the\n"
        . "  Public Suffix List).\n"
        . "* No SLA.";
}

function default_changelog_text_zh(): string
{
    return "本次更新内容\n"
        . "============\n\n"
        . "修复\n"
        . "----\n"
        . "1. 中文页面（/zh/ 下的所有页面）此前会 404，现已全部补齐（含控制台、交易市场、配额商店、\n"
        . "   在线咨询、WHOIS）；\n"
        . "2. 用 GitHub 登录或绑定账号时可能不断刷新、始终无法完成，已修复，并且完成后会回到你原来的页面；\n"
        . "3. 部分链接会跳到 /account/account/... 这类不存在的地址，已全站修复；\n"
        . "4. 后台测试第三方接口的按钮点了没有反应，现在会先保存你填写的值再测试，并显示对方的原始回复；\n"
        . "5. 向 DNSHE 提交记录时因记录名格式被拒绝，已修复；\n"
        . "6. 数据库缺少本版本所需字段时页面会报错或空白，现在会明确告诉你需要执行哪条 SQL；\n"
        . "7. 域名处于转让流程中仍能修改记录，现已锁定；\n"
        . "8. 审核员处理不属于自己后缀的域名会被拒绝，并说明被跳过的原因；\n"
        . "9. 第三方接口报错现在会区分「权限不足 / 频率限制 / 令牌失效 / 地址不存在」，分别给出提示；\n"
        . "10. 文档中「目前不支持自带域名服务器」的旧说法已更正；\n"
        . "11. 通知邮件按收件人语言发送，拒绝类邮件会带上原因；\n"
        . "12. DNSHE 域名不再允许删除（DNSHE 规定不允许删除已注册子域名），请改用「暂停」。\n\n"
        . "新增\n"
        . "----\n"
        . "* 域名交易市场：可挂牌出售你在别处购买的一手域名（注册日期、到期时间、注册平台、转移价格、\n"
        . "  出售价格、币种、是否可议价），人工审核后可展示、可置顶；买家申请后双方各收一封邮件互相\n"
        . "  拿到联系方式。注册局不参与交易。\n"
        . "* 配额商店：给项目点 Star、在平台上推广（提交链接，人工审核）、或用专属邀请码带来新用户，\n"
        . "  都能换取更多注册配额。\n"
        . "* 专属邀请码：控制台可查看你带来的人数与其中已生效的域名数；注册时也可以直接填写邀请码。\n"
        . "* 在线咨询：在页面里直接与注册局对话（页面自动刷新，无需 JavaScript）；未登录也能留言，\n"
        . "  我们会邮件回复。\n"
        . "* 转让工单：DNSHE 后缀下的域名可走正式转让流程 —— 提交申请、注册局准备转让码、接收方在\n"
        . "  72 小时内接受即完成过户。\n"
        . "* 自带域名服务器：已进入公共后缀列表（PSL）的后缀（例如 us.ci、de5.net）可在控制台把域名\n"
        . "  指向你自己的 nameserver，即时生效。\n"
        . "* 其它：WHOIS 查询页、深色模式、防刷保护与后台解封面板、按后缀授权的审核员账号、后台用户\n"
        . "  管理、以及按邮箱计算的注册配额。\n\n"
        . "仍然值得知道\n"
        . "------------\n"
        . "* 记录类型：现在支持 A、AAAA、CNAME、TXT、MX、SRV、CAA、PTR，以及泛解析（*.名字）；并且每条记录\n"
        . "  都可以有自己的 TTL（60–86400 秒），A/AAAA/CNAME 还可单独设置是否走代理；\n"
        . "* 本注册局自己发放的后缀不支持自带 NS（这些后缀尚未进入公共后缀列表）；\n"
        . "* 没有服务承诺（SLA）。";
}

function default_terms_text(): string
{
    return "Terms of service\n"
        . "================\n\n"
        . "1. This is a free service run by volunteers. There is no contract, no fee and no service level;\n"
        . "   the registry may refuse or withdraw a name when it is abused.\n"
        . "2. Names are handed out for your own use. You are responsible for what is published under them:\n"
        . "   no phishing, spam, malware, illegal content, trademark abuse or impersonation.\n"
        . "3. A name is not property and cannot be sold as such here. It can be handed over through the\n"
        . "   transfer procedure, and only for the suffixes that allow it.\n"
        . "4. Do not use a name for anything that needs a guarantee: this registry has no availability\n"
        . "   promise, no backup promise and no support promise.\n"
        . "5. Abusive names are suspended without notice; repeated abuse ends the access to the console.\n"
        . "6. Reports go to the abuse page. Keep them factual - they may be shown to our upstream provider.\n"
        . "7. The registry may change these terms; the current version is always on this page.";
}

function default_terms_text_zh(): string
{
    return "服务条款\n"
        . "========\n\n"
        . "1. 本服务由志愿者维护、完全免费：没有合同、没有费用、没有服务等级承诺；遇到滥用时，注册局可以拒绝\n"
        . "   或撤回域名。\n"
        . "2. 域名仅供你自己使用。域名下发布的内容由你负责：不得用于钓鱼、垃圾信息、恶意软件、违法内容、\n"
        . "   侵犯商标或冒充他人。\n"
        . "3. 域名不是财产，不能在这里当作财产出售。允许转让的后缀可通过转让流程换手。\n"
        . "4. 不要把它用于任何需要保证的场合：本注册局不承诺可用性、不承诺备份、也不承诺技术支持。\n"
        . "5. 滥用域名会被直接暂停，反复滥用将失去控制台访问权限。\n"
        . "6. 举报请走举报页，内容请客观 —— 它可能会被提供给我们的上游服务商。\n"
        . "7. 注册局可更新本条款，最新版本始终在本页面。";
}

function default_privacy_text(): string
{
    return "Privacy\n"
        . "=======\n\n"
        . "What is stored\n"
        . "--------------\n"
        . "* the name you registered, the address given with it, and the records you publish;\n"
        . "* a console account: user name, e-mail address, a password hash (never the password itself);\n"
        . "* what is needed to run the service: audit log entries, rate limit counters, chat and ticket\n"
        . "  messages, market or shop entries you create yourself;\n"
        . "* the IP address that used the API or the update address, and its last use.\n\n"
        . "What is shown in public\n"
        . "-----------------------\n"
        . "* WHOIS answers with the name, its status, the registrar of the suffix, the record values and the\n"
        . "  dates. Names and e-mail addresses of holders are NOT published.\n"
        . "* The public list of names masks the addresses; the market shows only what a seller chooses to\n"
        . "  publish and never the contact address of a buyer.\n\n"
        . "What is not done\n"
        . "----------------\n"
        . "* no advertising, no tracking scripts, no analytics, no JavaScript at all;\n"
        . "* no selling or sharing of data with third parties; the only outside services are the DNS\n"
        . "  providers that publish records (Cloudflare, DNSHE) and the mail server that sends notices.\n\n"
        . "Your control\n"
        . "------------\n"
        . "* you can export your records, tickets, chat and market entries at any time;\n"
        . "* deleting a name removes its records from the provider; deleting the account removes the\n"
        . "  account data and leaves the names suspended;\n"
        . "* questions or requests about your data: use the chat page or the abuse address in the footer.";
}

function default_privacy_text_zh(): string
{
    return "隐私说明\n"
        . "========\n\n"
        . "我们保存什么\n"
        . "------------\n"
        . "* 你注册的域名、随域名填写的邮箱，以及你发布的记录；\n"
        . "* 控制台账号：用户名、邮箱、密码哈希（绝不保存明文密码）；\n"
        . "* 运行所需的数据：审计日志、限频计数、聊天与工单消息、你自己创建的市场或商店条目；\n"
        . "* 调用 API 或动态更新地址的 IP 及其最后使用时间。\n\n"
        . "哪些是公开的\n"
        . "------------\n"
        . "* WHOIS 会显示域名、状态、后缀注册商、记录值与日期；**不会**显示持有人姓名与邮箱；\n"
        . "* 公开域名列表会对地址打码；交易市场只显示卖家主动填写的内容，从不显示买家的联系邮箱。\n\n"
        . "我们不做什么\n"
        . "------------\n"
        . "* 没有广告、没有追踪脚本、没有统计代码，全站不使用 JavaScript；\n"
        . "* 不向第三方出售或共享数据；唯一的外部服务是发布记录的 DNS 服务商（Cloudflare、DNSHE）与发信\n"
        . "  用的邮件服务器。\n\n"
        . "你的控制权\n"
        . "----------\n"
        . "* 你可以随时导出记录、工单、聊天与市场条目；\n"
        . "* 删除域名会同时从上游删除其记录；删除账号会清除账号数据，名下域名转为暂停；\n"
        . "* 关于数据的疑问或请求：请通过在线咨询或页脚邮箱联系我们。";
}

function default_policy_text(): string{
    return <<<'TXT'
1. Names are handed out on a first come, first served basis and stay free of charge,
   renewals included. No advertising is inserted into your pages.

2. You must control, or be authorised to use, the name you request. Names that infringe
   somebody else's trade mark, or that impersonate another organisation, are refused.

3. The registry answers for the name itself: an approved name is published with the A, AAAA,
   CNAME and TXT records kept for it. You must control every address or host name you enter;
   do not point a name at a machine you are not responsible for.

4. Addresses inside private, loopback, link-local, carrier-grade NAT or documentation ranges
   are refused. For suffixes whose upstream platform allows it (the zones that are in the Public
   Suffix List, for example us.ci and de5.net), you may point the name at your own name servers
   later on - see the console page "My own name servers".

5. After a name is active you may propose further records for it (sub-names such as
   www.myname, CNAME and TXT records). Every change is reviewed by an operator before it is
   published.

6. Do not use a name for spam, phishing, malware, illegal content, or anything else that
   damages the reputation of the zones. Such names are suspended without notice.

7. One registrant may hold a limited number of names, and one name a limited number of
   records. Requests filed only to resell or to park names are refused.

8. Tell the registry when the address behind your name changes (quote the request code).
   A name that stops resolving, or whose registrant does not answer registry mail for a long
   period, may be withdrawn after we try to contact the registrant.

9. Registration data (name, e-mail address, organization, country, records) is kept for
   the operation of the registry only. It is never sold and never shared for marketing.
TXT;
}

function default_documentation_text(): string
{
    return <<<'TXT'
Step 1 - decide which addresses will answer for the name
    Collect the IPv4 and/or IPv6 addresses that should serve the name, for example
    203.0.113.10 or 2001:db8::1. They must be publicly routable: private, loopback,
    link-local, carrier-grade NAT and documentation ranges are refused. Up to four
    addresses may be given, and each one becomes an A or AAAA record.

Step 2 - choose a zone and request the name
    Pick one of the zones offered on the request form (for example freedns.my), enter only
    the label (for example "myname"), your details and the addresses. The complete name will
    be myname.freedns.my. You can hold names in more than one zone; each name is requested
    separately.

Step 3 - keep the request code
    After submitting you receive a code such as FD-25-1A2B3C4D. It is shown once on screen
    and is the only way to follow the request and to manage the records later. Write it down.

Step 4 - wait for the review
    A registry operator checks the request and the addresses. You can follow the state at any
    time with the request code and your e-mail address on the status page. When the request is
    approved, the zone is updated and the name resolves to your addresses.

Step 5 - manage the records yourself
    Once the name is active, open "Manage records" and identify yourself with the request code
    and your e-mail address. There you can change addresses, add names such as
    www.myname.freedns.my, remove records, and add CNAME and TXT records. A sub-name is a single
    label in front of your name (www, mail, shop, ...).

    Every change is queued for review, so nothing is published before an operator approves it.
    You can withdraw a queued change yourself while it is still waiting.

Step 6 - name servers
    By default the registry publishes the records itself and answers for the name. For suffixes whose
    upstream platform allows it (the zones in the Public Suffix List, for example us.ci and de5.net) the
    holder can switch to his own name servers in the console, under "My own name servers": that takes
    effect immediately, without a review, it removes the records this registry published and hands the
    name over to that DNS service (glue is added for name servers inside the name). Because of that,
    record types such as MX or SRV cannot be set at the moment; write
    to the registry address in the footer if you need one.

Step 7 - later changes
    To change the contact person or the organization, or to give up a name, write to the registry
    address given in the page footer and quote the request code and the domain name.

Step 8 - reserved names
    A few names are kept by the registry. They are refused on the ordinary request form; to ask
    for one, tick "This is a reserved name" in section 2 and describe in section 5 what you will
    use it for - that description is required and the name may then also be a single character.
    Ticking the option for a name that is not reserved is refused, and the request is reviewed by
    hand like any other.

Questions
    The registry is run by volunteers on a best-effort basis. There is no telephone support
    and no service level agreement.
TXT;
}

function default_policy_text_zh(): string
{
    return <<<'TXT'
1. 域名按先到先得的原则分发，注册和续期都完全免费，不会在你的页面上插入广告。

2. 你必须拥有或获得授权使用所申请的域名。侵犯他人商标、冒充其他机构的域名会被拒绝。

3. 本注册局自己负责域名的解析：审核通过的域名会发布为其保存的 A、AAAA、CNAME 和 TXT 记录。
   你必须对自己填写的每个地址或主机名负责，不要把域名指向你无权管理的机器。

4. 私有地址、回环地址、链路本地地址、CGNAT 地址和文档示例网段都会被拒绝。对于上游平台允许的后缀
   （即已列入 Public Suffix List 的，例如 us.ci、de5.net），你之后可以在控制台「我的域名服务器」里
   把域名指向自己的 nameserver。

5. 域名生效后，你可以继续为它提交更多记录（例如 www.myname 这类子域名、CNAME 和 TXT 记录）。
   每次改动都会先由管理员审核，通过后才会发布。

6. 不得将域名用于垃圾邮件、钓鱼、恶意软件、违法内容或其他损害本后缀声誉的行为。此类域名会被
   直接停用，不另行通知。

7. 每个申请人可持有的域名数量有限，每个域名可保存的记录数量也有限。仅为转售或囤积而提交的
   申请会被拒绝。

8. 域名对应的地址发生变化时请告知注册局（注明申请编号）。长期无法解析、或长期不回复注册局邮件的
   域名，在我们尝试联系申请人之后可能被收回。

9. 注册信息（域名、邮箱、组织、国家/地区、记录）仅用于注册局的日常运营，绝不会出售，
   也不会用于营销。
TXT;
}

function default_documentation_text_zh(): string
{
    return <<<'TXT'
第一步 - 确定域名要指向什么
    先准备好域名要使用的地址或主机名，例如 IPv4 地址、IPv6 地址，或一个 CNAME 目标主机名。
    地址必须是公网可路由的：私有地址、回环、链路本地、CGNAT 和文档示例网段都会被拒绝。
    同一个域名最多可以填 4 条 A/AAAA 记录。

第二步 - 选择后缀并提交申请
    在申请表单里选择后缀（例如 freedns.my），只填标签部分（例如 myname），再填申请人信息和记录。
    完整域名将是 myname.freedns.my。注册时名称栏请填 @，它表示申请的域名本身；注册时还不能填
    子域名和 TXT 记录，这些要等域名生效之后再添加。

第三步 - 记下申请编号
    提交后会得到一个形如 FD-25-1A2B3C4D 的申请编号，它只显示一次。它是查询进度和以后管理记录的
    唯一凭据，请务必保存。

第四步 - 等待人工审核
    管理员会人工检查申请和记录。你可以随时用申请编号和邮箱在查询页面查看状态。通过后，记录会被
    写入对应 zone，域名开始解析。

第五步 - 自己管理记录
    域名生效后，打开「管理记录」页面，用申请编号和邮箱进入。在那里可以修改地址、添加
    www.myname.freedns.my 这类子域名、删除记录，以及添加 CNAME 和 TXT 记录。

    对已生效域名的改动会进入审核队列，管理员批准后才会发布；排队期间你可以自己撤回。
    申请还在待审核时，改动会立即保存，方便你自己修正填错的地址。

第六步 - nameserver
    默认由注册局自己发布记录并负责解析。对于上游平台允许的后缀（即已列入 Public Suffix List 的，
    例如 us.ci、de5.net），持有人可以在控制台「我的域名服务器」里改用**自己的 nameserver**：
    **即时生效、无需审核**，注册局此前发布的记录会被下掉，解析完全交给该 DNS 服务
    （若 nameserver 位于域名之内，还会同时发布 Glue 记录）。也正因为如此，MX、SRV 等记录类型目前
    无法自行设置：确有需要时，请按页脚的邮箱联系我们并注明申请编号。

第七步 - 其它变更
    需要更换联系人、组织，或放弃某个域名时，请按页脚的邮箱联系我们，并注明申请编号和域名。

第八步 - 保留域名
    少数域名由注册局保留，在普通申请通道会被直接拒绝。若要申请，请在第 2 节勾选
    「这是保留域名申请」，并在第 5 节写清用途——用途说明为必填，此时也允许单字符域名。
    对不在保留名单中的域名勾选该选项同样会被拒绝；保留域名的申请和普通申请一样需要人工审核。

其它
    本服务由志愿者尽力维护，不提供电话支持，也不提供任何服务等级保证。
TXT;
}

function default_settings(): array
{
    return [
        'site_title'                    => 'freedns.my',
        'site_subtitle'                 => 'Free domain names',
        'contact_email'                 => 'hostmaster@freedns.my',
        'announcement'                  => "This registry hands out free domain names under the zones listed on the\n"
            . "request form (first served, no charge, no advertising).\n\n"
            . "Give the IPv4 and/or IPv6 address or addresses that should serve your name: the registry publishes\n"
            . "the A and AAAA records. Once a name is active you can add sub-names such as www.myname and further\n"
            . "records (CNAME, TXT) yourself; every change is reviewed by an operator before it goes live.\n\n"
            . "Name servers: the registry answers for your name. For suffixes whose upstream allows it\n"
            . "(zones in the Public Suffix List, for example us.ci and de5.net) you can point the name at\n"
            . "your own name servers in the console later - that is applied immediately, without a review.\n\n"
            . "Requests are reviewed by hand, normally within a few days. Please read the registration policy before you apply.",
        'policy'                        => default_policy_text(),
        'documentation'                 => default_documentation_text(),
        // Chinese versions of the editable texts; empty values fall back to the English ones.
        'site_title_zh'                 => 'freedns.my 免费域名',
        'site_subtitle_zh'              => '免费二级域名',
        'announcement_zh'               => "本注册局在申请表单列出的后缀下免费分发域名（先到先得，不收费，无广告）。\n\n"
            . "请填写域名要指向的 IPv4 和/或 IPv6 地址，或一个 CNAME 目标主机名：注册局会发布对应的 A、\n"
            . "AAAA 或 CNAME 记录。域名生效后，你还可以自己添加 www.myname 这类子域名，以及 CNAME 和 TXT\n"
            . "记录；每次改动都会先由管理员审核再发布。\n\n"
            . "名称服务器：默认由注册局负责解析。对于上游允许的后缀（已列入 Public Suffix List 的，例如\n"
            . "us.ci、de5.net），你之后可以在控制台把域名指向自己的 nameserver，**即时生效、无需审核**。\n\n"
            . "申请由人工审核，通常几天内处理。提交前请先阅读注册政策。",
        'policy_zh'                     => default_policy_text_zh(),
        'documentation_zh'              => default_documentation_text_zh(),
        'requests_open'                 => '1',
        'self_service'                  => '1',
        // User console: registration switch and the per-address limit per day.
        'user_registration_open'        => '1',
        'max_user_registrations_per_ip_per_day' => '3',
        // Hidden back-office entry (Settings -> Admin entry).
        'admin_gate_enabled'            => '0',
        'admin_gate_slug'               => '',
        'admin_gate_pass'               => '',
        'max_domains_per_email'         => '5',
        'max_requests_per_ip_per_day'   => '5',
        'max_records_per_domain'        => '20',
        'default_ttl'                   => '3600',
        'notify_admin_email'            => '',
        // The chat group offered on the home page; empty hides the whole section.
        // Registration quota per e-mail address, counted across every suffix. Off by default.
        'quota_enabled'                 => '0',
        'quota_per_email'               => '3',
        'quota_grandfather_at'          => '',
        // Star our GitHub repository, get a bigger allowance. Off until a repository is configured.
        'star_bonus_enabled'            => '0',
        'star_repo'                     => '',
        'star_bonus_amount'             => '1',
        'star_token'                    => '',
        'star_cache_minutes'            => '10',        'notify_applicant'              => '1',
        'mail_from'                     => '',
        // Mail transport: 'auto' uses SMTP when a host is set and PHP mail() otherwise.
        'mail_transport'                => 'auto',
        'smtp_host'                     => '',
        'smtp_port'                     => '465',
        'smtp_secure'                   => 'ssl',
        'smtp_user'                     => '',
        'smtp_pass'                     => '',
        'smtp_timeout'                  => '15',
        'smtp_insecure'                 => '0',
        // Automatic publishing to Cloudflare (Settings -> DNS): off until a token is stored.
        'cf_enabled'                    => '0',
        'cf_token'                      => '',
        'cf_failure_policy'             => 'strict',
        'cf_precheck'                   => '1',
        'cf_cron_key'                   => '',
        // Publishing through DNSHE, for suffixes that are not hosted in Cloudflare.
        'dnshe_enabled'                 => '0',
        'dnshe_key'                     => '',
        'dnshe_secret'                  => '',
        // Anti-flood protection (Rate limits page): off until the operator switches it on.
        'flood_enabled'                 => '0',
        'flood_limit'                   => '60',
        'flood_window'                  => '60',
        'flood_block_minutes'           => '10',
        'flood_escalate'                => '1',
        'flood_whitelist'               => '',
        // WHOIS page: internal lookups are free, external ones are limited and cached.
        'whois_enabled'                 => '1',
        'whois_external'                => '1',
        'whois_external_per_hour'       => '20',
        'whois_cache_minutes'           => '15',
        // Signing in with GitHub (user console only): off until a client id and secret are stored.
        'github_enabled'                => '0',
        'github_client_id'              => '',
        'github_client_secret'          => '',
        'github_allow_signup'           => '1',
        // Manage Records can be closed for visitors who are not signed in, with a notice of your own.
        'manage_closed'                 => '0',
        'manage_notice'                 => '',
        'manage_notice_zh'              => '',
        // Transfer requests (tickets) for names published through DNSHE.
        'transfer_enabled'              => '1',
        // The market place (selling domains bought elsewhere).
        'market_enabled'                => '1',
        // The Shop: non-paid ways to raise the allowance.
        'shop_enabled'                  => '1',
        'promo_bonus_amount'            => '2',
        'promo_max_total'               => '10',
        // Support chat (auto-refreshing pages, no JavaScript).
        'chat_enabled'                  => '1',
        'chat_refresh_seconds'          => '8',
        // Dynamic updates (routers, NAS, scripts).
        'ddns_enabled'                  => '1',
        // The HTTP API for console accounts.
        'api_enabled'                   => '1',
        // Mail forwarding through Cloudflare Email Routing.
        'mailroute_enabled'             => '1',
        // The changelog page; editable in the back office like the announcement.
        'changelog'                     => default_changelog_text(),
        'changelog_zh'                  => default_changelog_text_zh(),
        // Terms of service and privacy note (editable in the back office).
        'terms_text'                    => default_terms_text(),
        'terms_text_zh'                 => default_terms_text_zh(),
        'privacy_text'                  => default_privacy_text(),
        'privacy_text_zh'               => default_privacy_text_zh(),
        'trust_proxy'                   => '0',
        'listing_page_size'             => '200',
    ];
}

function settings_all(): array
{
    if (!isset($GLOBALS['FD_SETTINGS'])) {
        $rows = [];
        try {
            foreach (db_query('SELECT setting_key, setting_value FROM settings') as $r) {
                $rows[(string)$r['setting_key']] = (string)$r['setting_value'];
            }
        } catch (Throwable $e) {
            $rows = [];
        }
        $GLOBALS['FD_SETTINGS'] = array_merge(default_settings(), $rows);
    }

    return $GLOBALS['FD_SETTINGS'];
}

function setting(string $key, $default = '')
{
    $all = settings_all();

    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_bool(string $key, bool $default = false): bool
{
    $value = setting($key, $default ? '1' : '0');

    return $value === '1' || $value === 'true' || $value === 'on' || $value === 'yes';
}

function set_setting(string $key, string $value): void
{
    db_query(
        'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
        [$key, $value]
    );
    $GLOBALS['FD_SETTINGS'] = array_merge(settings_all(), [$key => $value]);
}

/* ------------------------------------------------------------------ *
 * Languages                                                           *
 *                                                                     *
 * The public site exists in English (site root) and Chinese (/zh/).   *
 * A page under /zh/ sets $FD_FORCE_LANG before including the page     *
 * from the site root; the back office always runs in English.         *
 * ------------------------------------------------------------------ */

/**
 * The languages the site knows about.
 *
 * English and Simplified Chinese are complete; the other four are being translated, which is why the array also
 * says roughly how far along each one is. Nothing here decides what a page shows - a missing entry already falls
 * back to English inside t() - it only decides what the switcher offers and whether a page tells its reader that
 * it is not finished yet.
 */
function fd_languages(): array
{
    return [
        'en'      => ['English',            'English'],
        'zh'      => ['简体中文',            'Simplified Chinese'],
        'zh-hant' => ['繁體中文',            'Traditional Chinese'],
        'ja'      => ['日本語',              'Japanese'],
        'ko'      => ['한국어',              'Korean'],
        'ms'      => ['Bahasa Melayu',      'Malay'],
    ];
}

/** The language in use, always one of fd_languages(). */
/**
 * Is the page being shown in Chinese - Simplified or Traditional?
 *
 * The early pages decided everything with `is_zh() ? '中文' : 'English'`, which quietly meant that
 * Traditional Chinese (and any other Chinese catalogue added later) fell through to the English branch. Every
 * one of those checks now asks this question instead, so a Chinese reader gets Chinese.
 */
function is_zh(): bool
{
    return str_starts_with(lang(), 'zh');
}
function lang(): string
{
    $want = (string)($GLOBALS['FD_FORCE_LANG'] ?? '');

    return array_key_exists($want, fd_languages()) ? $want : 'en';
}

/** Switches the language for the rest of the request and returns the previous one. */
function lang_switch(string $lang): string
{
    $previous = lang();
    $GLOBALS['FD_FORCE_LANG'] = array_key_exists($lang, fd_languages()) ? $lang : 'en';

    return $previous;
}

/**
 * Is the language in use missing entries that English has?
 *
 * Used to show one line at the top of a page: this language is not finished, anything untranslated is shown in
 * English. Better to say so than to leave a reader wondering why half the page is in another language.
 */
function lang_incomplete(): bool
{
    $current = lang();
    if ($current === 'en') {
        return false;
    }
    if ($current === 'zh') {
        return false;       // Simplified Chinese is complete
    }

    return count(lang_catalogue_for($current)) < count(lang_catalogue_for('en'));
}

/** The language to show in a switcher: its own name, or its English name. */
function lang_label(string $code, bool $native = true): string
{
    $names = fd_languages()[$code] ?? null;
    if ($names === null) {
        return $code;
    }

    return $native ? $names[0] : $names[1];
}

/**
 * The footer's language line: English - 简体中文 - 其它語言.
 *
 * Listing all six languages grew into a long line, so only the two complete ones stay in it, the language in use
 * is marked, and everything else - however many languages the list holds, this function does not care - is
 * reached through one link to language.php, which shows them together with how far each translation has got.
 *
 * The addresses are built the same way as in lang_switcher_html(): the current path loses any language prefix,
 * and the chosen one takes its place, query string and all.
 */
function lang_line_html(): string
{
    $current = lang();
    $uri     = (string)($_SERVER['REQUEST_URI'] ?? '/');

    $path = '/' . ltrim((string)parse_url($uri, PHP_URL_PATH), '/');
    foreach (array_keys(fd_languages()) as $code) {
        if ($code !== 'en' && str_starts_with($path, '/' . $code . '/')) {
            $path = substr($path, strlen($code) + 1);
            break;
        }
    }
    if (basename($path) === 'language.php') {
        $path = '/index.php';           // the chooser itself: changing language lands on the home page
    }
    $query = (string)parse_url($uri, PHP_URL_QUERY);
    $tail  = $query === '' ? '' : '?' . $query;

    $parts = [];
    foreach (['en', 'zh'] as $code) {
        $name    = lang_label($code);
        $parts[] = $code === $current
            ? '<strong>' . h($name) . '</strong>'
            : '<a href="' . h(($code === 'en' ? '' : '/' . $code) . $path . $tail) . '" hreflang="' . h($code)
              . '">' . h($name) . '</a>';
    }

    // The language in use, when it is one of the others: a reader of /ja/ should see where he is.
    if (!in_array($current, ['en', 'zh'], true)) {
        $parts[] = '<strong>' . h(lang_label($current)) . '</strong>';
    }
    $parts[] = '<a href="' . h('/language.php' . $tail) . '">'
        . h(function_exists('is_zh') && is_zh() ? '其它語言' : 'Other languages') . '</a>';

    return implode(' &middot; ', $parts);
}

/**
 * The language switcher: one link per language.
 *
 * A language is only offered when it can actually be shown - English lives in the site root, the others in a
 * directory named after the code. The moment such a directory exists the language joins the menu, and until then
 * nobody is sent to an address that does not exist yet.
 *
 * The current address decides the rest: any language prefix on it is stripped and the chosen one takes its place,
 * query string and all.
 */
function lang_switcher_html(): string
{
    $current = lang();
    $root    = defined('FD_ROOT') ? FD_ROOT : dirname(__DIR__);
    $uri     = (string)($_SERVER['REQUEST_URI'] ?? '/');

    $path = '/' . ltrim((string)parse_url($uri, PHP_URL_PATH), '/');
    foreach (array_keys(fd_languages()) as $code) {
        if ($code !== 'en' && str_starts_with($path, '/' . $code . '/')) {
            $path = substr($path, strlen($code) + 1);
            break;
        }
    }
    $query = (string)parse_url($uri, PHP_URL_QUERY);

    $links = [];
    foreach (fd_languages() as $code => $names) {
        if ($code === $current) {
            continue;
        }
        if ($code !== 'en' && !is_dir($root . '/' . $code)) {
            continue;                       // no directory yet: that language cannot be shown
        }
        $href = ($code === 'en' ? '' : '/' . $code) . $path . ($query === '' ? '' : '?' . $query);
        $links[] = '<a href="' . h($href) . '" hreflang="' . h($code) . '" title="'
            . h(lang_label($code, false)) . '">' . h(lang_label($code)) . '</a>';
    }

    return $links === [] ? '' : implode(' &middot; ', $links);
}

function lang_catalogue_for(string $lang): array
{
    if (!isset($GLOBALS['FD_LANG_CAT'][$lang])) {
        $file = FD_INC . '/lang/' . $lang . '.php';
        $data = is_file($file) ? require $file : [];
        $GLOBALS['FD_LANG_CAT'][$lang] = is_array($data) ? $data : [];
    }

    return $GLOBALS['FD_LANG_CAT'][$lang];
}

/** The catalogue of the language in use. */
function lang_catalogue(): array
{
    return lang_catalogue_for(lang());
}

/** Returns a translated string; unknown keys fall back to English and then to the key itself. */
function t(string $key, ...$args): string
{
    $text = lang_catalogue()[$key] ?? null;
    if ($text === null && lang() !== 'en') {
        $text = lang_catalogue_for('en')[$key] ?? null;
    }
    if ($text === null) {
        $text = $key;
    }

    if ($args === []) {
        return (string)$text;
    }

    // A translation with more placeholders than arguments must never break a page: the missing ones are
    // filled with an empty string, and a formatting problem falls back to the plain text.
    $text    = (string)$text;
    $needed  = max(0, substr_count(preg_replace('/%%/', '', $text) ?? $text, '%') );
    if ($needed > count($args)) {
        $args = array_pad($args, $needed, '');
    }

    try {
        return vsprintf($text, $args);
    } catch (Throwable $e) {
        return $text;
    }
}

/** The directory a language lives in (empty for the site root). */
function lang_dir(): string
{
    return lang() === 'en' ? '' : lang();
}

/** Absolute URL of a path inside the directory of the given language. */
function lang_url(string $lang, string $path = ''): string
{
    $base   = app_base_url();
    $prefix = $lang === 'en' ? $base . '/' : $base . '/' . $lang . '/';
    $path   = ltrim($path, '/');

    return $path === '' ? $prefix : $prefix . $path;
}

/**
 * Link to the same page in the other language, for the language switcher.
 *
 * The path below the language directory is kept, so /zh/account/login.php links to
 * /account/login.php and not to /login.php.
 */
function other_lang_url(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

    // Drop the installation directory (the site may live in a subdirectory).
    $basePath = rtrim((string)(parse_url(app_base_url(), PHP_URL_PATH) ?? ''), '/');
    if ($basePath !== '' && str_starts_with($script, $basePath)) {
        $script = substr($script, strlen($basePath));
    }

    $path = ltrim($script, '/');
    if (str_starts_with($path, 'zh/')) {
        $path = substr($path, 3);
    } elseif ($path === 'zh') {
        $path = '';
    }

    $url = lang_url(is_zh() ? 'en' : 'zh', $path);

    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') {
        $url .= '?' . $query;
    }

    return $url;
}

/** An administrator editable text in the current language, falling back to the English value. */
function setting_lang(string $key): string
{
    if (lang() !== 'en') {
        $value = trim((string)setting($key . '_' . lang(), ''));
        if ($value !== '') {
            return $value;
        }
    }

    return (string)setting($key, '');
}

/* ------------------------------------------------------------------ *
 * Zones (the suffixes names are handed out under)                     *
 * ------------------------------------------------------------------ */

function zone_list(bool $onlyOpen = false): array
{
    if (!isset($GLOBALS['FD_ZONES']) || !is_array($GLOBALS['FD_ZONES'])) {
        try {
            $GLOBALS['FD_ZONES'] = db_all('SELECT * FROM zones ORDER BY sort_order ASC, name ASC');
        } catch (Throwable $e) {
            $GLOBALS['FD_ZONES'] = [];
        }
    }

    $all = $GLOBALS['FD_ZONES'];
    if (!$onlyOpen) {
        return $all;
    }

    return array_values(array_filter($all, static fn($zone) => (int)$zone['is_open'] === 1));
}

function zone_refresh(): void
{
    $GLOBALS['FD_ZONES'] = null;
    zone_list();
}

function zone_by_name(string $name): ?array
{
    $name = strtolower(trim($name, ". \t\n\r\0\x0B"));
    if ($name === '') {
        return null;
    }
    foreach (zone_list() as $zone) {
        if (strtolower((string)$zone['name']) === $name) {
            return $zone;
        }
    }

    return null;
}

function default_zone(): ?array
{
    $open = zone_list(true);
    if ($open !== []) {
        return $open[0];
    }
    $all = zone_list();

    return $all === [] ? null : $all[0];
}

/** "freedns.my", "freedns.my or asia.ci", "freedns.my, asia.ci or example.org" */
function zones_sentence(bool $onlyOpen = true): string
{
    $names = [];
    foreach (zone_list($onlyOpen) as $zone) {
        $names[] = (string)$zone['name'];
    }
    if ($names === []) {
        return 'this registry';
    }
    if (count($names) === 1) {
        return $names[0];
    }
    $last = array_pop($names);

    return implode(', ', $names) . ' or ' . $last;
}

function zone_max_domains_per_email(array $zone): int
{
    $perZone = (int)($zone['max_domains_per_email'] ?? 0);
    if ($perZone > 0) {
        return $perZone;
    }

    return max(1, (int)setting('max_domains_per_email', '5'));
}

function zone_allows_subdomains(string $zoneName): bool
{
    $zone = zone_by_name($zoneName);

    // Fail closed: an unknown zone (renamed, deleted, hand-edited) grants nothing.
    return $zone === null ? false : (int)$zone['allow_subdomains'] === 1;
}

/** Title of a zone in the current language, falling back to the English one. */
function zone_title_text(array $zone): string
{
    if (lang() !== 'en') {
        $value = trim((string)($zone['title_' . lang()] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string)($zone['title'] ?? ''));
}

/** Description of a zone in the current language, falling back to the English one. */
function zone_description_text(array $zone): string
{
    if (lang() !== 'en') {
        $value = trim((string)($zone['description_' . lang()] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string)($zone['description'] ?? ''));
}

function default_ttl(): int
{
    return max(60, (int)setting('default_ttl', '3600'));
}

/**
 * How many records one name may hold.
 *
 * Cloudflare refuses more than 200 records in a zone and then fails the whole publish, so a
 * suffix published through Cloudflare keeps a firm cap (setting cf_record_cap, default 120).
 * Every other provider accepts what we send, so those suffixes have no practical limit - the
 * remaining brakes are the four-records-per-submission rule and the pagination in the editor.
 *
 * Callers that cannot name a suffix get the Cloudflare cap, which is the safe side.
 */
function max_records_per_domain(?string $zoneName = null): int
{
    $provider = 'cloudflare';
    if (function_exists('publish_provider') && $zoneName !== null && $zoneName !== '') {
        $provider = (string)publish_provider($zoneName);
    }

    if ($provider === 'cloudflare' || $provider === '') {
        $cap = (int)setting('cf_record_cap', '120');
        if ($cap < 1 || $cap > 200) {
            $cap = 120;
        }

        return $cap;
    }

    /* Far beyond any real zone, but keeps the arithmetic in the editor sane. */
    return 100000;
}

/** True when this suffix has no practical record limit. */
function records_unlimited(string $zoneName): bool
{
    return max_records_per_domain($zoneName) >= 100000;
}

/* ------------------------------------------------------------------ *
 * Output, URLs, sessions                                              *
 * ------------------------------------------------------------------ */

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** True when the request itself arrived over HTTPS (honouring a trusted reverse proxy). */
function request_is_https(): bool
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';

    if (setting_bool('trust_proxy')) {
        $forwardedProto = explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if (strtolower(trim($forwardedProto[0])) === 'https') {
            $https = true;
        }
    }

    return $https;
}

function detected_base_url(): string
{
    $https = request_is_https();
    $host  = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

    if (setting_bool('trust_proxy')) {
        // Only honour an overridden host when it is the host the site is configured for;
        // otherwise a visitor could poison every generated absolute URL.
        $forwardedHost  = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0]);
        $configuredHost = (string)(parse_url((string)(config('site', [])['base_url'] ?? ''), PHP_URL_HOST) ?? '');
        if ($forwardedHost !== '' && $configuredHost !== ''
            && strcasecmp($forwardedHost, $configuredHost) === 0) {
            $host = $forwardedHost;
        }
    }

    if (!preg_match('/^[A-Za-z0-9._:\[\]-]+$/', $host)) {
        $host = 'localhost';
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (basename($dir) === 'admin') {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }

    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function app_base_url(): string
{
    $cfg  = config('site', []);
    $base = trim((string)($cfg['base_url'] ?? ''));
    if ($base === '') {
        $base = detected_base_url();
    }

    return rtrim($base, '/');
}

/** Absolute URL of a page of the current language (links stay inside /zh/ when in Chinese). */
function url(string $path = ''): string
{
    return lang_url(lang(), $path);
}

/** Removes the characters that could break an HTTP header out of its intended field. */
function header_safe(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $value));
}

/** Accepts only a site-local path such as "/admin/domains.php?status=pending". */
function safe_relative_url(string $target, string $fallback = '/'): string
{
    $target = str_replace(["\r", "\n", "\0", '\\'], '', $target);
    if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
        return $fallback;
    }

    return $target;
}

function redirect(string $target): void
{
    header('Location: ' . str_replace(["\r", "\n", "\0"], '', $target));
    exit;
}

function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    if (setting_bool('trust_proxy')) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') {
                $parts = explode(',', $value);
                $ip    = trim((string)($parts[0] ?? ''));
                break;
            }
        }
    }

    return substr($ip, 0, 45);
}

function fd_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        // Too late for a cookie; the page keeps working without a session.
        return;
    }

    $cfg       = config('site', []);
    $sec       = config('security', []);
    // Behind a TLS-terminating proxy the request itself is plain HTTP, so the proxy header counts.
    $https     = request_is_https();
    $name      = (string)($cfg['session_name'] ?? 'freednssid');

    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($sec['cookie_secure']) && $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------------ *
 * Flash messages and CSRF                                             *
 * ------------------------------------------------------------------ */

function flash(string $message, string $type = 'info'): void
{
    fd_start_session();
    if (!isset($_SESSION['fd_flash'])) {
        $_SESSION['fd_flash'] = [];
    }
    $_SESSION['fd_flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    // Pages that never started a session (the informational public pages) have nothing to take.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $flashes = $_SESSION['fd_flash'] ?? [];
    unset($_SESSION['fd_flash']);

    return is_array($flashes) ? $flashes : [];
}

function csrf_token(): string
{
    fd_start_session();
    if (empty($_SESSION['fd_csrf'])) {
        $_SESSION['fd_csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['fd_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    fd_start_session();
    $sent = (string)($_POST['csrf'] ?? '');
    if ($sent === '' || empty($_SESSION['fd_csrf']) || !hash_equals((string)$_SESSION['fd_csrf'], $sent)) {
        http_response_code(419);
        page_header(t('error.token_title'));
        echo '<p>' . h(t('error.token_body')) . '</p>';
        page_footer();
        exit;
    }
}

/* ------------------------------------------------------------------ *
 * Validation                                                          *
 * ------------------------------------------------------------------ */

function valid_label(string $label): bool
{
    return (bool)preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label);
}

/**
 * Validates a host name. Not used by the current address-only workflow, but kept for the
 * day the zone is accepted into the Public Suffix List and name-server delegation returns.
 */
function valid_hostname(string $host): bool
{
    $host = rtrim(strtolower(trim($host)), '.');
    if ($host === '' || strlen($host) > 253) {
        return false;
    }
    $labels = explode('.', $host);
    if (count($labels) < 2) {
        return false;
    }
    foreach ($labels as $label) {
        if ($label === '' || strlen($label) > 63) {
            return false;
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
            return false;
        }
    }

    return true;
}

function valid_email_addr(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190;
}

function valid_ipv4(string $ip): bool
{
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

function valid_ipv6(string $ip): bool
{
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
}

function reserved_labels(): array
{
    return [
        'www', 'mail', 'smtp', 'pop', 'pop3', 'imap', 'ns', 'ns1', 'ns2', 'ns3', 'ns4',
        'dns', 'dns1', 'dns2', 'admin', 'administrator', 'root', 'webmail', 'ftp', 'cpanel',
        'whm', 'api', 'mx', 'mx1', 'mx2', 'localhost', 'test', 'dev', 'staging', 'vpn',
        'proxy', 'cdn', 'static', 'assets', 'mailer', 'postmaster', 'abuse', 'hostmaster',
        'whois', 'rdns', 'in-addr', 'ipv4', 'ipv6', 'registry', 'nic', 'billing', 'support',
        // Names that must never belong to one person, because mail or trust is attached to them: these are
        // the addresses a stranger would write to, and the ones our own forwarding rules must refuse.
        'postmaster', 'mailer-daemon', 'noreply', 'no-reply', 'donotreply', 'security', 'cert',
        'sales', 'info', 'contact', 'help', 'helpdesk', 'service', 'services', 'official',
        'system', 'sysadmin', 'operator', 'monitor', 'status', 'health', 'alert', 'alerts',
        'office', 'hr', 'legal', 'privacy', 'dmca', 'spam', 'phishing', 'fraud', 'complaint',
        'app', 'apps', 'portal', 'my', 'account', 'accounts', 'user', 'users', 'login', 'signin',
        'auth', 'sso', 'id', 'me', 'profile', 'panel', 'dashboard', 'console', 'manage',
        'shop', 'store', 'pay', 'payment', 'payments', 'invoice', 'checkout', 'order', 'orders',
        'blog', 'news', 'forum', 'community', 'chat', 'ticket', 'tickets', 'docs', 'doc', 'wiki',
        'download', 'downloads', 'upload', 'files', 'file', 'data', 'db', 'sql', 'backup',
        'server', 'servers', 'host', 'hosting', 'cloud', 'node', 'edge', 'lb', 'gateway',
        'demo', 'example', 'sample', 'invalid', 'null', 'none', 'unknown', 'default',
        'freedns', 'freednsmy', 'myfreedns', 'www2', 'www3', 'web', 'web1', 'web2',
        'ns0', 'ns5', 'ns6', 'dns3', 'dns4', 'time', 'ntp', 'ldap', 'radius', 'git', 'svn',
        'ci', 'build', 'jenkins', 'docker', 'k8s', 'grafana', 'prometheus', 'nagios', 'zabbix',
        'autodiscover', 'autoconfig', 'mta-sts', '_dmarc', '_domainkey', 'dkim', 'spf',
    ];
}

/**
 * Accepts "example", "example.freedns.my" or "EXAMPLE.FREEDNS.MY." and returns the label.
 * With an empty $zoneName the suffix of any configured zone is stripped.
 */
function normalize_label(string $input, string $zoneName = ''): string
{
    $value = strtolower(trim($input));
    $value = preg_replace('/\s+/', '', $value) ?? '';
    $value = rtrim($value, '.');

    $suffixes = [];
    if ($zoneName !== '') {
        $suffixes[] = strtolower($zoneName);
    } else {
        foreach (zone_list() as $zone) {
            $suffixes[] = strtolower((string)$zone['name']);
        }
    }

    foreach ($suffixes as $suffix) {
        if ($suffix !== '' && str_ends_with($value, '.' . $suffix)) {
            $value = substr($value, 0, -(strlen($suffix) + 1));
            break;
        }
    }

    return rtrim($value, '.');
}

/* ------------------------------------------------------------------ *
 * Reserved names                                                      *
 *                                                                     *
 * The registry can keep names for itself: they are refused on the      *
 * ordinary request form and can only be requested with the             *
 * reserved-name option, which also allows a single-character name.     *
 * ------------------------------------------------------------------ */

/**
 * True when the reserved_names table exists. The feature may be installed before the matching
 * upgrade file has been imported, and nothing should turn into a fatal error in that case.
 */
function reserved_table_ready(): bool
{
    if (isset($GLOBALS['FD_RESERVED_READY'])) {
        return (bool)$GLOBALS['FD_RESERVED_READY'];
    }

    $ready = false;
    try {
        // The underscore is escaped so LIKE looks for the literal table name.
        $ready = db_one("SHOW TABLES LIKE 'reserved\\_names'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_RESERVED_READY'] = $ready;

    return $ready;
}

/** All reserved entries, or those of one zone, ordered for display. */
function reserved_name_rows(?string $zoneName = null): array
{
    if (!reserved_table_ready()) {
        return [];
    }

    if ($zoneName === null || $zoneName === '') {
        return db_all('SELECT * FROM reserved_names ORDER BY zone ASC, label ASC');
    }

    return db_all(
        'SELECT * FROM reserved_names WHERE zone = ? ORDER BY label ASC',
        [strtolower(trim($zoneName))]
    );
}

/** The reserved labels of one zone, lower case. */
function reserved_labels_in_zone(string $zoneName): array
{
    $labels = [];
    foreach (reserved_name_rows($zoneName) as $row) {
        $labels[] = (string)$row['label'];
    }

    return $labels;
}

function reserved_name_is_listed(string $zoneName, string $label): bool
{
    $zoneName = strtolower(trim($zoneName));
    $label    = strtolower(trim($label));
    if ($zoneName === '' || $label === '' || !reserved_table_ready()) {
        return false;
    }

    return db_one(
        'SELECT id FROM reserved_names WHERE zone = ? AND label = ?',
        [$zoneName, $label]
    ) !== null;
}

function reserved_name_count(?string $zoneName = null): int
{
    if (!reserved_table_ready()) {
        return 0;
    }

    if ($zoneName === null || $zoneName === '') {
        return (int)db_value('SELECT COUNT(*) FROM reserved_names', [], 0);
    }

    return (int)db_value(
        'SELECT COUNT(*) FROM reserved_names WHERE zone = ?',
        [strtolower(trim($zoneName))],
        0
    );
}

/**
 * Why a label cannot be put on the reserved list; an empty string means it can.
 * Returns the message as an i18n key suffix so the caller can translate it.
 */
function reserved_label_problem(string $zoneName, string $label): string
{
    if (zone_by_name($zoneName) === null) {
        return 'zone';
    }
    if ($label === '') {
        return 'empty';
    }
    if (strlen($label) > 63) {
        return 'long';
    }
    if (str_contains($label, '.')) {
        return 'dots';
    }
    if (!valid_label($label)) {
        return 'chars';
    }

    return '';
}

/** Adds one reserved name. Returns '' on success or the reason it was refused. */
function reserved_name_add(string $zoneName, string $label, ?string $note = null, ?string $adminName = null): string
{
    $zoneName = strtolower(trim($zoneName));
    $label    = strtolower(trim($label));

    if (!reserved_table_ready()) {
        return 'notable';
    }

    $problem = reserved_label_problem($zoneName, $label);
    if ($problem !== '') {
        return $problem;
    }
    if (reserved_name_is_listed($zoneName, $label)) {
        return 'exists';
    }

    $note = $note === null ? '' : trim($note);
    db_query(
        'INSERT INTO reserved_names (zone, label, note, created_by, created_at) VALUES (?, ?, ?, ?, NOW())',
        [
            $zoneName,
            $label,
            $note === '' ? null : limit_chars($note, 190),
            $adminName === null || $adminName === '' ? null : limit_chars($adminName, 32),
        ]
    );

    return '';
}

function reserved_name_delete(int $id): void
{
    if (!reserved_table_ready()) {
        return;
    }

    db_query('DELETE FROM reserved_names WHERE id = ?', [$id]);
}

/* ------------------------------------------------------------------ *
 * Address display                                                     *
 * ------------------------------------------------------------------ */

/**
 * Hides most of an address for the public list: 23.45.67.89 -> 23.****.***.89 and
 * 2001:db8:1:2:3:4:5:6 -> 2001:db8:****:****:****:****:****:6.
 * Anything that is not an address is returned unchanged, so CNAME and TXT records stay readable.
 */
function mask_address(string $value): string
{
    $value = trim($value);

    if (valid_ipv4($value)) {
        $parts = explode('.', $value);
        if (count($parts) === 4) {
            return $parts[0] . '.****.***.' . $parts[3];
        }
    }

    if (valid_ipv6($value)) {
        $binary = @inet_pton($value);
        if (is_string($binary) && strlen($binary) === 16) {
            $groups = str_split(bin2hex($binary), 4);
            $show   = static function (string $group): string {
                $short = ltrim(strtolower($group), '0');

                return $short === '' ? '0' : $short;
            };

            return $show($groups[0]) . ':' . $show($groups[1])
                . ':****:****:****:****:****:' . $show($groups[7]);
        }
    }

    return $value;
}

/* ------------------------------------------------------------------ *
 * Request codes, status display                                       *
 * ------------------------------------------------------------------ */

function generate_request_code(): string
{
    return 'FD-' . date('y') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function status_text(string $status): string
{
    $key = 'status.' . $status;
    $text = t($key);
    if ($text !== $key) {
        return $text;
    }

    return strtoupper($status);
}

function status_options(): array
{
    return ['pending', 'active', 'rejected', 'suspended', 'deleted'];
}

/** Shortens free text for table columns; multibyte safe when mbstring is present. */
function shorten(?string $text, int $length = 100): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text) ?? (string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8') > $length
            ? mb_substr($text, 0, $length, 'UTF-8') . '...'
            : $text;
    }

    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function format_date(?string $value): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return '-';
    }

    return substr($value, 0, 10);
}

/* ------------------------------------------------------------------ *
 * Pagination                                                          *
 * ------------------------------------------------------------------ */

function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int)ceil($total / $perPage));
    $page    = min(max(1, $page), $pages);

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/** Renders prev/next links for the current script, keeping every query argument in use. */
function page_links(array $p, array $query = []): string
{
    if ((int)$p['pages'] <= 1) {
        return '';
    }

    $script = basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
    $make   = static function (int $n) use ($query, $script): string {
        $q         = array_merge($_GET, $query);
        $q['page'] = $n;

        return $script . '?' . http_build_query($q);
    };

    $parts = [];
    if ((int)$p['page'] > 1) {
        $parts[] = '<a href="' . h($make((int)$p['page'] - 1)) . '">&lt; previous</a>';
    }
    $parts[] = 'page ' . (int)$p['page'] . ' of ' . (int)$p['pages'];
    if ((int)$p['page'] < (int)$p['pages']) {
        $parts[] = '<a href="' . h($make((int)$p['page'] + 1)) . '">next &gt;</a>';
    }

    return '<p class="small">' . implode(' &middot; ', $parts) . '</p>';
}

/* ------------------------------------------------------------------ *
 * Mail notifications (best effort, never fatal)                       *
 *                                                                     *
 * The transport (SMTP client or PHP mail()) lives in inc/mail.php;     *
 * these functions only compose the messages.                          *
 * ------------------------------------------------------------------ */

/** Registry mails are always written in English; applicant mails follow the request language. */
function notify_admin_new_request(array $domain, array $records): void
{
    $to = trim((string)setting('notify_admin_email', ''));
    if ($to === '') {
        return;
    }

    $previous = lang_switch('en');

    $lines = [
        t('mail.request_intro'),
        '',
        t('mail.domain', (string)$domain['name']),
        t('mail.zone', (string)($domain['zone'] ?? '')),
        t('mail.code', (string)$domain['request_code']),
        t('mail.applicant', (string)$domain['registrant_name'], (string)$domain['registrant_email']),
        t('mail.organization', (string)($domain['organization'] ?? '')),
        t('mail.country', (string)($domain['country'] ?? '')),
        '',
        t('mail.records'),
    ];
    if (!empty($domain['reserved'])) {
        // Makes the reserved request stand out in the operator's mailbox.
        $lines[] = '';
        $lines[] = t('mail.reserved');
    }
    foreach (record_zone_lines($records, (string)$domain['name']) as $line) {
        $lines[] = '  ' . $line;
    }
    $lines[] = '';
    $lines[] = t('mail.purpose');
    $lines[] = (string)($domain['justification'] ?? '');
    $lines[] = '';
    $lines[] = t('mail.review_at', url('admin/edit.php?id=' . (int)$domain['id']));

    $subject = t('mail.request_subject', (string)$domain['name']);
    lang_switch($previous);

    send_mail($to, $subject, implode("\n", $lines));
}

function notify_admin_new_change(array $domain, array $change, array $records): void
{
    $to = trim((string)setting('notify_admin_email', ''));
    if ($to === '') {
        return;
    }

    $previous = lang_switch('en');

    $lines = [
        t('mail.change_intro'),
        '',
        t('mail.domain', (string)$domain['name']),
        t('mail.code', (string)$domain['request_code']),
        t('mail.applicant', (string)$domain['registrant_name'], (string)$domain['registrant_email']),
        t('mail.submitted', (string)$change['submitted_at']),
        '',
        t('mail.records_proposed'),
    ];
    foreach (record_zone_lines($records, (string)$domain['name']) as $line) {
        $lines[] = '  ' . $line;
    }
    $note = trim((string)($change['applicant_note'] ?? ''));
    if ($note !== '') {
        $lines[] = '';
        $lines[] = t('mail.note_applicant');
        $lines[] = $note;
    }
    $lines[] = '';
    $lines[] = t('mail.review_at', url('admin/change.php?id=' . (int)$change['id']));

    $subject = t('mail.change_subject', (string)$domain['name']);
    lang_switch($previous);

    send_mail($to, $subject, implode("\n", $lines));
}

function notify_applicant_decision(array $domain, array $records): void
{
    if (!setting_bool('notify_applicant', true)) {
        return;
    }

    // The applicant is written to in the language the request was submitted in.
    $previous = lang_switch((string)($domain['language'] ?? 'en'));

    $status  = (string)$domain['status'];
    $subject = t('mail.decision_subject', (string)$domain['name'], status_text($status));
    $lines   = [
        t('mail.dear', (string)$domain['registrant_name']),
        '',
        t('mail.request_state', (string)$domain['name'], status_text($status)),
        t('mail.code', (string)$domain['request_code']),
        '',
        t('mail.records'),
    ];
    foreach (record_zone_lines($records, (string)$domain['name']) as $line) {
        $lines[] = '  ' . $line;
    }
    $note = trim((string)($domain['public_note'] ?? ''));
    if ($note !== '') {
        $lines[] = '';
        $lines[] = t('mail.note_registry');
        $lines[] = $note;
    }
    $lines[] = '';
    $lines[] = t('mail.check_status', url('status.php'));
    $lines[] = t('mail.manage', url('manage.php'));
    $lines[] = '';
    $lines[] = setting('site_title', 'freedns.my');

    lang_switch($previous);

    if (mail_pref_allows((string)$domain['registrant_email'], 'domain')) {
        send_mail((string)$domain['registrant_email'], $subject, implode("\n", $lines));
    }
}

function notify_applicant_change(array $domain, array $change, array $records, bool $approved): void
{
    if (!setting_bool('notify_applicant', true)) {
        return;
    }

    $previous = lang_switch((string)($domain['language'] ?? 'en'));

    $subject = t(
        'mail.change_decision_subject',
        (string)$domain['name'],
        $approved ? t('mail.approved_short') : t('mail.rejected_short')
    );
    $lines   = [
        t('mail.dear', (string)$domain['registrant_name']),
        '',
        trim(t(
            'mail.change_state',
            (string)$domain['name'],
            (string)$change['submitted_at'],
            $approved ? t('mail.approved') : t('mail.rejected')
        )),
        '',
        $approved ? t('mail.records_now') : t('mail.records_proposed'),
    ];
    foreach (record_zone_lines($records, (string)$domain['name']) as $line) {
        $lines[] = '  ' . $line;
    }
    $note = trim((string)($change['admin_note'] ?? ''));
    if ($note !== '') {
        $lines[] = '';
        $lines[] = t('mail.note_registry');
        $lines[] = $note;
    }
    $lines[] = '';
    $lines[] = t('mail.manage', url('manage.php'));
    $lines[] = '';
    $lines[] = setting('site_title', 'freedns.my');

    lang_switch($previous);

    if (mail_pref_allows((string)$domain['registrant_email'], 'domain')) {
        send_mail((string)$domain['registrant_email'], $subject, implode("\n", $lines));
    }
}

/* ------------------------------------------------------------------ *
 * Address helpers (the registry publishes A / AAAA records itself,    *
 * because sub-domains cannot be delegated until the zone is in the    *
 * Public Suffix List)                                                 *
 * ------------------------------------------------------------------ */

/** Returns 'ipv4', 'ipv6' or '' for anything that is not an IP address. */
function address_version(string $ip): string
{
    if (valid_ipv4($ip)) {
        return 'ipv4';
    }
    if (valid_ipv6($ip)) {
        return 'ipv6';
    }

    return '';
}

/** Canonical spelling: 2001:0DB8:0:0::1 becomes 2001:db8::1. */
function normalize_address(string $ip): string
{
    $packed = @inet_pton($ip);
    if ($packed === false) {
        return strtolower(trim($ip));
    }
    $readable = @inet_ntop($packed);

    return $readable === false ? strtolower(trim($ip)) : strtolower($readable);
}

/**
 * Rejects private, loopback, link-local, carrier-grade NAT, benchmarking, multicast, reserved
 * and documentation ranges.
 *
 * PHP's FILTER_FLAG_NO_RES_RANGE does *not* cover the IPv4/IPv6 documentation ranges
 * (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32) on several PHP versions, so
 * they are checked explicitly here.
 */
function is_public_address(string $ip): bool
{
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

    if (valid_ipv4($ip)) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | $flags) === false) {
            return false;
        }
        $o = array_map('intval', explode('.', $ip));
        // 100.64.0.0/10 carrier-grade NAT, 198.18.0.0/15 benchmarking.
        if ($o[0] === 100 && $o[1] >= 64 && $o[1] <= 127) {
            return false;
        }
        if ($o[0] === 198 && ($o[1] === 18 || $o[1] === 19)) {
            return false;
        }
        // Documentation and special-use ranges.
        if ($o[0] === 192 && $o[1] === 0 && in_array($o[2], [0, 2], true)) {
            return false; // 192.0.0.0/24, 192.0.2.0/24
        }
        if ($o[0] === 198 && $o[1] === 51 && $o[2] === 100) {
            return false; // 198.51.100.0/24
        }
        if ($o[0] === 203 && $o[1] === 0 && $o[2] === 113) {
            return false; // 203.0.113.0/24
        }

        return true;
    }

    if (valid_ipv6($ip)) {
        $value = normalize_address($ip);
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | $flags) === false) {
            return false;
        }

        $packed = @inet_pton($value);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        // ::, ::1 and IPv4-mapped addresses (::ffff:0:0/96)
        $allZero = true;
        for ($i = 0; $i < 16; $i++) {
            if ($packed[$i] !== "\x00") {
                $allZero = false;
                break;
            }
        }
        if ($allZero) {
            return false;
        }
        $mapped = true;
        for ($i = 0; $i < 10; $i++) {
            if ($packed[$i] !== "\x00") {
                $mapped = false;
                break;
            }
        }
        if ($mapped && $packed[10] === "\xff" && $packed[11] === "\xff") {
            return false;
        }

        $b0 = ord($packed[0]);
        $b1 = ord($packed[1]);

        // ff00::/8 multicast
        if ($b0 === 0xff) {
            return false;
        }
        // fe80::/10 link-local and fec0::/10 deprecated site-local
        if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) {
            return false;
        }
        if ($b0 === 0xfe && ($b1 & 0xc0) === 0xc0) {
            return false;
        }
        // 2001:db8::/32 documentation
        if ($b0 === 0x20 && $b1 === 0x01 && $packed[2] === "\x0d" && $packed[3] === "\xb8") {
            return false;
        }

        return true;
    }

    return false;
}

/* ------------------------------------------------------------------ *
 * DNS records (what the registry publishes for a name)                *
 * ------------------------------------------------------------------ */

function record_types(): array
{
    return ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'SRV', 'CAA', 'PTR'];
}

/** The record types an applicant may submit with the initial request (no TXT yet). */
function request_record_types(): array
{
    return ['A', 'AAAA', 'CNAME', 'MX'];
}

function valid_record_type(string $type): bool
{
    return in_array(strtoupper($type), record_types(), true);
}

/** True for the spellings that mean the registered name itself: "", "@" or the full name. */
function normalize_record_sub(string $sub, string $domainName = ''): string
{
    $sub = strtolower(trim($sub));
    if ($sub === '' || $sub === '@' || $sub === '.') {
        return '';
    }
    if ($domainName !== '' && rtrim($sub, '.') === strtolower(rtrim($domainName, '.'))) {
        return '';
    }

    return rtrim($sub, '.');
}

/** How a sub-name is shown in the editors. */
function record_sub_display(string $sub): string
{
    return $sub === '' ? '@' : $sub;
}

/** A sub-name keeps the old spelling, and may now be a wildcard ("*" or "*.label"). */
function valid_sub_label(string $sub): bool
{
    if ($sub === '') {
        return true;
    }

    return (bool)preg_match(
        '/^(\*|[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/',
        $sub
    );
}

/** Canonical spelling of a record value, so that comparisons and the zone file are stable. */
function normalize_record_value(string $type, string $value): string
{
    $value = trim($value);
    $type  = strtoupper($type);

    if ($type === 'A') {
        return $value;
    }
    if ($type === 'AAAA') {
        return normalize_address($value);
    }
    if ($type === 'CNAME') {
        return rtrim(strtolower($value), '.');
    }
    if ($type === 'PTR' || $type === 'MX') {
        // "10 mail.example.com" (MX) or "host.example.com" (PTR): only the target is case-insensitive.
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        if (count($parts) > 1) {
            $last              = array_pop($parts);
            $parts[]           = rtrim(strtolower((string)$last), '.');

            return implode(' ', $parts);
        }

        return rtrim(strtolower(trim($value)), '.');
    }
    if ($type === 'SRV') {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        if (count($parts) === 4) {
            $parts[3] = rtrim(strtolower((string)$parts[3]), '.');

            return implode(' ', $parts);
        }

        return trim($value);
    }
    if ($type === 'CAA') {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    return $value;
}

/** Returns an error message, or an empty string when the record is acceptable. */
function record_validation_error(string $sub, string $type, string $value, string $domainName): string
{
    $sub    = normalize_record_sub($sub, $domainName);
    $type   = strtoupper($type);
    $domain = strtolower($domainName);
    $owner  = $sub === '' ? $domain : $sub . '.' . $domain;

    if (!valid_sub_label($sub)) {
        return t('rec.name_invalid', $sub);
    }
    if (!valid_record_type($type)) {
        return t('rec.type_unsupported', $type);
    }

    switch ($type) {
        case 'A':
            if (!valid_ipv4($value)) {
                return t('rec.a_invalid', $value);
            }
            if (!is_public_address($value)) {
                return t('rec.not_public', $value);
            }
            break;

        case 'AAAA':
            if (!valid_ipv6($value)) {
                return t('rec.aaaa_invalid', $value);
            }
            if (!is_public_address(normalize_address($value))) {
                return t('rec.not_public', $value);
            }
            break;

        case 'CNAME':
            if (!valid_hostname($value)) {
                return t('rec.cname_target', $value);
            }
            if (rtrim(strtolower($value), '.') === $owner) {
                return t('rec.cname_self', $owner);
            }
            break;

        case 'TXT':
            if (strlen($value) > 255) {
                return t('rec.txt_length');
            }
            if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
                return t('rec.txt_control');
            }
            break;

        case 'MX':
            // "10 mail.example.com"
            $parts = preg_split('/\s+/', trim($value)) ?: [];
            if (count($parts) !== 2
                || !preg_match('/^\d{1,5}$/', (string)$parts[0])
                || (int)$parts[0] > 65535) {
                return t('rec.mx_format', $value);
            }
            if (!valid_hostname((string)$parts[1])) {
                return t('rec.mx_target', (string)$parts[1]);
            }
            if (rtrim(strtolower((string)$parts[1]), '.') === $owner) {
                return t('rec.mx_self', $owner);
            }
            break;

        case 'SRV':
            // "priority weight port target"
            $parts = preg_split('/\s+/', trim($value)) ?: [];
            if (count($parts) !== 4) {
                return t('rec.srv_format', $value);
            }
            foreach ([0, 1, 2] as $index) {
                if (!preg_match('/^\d{1,5}$/', (string)$parts[$index]) || (int)$parts[$index] > 65535) {
                    return t('rec.srv_numbers');
                }
            }
            if (!valid_hostname((string)$parts[3])) {
                return t('rec.srv_target', (string)$parts[3]);
            }
            if (rtrim(strtolower((string)$parts[3]), '.') === $owner) {
                return t('rec.srv_self', $owner);
            }
            break;

        case 'CAA':
            // '0 issue "letsencrypt.org"'
            if (preg_match('/^(\d{1,3})\s+(issue|issuewild|iodef)\s+(.+)$/i', trim($value), $match) !== 1) {
                return t('rec.caa_format', $value);
            }
            if ((int)$match[1] > 255) {
                return t('rec.caa_flags');
            }
            $target = trim((string)$match[3], " \t\"'");
            if ($target === '') {
                return t('rec.caa_format', $value);
            }
            if (strtolower((string)$match[2]) === 'iodef') {
                if (!preg_match('#^(https?://|mailto:)#i', $target)) {
                    return t('rec.caa_iodef', $target);
                }
            } elseif (!valid_hostname($target)) {
                return t('rec.caa_target', $target);
            }
            break;

        case 'PTR':
            if (!valid_hostname($value)) {
                return t('rec.ptr_bad', $value);
            }
            if (rtrim(strtolower($value), '.') === $owner) {
                return t('rec.ptr_same', $owner);
            }
            break;
    }

    return '';
}

function domain_by_id(int $id): ?array
{
    return db_one('SELECT * FROM domains WHERE id = ?', [$id]);
}

function domain_records(int $domainId): array
{
    return db_all(
        'SELECT * FROM records WHERE domain_id = ? ORDER BY sub ASC, type ASC, position ASC, id ASC',
        [$domainId]
    );
}

/** Does this installation already store a per-record proxy flag? (added in upgrade-recordtypes.sql) */
function records_proxied_ready(): bool
{
    if (isset($GLOBALS['FD_REC_PROXIED'])) {
        return (bool)$GLOBALS['FD_REC_PROXIED'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM records LIKE 'proxied'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_REC_PROXIED'] = $ready;

    return $ready;
}

/**
 * TTL, with Cloudflare's "Auto" allowed.
 *
 * Cloudflare takes 1 to mean "let us decide" (and it is the only value it accepts on a proxied record).
 * Everything else is clamped to the range both providers accept, so a wild number cannot be stored.
 * The value is kept as 1 all the way to the publisher, which translates it into the API's automatic mode.
 */
function record_ttl_normalize(int $ttl): int
{
    if ($ttl === 1) {
        return 1;
    }

    return max(60, min(86400, $ttl));
}

/** Is this "Auto"? */
function record_ttl_is_auto($ttl): bool
{
    return (int)$ttl === 1;
}

/** How a TTL is shown to a person: 1 is not "one second", it is Auto. */
function record_ttl_text($ttl): string
{
    return record_ttl_is_auto($ttl) ? t('rec.ttl_auto') : (string)(int)$ttl;
}

function replace_records(int $domainId, array $records): void
{
    db_query('DELETE FROM records WHERE domain_id = ?', [$domainId]);
    $ttl     = default_ttl();
    $proxied = records_proxied_ready();

    foreach ($records as $index => $row) {
        $values = [
            $domainId,
            (string)($row['sub'] ?? ''),
            strtoupper((string)$row['type']),
            (string)$row['value'],
            // 1 means "Auto" for Cloudflare; anything else is a real number of seconds.
            isset($row['ttl']) ? record_ttl_normalize((int)$row['ttl']) : $ttl,
        ];

        if ($proxied) {
            db_query(
                'INSERT INTO records (domain_id, sub, type, value, ttl, proxied, position, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                array_merge($values, [empty($row['proxied']) ? 0 : 1, $index + 1])
            );
            continue;
        }

        db_query(
            'INSERT INTO records (domain_id, sub, type, value, ttl, position, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            array_merge($values, [$index + 1])
        );
    }
}

/** The full name a record belongs to: "" gives the registered name, "www" gives www.name. */
function record_fqdn(array $record, string $domainName): string
{
    $sub = (string)($record['sub'] ?? '');

    return $sub === '' ? $domainName : $sub . '.' . $domainName;
}

/** One line of zone-file text per record. */
function record_zone_lines(array $records, string $domainName): array
{
    $lines = [];
    foreach ($records as $record) {
        $type  = strtoupper((string)$record['type']);
        $value = (string)$record['value'];
        if ($type === 'TXT') {
            $value = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        } elseif ($type === 'CNAME') {
            $value = rtrim($value, '.') . '.';
        }
        $lines[] = str_pad(record_fqdn($record, $domainName) . '.', 34) . ' IN '
            . str_pad($type, 6) . ' ' . $value;
    }

    return $lines;
}

/**
 * Reads the record editor of a form.
 *
 * Existing rows:  row_sub[]  row_type[]  row_value[]  row_delete[]  (row_delete holds the row index)
 * New rows:       new_sub[]  new_type[]  new_value[]
 *
 * The request form only uses the "new" rows, restricts the types and refuses sub-names; the
 * editors of an active name use both and allow every type.
 *
 * @param array<int, string> $allowedTypes empty = every type
 * @return array{records: array<int, array{sub: string, type: string, value: string}>, errors: array<int, string>}
 */
function collect_records_from_post(
    string $domainName,
    string $zoneName = '',
    int $maxRecords = 0,
    array $allowedTypes = [],
    bool $apexOnly = false
): array {
    $records = [];
    $errors  = [];

    $deleted = [];
    if (isset($_POST['row_delete']) && is_array($_POST['row_delete'])) {
        foreach ($_POST['row_delete'] as $index) {
            $deleted[(int)$index] = true;
        }
    }

    $rowSub   = isset($_POST['row_sub']) && is_array($_POST['row_sub']) ? $_POST['row_sub'] : [];
    $rowType  = isset($_POST['row_type']) && is_array($_POST['row_type']) ? $_POST['row_type'] : [];
    $rowValue = isset($_POST['row_value']) && is_array($_POST['row_value']) ? $_POST['row_value'] : [];
    // Per-record TTL and proxy flag: an editor that offers them posts them per row, the others do not.
    $rowTtl     = isset($_POST['row_ttl']) && is_array($_POST['row_ttl']) ? $_POST['row_ttl'] : [];
    $rowProxied = isset($_POST['row_proxied']) && is_array($_POST['row_proxied']) ? $_POST['row_proxied'] : [];

    foreach ($rowSub as $index => $sub) {
        if (isset($deleted[(int)$index])) {
            continue;
        }
        $value = trim(post_scalar($rowValue[$index] ?? ''));
        $type  = strtoupper(trim(post_scalar($rowType[$index] ?? 'A')));
        if ($value === '') {
            $errors[] = t('rec.row_empty', (int)$index + 1, $type);
            continue;
        }

        $row = [
            'sub'   => normalize_record_sub(post_scalar($sub), $domainName),
            'type'  => $type,
            'value' => $value,
        ];

        $ttl = trim(post_scalar($rowTtl[$index] ?? ''));
        if ($ttl !== '' && (ctype_digit($ttl) || strcasecmp($ttl, 'auto') === 0)) {
            // "auto" (or 1) is Cloudflare's automatic TTL; anything else must be a sane number of seconds.
            $auto = strcasecmp($ttl, 'auto') === 0 || (int)$ttl === 1;
            if (!$auto && ((int)$ttl < 60 || (int)$ttl > 86400)) {
                $errors[] = t('rec.ttl_range', (int)$index + 1);
                continue;
            }
            $row['ttl'] = $auto ? 1 : (int)$ttl;
        }
        if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
            $row['proxied'] = empty($rowProxied[$index]) ? 0 : 1;
        }

        $records[] = $row;
    }

    $newSub   = isset($_POST['new_sub']) && is_array($_POST['new_sub']) ? $_POST['new_sub'] : [];
    $newType  = isset($_POST['new_type']) && is_array($_POST['new_type']) ? $_POST['new_type'] : [];
    $newValue = isset($_POST['new_value']) && is_array($_POST['new_value']) ? $_POST['new_value'] : [];

    foreach ($newValue as $index => $value) {
        $value = trim(post_scalar($value));
        $sub   = normalize_record_sub(post_scalar($newSub[$index] ?? ''), $domainName);
        $type  = strtoupper(trim(post_scalar($newType[$index] ?? 'A')));
        if ($value === '' && $sub === '') {
            continue;
        }
        if ($value === '') {
            $errors[] = t('rec.new_row_empty', (int)$index + 1, $type);
            continue;
        }
        $records[] = ['sub' => $sub, 'type' => $type, 'value' => $value];
    }

    /* ---- validation ---- */

    $validated = validate_record_set($records, $domainName, $zoneName, $maxRecords, $allowedTypes, $apexOnly);
    foreach ($validated['errors'] as $error) {
        $errors[] = $error;
    }

    return ['records' => $validated['records'], 'errors' => array_values(array_unique($errors))];
}

/**
 * Validates and normalises a complete record set.
 *
 * Used by the forms (through collect_records_from_post) and again when a queued change is
 * approved, so a proposal that was made before the rules changed cannot slip through.
 *
 * @param array<int, array<string, mixed>> $records
 * @param array<int, string>               $allowedTypes empty = every type; the request form passes
 *                                                      A, AAAA and CNAME (TXT only after approval)
 * @param bool                             $apexOnly     the request form only accepts records of
 *                                                      the registered name itself
 * @return array{records: array<int, array{sub: string, type: string, value: string}>, errors: array<int, string>}
 */
function validate_record_set(
    array $records,
    string $domainName,
    string $zoneName = '',
    int $maxRecords = 0,
    array $allowedTypes = [],
    bool $apexOnly = false
): array {
    if ($maxRecords <= 0) {
        $maxRecords = max_records_per_domain();
    }

    $clean     = [];
    $errors    = [];
    $seen      = [];
    $cnamePer  = [];

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $sub   = normalize_record_sub(post_scalar($record['sub'] ?? ''), $domainName);
        $type  = strtoupper(trim(post_scalar($record['type'] ?? '')));
        $value = trim(post_scalar($record['value'] ?? ''));

        if ($apexOnly && $sub !== '') {
            $errors[] = t('rec.subnames_later', $sub . '.' . $domainName);
            continue;
        }
        if ($allowedTypes !== [] && !in_array($type, $allowedTypes, true)) {
            $errors[] = $type === 'TXT'
                ? t('rec.txt_later')
                : t('rec.type_not_here', $type, implode(', ', $allowedTypes));
            continue;
        }

        $error = record_validation_error($sub, $type, $value, $domainName);
        if ($error !== '') {
            $errors[] = $error;
            continue;
        }

        $value = normalize_record_value($type, $value);
        $key   = $sub . '|' . $type . '|' . strtolower($value);
        if (isset($seen[$key])) {
            $errors[] = t('rec.duplicate', ($sub === '' ? $domainName : $sub . '.' . $domainName), $type, $value);
            continue;
        }
        $seen[$key] = true;

        if ($type === 'CNAME') {
            $cnamePer[$sub] = ($cnamePer[$sub] ?? 0) + 1;
        }
        $clean[] = ['sub' => $sub, 'type' => $type, 'value' => $value];
    }

    // A name can hold only one CNAME, and a CNAME must be the only record of its name.
    foreach ($cnamePer as $sub => $count) {
        $owner = $sub === '' ? $domainName : $sub . '.' . $domainName;
        if ($count > 1) {
            $errors[] = t('rec.cname_one_only', $owner, $count);
        }
    }
    foreach ($clean as $record) {
        $sub = (string)$record['sub'];
        if (isset($cnamePer[$sub]) && (string)$record['type'] !== 'CNAME') {
            $errors[] = t('rec.cname_exclusive', ($sub === '' ? $domainName : $sub . '.' . $domainName));
        }
    }

    // The registered name itself needs at least one record: an address or a CNAME.
    $apexRecord = false;
    $apexTypes  = $allowedTypes === [] ? ['A', 'AAAA', 'CNAME'] : array_values(array_intersect($allowedTypes, ['A', 'AAAA', 'CNAME', 'MX', 'CAA']));
    foreach ($clean as $record) {
        if ((string)$record['sub'] === '' && in_array((string)$record['type'], $apexTypes, true)) {
            $apexRecord = true;
        }
    }
    if (!$apexRecord) {
        $errors[] = $apexTypes === ['CNAME']
            ? t('rec.need_apex_cname')
            : t('rec.need_apex_record');
    }

    // Sub-names may be switched off per zone.
    if ($zoneName !== '' && !zone_allows_subdomains($zoneName)) {
        foreach ($clean as $record) {
            if ((string)$record['sub'] !== '') {
                $errors[] = t('rec.subnames_zone_off', $zoneName, $domainName);
                break;
            }
        }
    }

    if (count($clean) > $maxRecords) {
        $errors[] = t('rec.too_many', $maxRecords, count($clean));
    }

    return ['records' => $clean, 'errors' => array_values(array_unique($errors))];
}

/** Reads a submitted value as a string; arrays and other hostile shapes become an empty string. */
function post_scalar($value): string
{
    if (is_scalar($value) || $value === null) {
        return (string)$value;
    }

    return '';
}

/**
 * The rows of a record editor exactly as they were submitted, without validation.
 * Used to render the form again after a validation error so nothing is lost.
 *
 * @return array<int, array{sub: string, type: string, value: string}>
 */
function raw_records_from_post(): array
{
    $records = [];

    $deleted = [];
    if (isset($_POST['row_delete']) && is_array($_POST['row_delete'])) {
        foreach ($_POST['row_delete'] as $index) {
            $deleted[(int)$index] = true;
        }
    }

    $rowSub   = isset($_POST['row_sub']) && is_array($_POST['row_sub']) ? $_POST['row_sub'] : [];
    $rowType  = isset($_POST['row_type']) && is_array($_POST['row_type']) ? $_POST['row_type'] : [];
    $rowValue = isset($_POST['row_value']) && is_array($_POST['row_value']) ? $_POST['row_value'] : [];

    foreach ($rowSub as $index => $sub) {
        if (isset($deleted[(int)$index])) {
            continue;
        }
        $records[] = [
            'sub'   => normalize_record_sub(post_scalar($sub)),
            'type'  => strtoupper(trim(post_scalar($rowType[$index] ?? 'A'))),
            'value' => trim(post_scalar($rowValue[$index] ?? '')),
        ];
    }

    $newSub   = isset($_POST['new_sub']) && is_array($_POST['new_sub']) ? $_POST['new_sub'] : [];
    $newType  = isset($_POST['new_type']) && is_array($_POST['new_type']) ? $_POST['new_type'] : [];
    $newValue = isset($_POST['new_value']) && is_array($_POST['new_value']) ? $_POST['new_value'] : [];

    foreach ($newValue as $index => $value) {
        $value = trim(post_scalar($value));
        $sub   = normalize_record_sub(post_scalar($newSub[$index] ?? ''));
        if ($value === '' && $sub === '') {
            continue;
        }
        $records[] = [
            'sub'   => $sub,
            'type'  => strtoupper(trim(post_scalar($newType[$index] ?? 'A'))),
            'value' => $value,
        ];
    }

    return $records;
}

/** Compares two record sets: returns added, removed and unchanged records. */
function records_diff(array $live, array $proposed): array
{
    $key = static function (array $record): string {
        return (string)($record['sub'] ?? '') . '|' . strtoupper((string)$record['type'])
            . '|' . strtolower((string)$record['value']);
    };

    $liveKeys = [];
    foreach ($live as $record) {
        $liveKeys[$key($record)] = $record;
    }
    $proposedKeys = [];
    foreach ($proposed as $record) {
        $proposedKeys[$key($record)] = $record;
    }

    $added = $removed = $kept = [];
    foreach ($proposedKeys as $k => $record) {
        if (isset($liveKeys[$k])) {
            $kept[] = $record;
        } else {
            $added[] = $record;
        }
    }
    foreach ($liveKeys as $k => $record) {
        if (!isset($proposedKeys[$k])) {
            $removed[] = $record;
        }
    }

    return ['added' => $added, 'removed' => $removed, 'kept' => $kept];
}

/* ------------------------------------------------------------------ *
 * Change requests proposed by the applicants                          *
 * ------------------------------------------------------------------ */

function change_snapshot_encode(array $records): string
{
    $out = [];
    foreach ($records as $record) {
        $out[] = [
            'sub'   => (string)($record['sub'] ?? ''),
            'type'  => strtoupper((string)$record['type']),
            'value' => (string)$record['value'],
        ];
    }

    return is_string($json = json_encode(
        $out,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    )) ? $json : '';
}

/**
 * Decodes a snapshot for display. Returns null when the stored JSON is damaged, so callers can
 * tell "damaged" apart from "the applicant proposed an empty record set".
 */
function change_snapshot_decode_strict(?string $json): ?array
{
    if ($json === null || trim($json) === '') {
        return null;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }

    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row) || !isset($row['type'], $row['value'])) {
            return null;
        }
        $out[] = [
            'sub'   => (string)($row['sub'] ?? ''),
            'type'  => strtoupper((string)$row['type']),
            'value' => (string)$row['value'],
        ];
    }

    return $out;
}

function change_snapshot_decode(?string $json): array
{
    $decoded = change_snapshot_decode_strict($json);

    return $decoded === null ? [] : $decoded;
}

function change_request_by_id(int $id): ?array
{
    return db_one('SELECT * FROM change_requests WHERE id = ?', [$id]);
}

function pending_change_for_domain(int $domainId): ?array
{
    return db_one(
        "SELECT * FROM change_requests WHERE domain_id = ? AND status = 'pending'
         ORDER BY id DESC LIMIT 1",
        [$domainId]
    );
}

/** Applies a proposed record set to the live records and marks the request approved. */
function apply_change_request(array $change, string $adminUsername, string $adminNote = ''): void
{
    $changeId = (int)$change['id'];
    $domainId = (int)$change['domain_id'];

    if ((string)$change['status'] !== 'pending') {
        throw new RuntimeException('This change request has already been decided.');
    }

    $domain = domain_by_id($domainId);
    if ($domain === null) {
        throw new RuntimeException('The domain of this change request no longer exists.');
    }
    if (!in_array((string)$domain['status'], ['active', 'pending'], true)) {
        throw new RuntimeException('The records of a ' . status_text((string)$domain['status'])
            . ' name cannot be changed.');
    }

    $proposed = change_snapshot_decode_strict((string)$change['snapshot']);
    if ($proposed === null) {
        throw new RuntimeException('The stored change is damaged and cannot be applied.');
    }
    if ($proposed === []) {
        throw new RuntimeException('The stored change contains no records and was not applied.');
    }

    // The rules may have changed since the applicant submitted the proposal, so validate again.
    $validated = validate_record_set($proposed, (string)$domain['name'], (string)$domain['zone']);
    if ($validated['errors'] !== []) {
        throw new RuntimeException('The change does not pass validation any more: '
            . implode(' ', array_slice($validated['errors'], 0, 3)));
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        replace_records($domainId, $validated['records']);
        $stmt = db_query(
            "UPDATE change_requests SET status = 'approved', admin_note = ?, decided_at = NOW(),
                decided_by = ? WHERE id = ? AND status = 'pending'",
            [$adminNote === '' ? null : $adminNote, $adminUsername, $changeId]
        );
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('That change request was decided in the meantime; nothing was changed.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reject_change_request(array $change, string $adminUsername, string $adminNote = ''): void
{
    $stmt = db_query(
        "UPDATE change_requests SET status = 'rejected', admin_note = ?, decided_at = NOW(), decided_by = ?
         WHERE id = ? AND status = 'pending'",
        [$adminNote === '' ? null : $adminNote, $adminUsername, (int)$change['id']]
    );
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('That change request was decided in the meantime; nothing was changed.');
    }
}

/** Drops the queued changes of a domain (used when a name is deleted, recycled or suspended). */
function drop_pending_changes(int $domainId): int
{
    return db_query("DELETE FROM change_requests WHERE domain_id = ? AND status = 'pending'", [$domainId])
        ->rowCount();
}

function count_pending_changes(): int
{
    return (int)db_value("SELECT COUNT(*) FROM change_requests WHERE status = 'pending'", [], 0);
}

/* ------------------------------------------------------------------ *
 * Applicant self-service ("manage records") throttling                *
 * ------------------------------------------------------------------ */

function portal_key(string $code, string $email): string
{
    return 'portal:' . substr(hash('sha256', strtoupper($code) . '|' . strtolower($email)), 0, 16);
}

function portal_throttled(string $key): bool
{
    $max    = max(3, (int)(config('security', [])['login_max_attempts'] ?? 8));
    $cutoff = login_window_cutoff();

    $count = (int)db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ? AND (ip = ? OR username = ?)',
        [$cutoff, client_ip(), $key],
        0
    );

    return $count >= ($max * 3);
}

function record_portal_attempt(string $key, bool $success): void
{
    db_query(
        'INSERT INTO login_attempts (username, ip, success, created_at) VALUES (?, ?, ?, NOW())',
        [substr($key, 0, 64), client_ip(), $success ? 1 : 0]
    );
    if ($success) {
        db_query('DELETE FROM login_attempts WHERE success = 0 AND username = ?', [$key]);
    }

    // Keep the table small even on sites where nobody signs in to the back office.
    if (random_int(1, 20) === 1) {
        db_query('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 172800)]);
    }
}

/** Cuts text to a number of characters, not bytes (used for VARCHAR limits). */
function limit_chars(string $text, int $max): string
{
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') : $text;
    }

    return strlen($text) > $max ? substr($text, 0, $max) : $text;
}

function count_domains_for_email(string $email, ?string $zone = null): int
{
    if ($zone === null) {
        return (int)db_value(
            "SELECT COUNT(*) FROM domains WHERE registrant_email = ? AND status IN ('pending', 'active')",
            [$email],
            0
        );
    }

    return (int)db_value(
        "SELECT COUNT(*) FROM domains WHERE registrant_email = ? AND zone = ? AND status IN ('pending', 'active')",
        [$email, $zone],
        0
    );
}

function count_requests_from_ip(string $ip, int $hours = 24): int
{
    $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));

    return (int)db_value(
        "SELECT COUNT(*) FROM domains WHERE created_ip = ? AND created_at > ? AND status <> 'deleted'",
        [$ip, $cutoff],
        0
    );
}

/** The real count, whatever the override says. */

/* ---------------------------------------------------------------- home page counters */

/**
 * Per-suffix display overrides set in the back office (admin/home-stats.php).
 *
 * Stored as JSON in the setting "home_zone_shown", for example {"pvf.cc":900,"nl0.net":120}.
 * An empty or 0 value means "use the real count for that suffix".
 */
function home_zone_shown_map(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = [];
    $raw   = (string)setting('home_zone_shown', '');
    if ($raw !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            foreach ($parsed as $zone => $n) {
                $n = (int)$n;
                if ($n > 0) {
                    $cache[(string)$zone] = $n;
                }
            }
        }
    }

    return $cache;
}

/** The real per-suffix counts of active names. */
function names_zone_real(): array
{
    $out = [];
    foreach (db_all("SELECT zone, COUNT(*) AS n FROM domains WHERE status = 'active' GROUP BY zone") as $row) {
        $out[(string)$row['zone']] = (int)$row['n'];
    }

    return $out;
}

/**
 * The per-suffix numbers the home page prints: the override where one is set, the real count
 * everywhere else. Always covers every suffix the registry has, so a suffix with no names yet
 * still appears once the operator gives it a number.
 */
function home_zone_rows(): array
{
    $real = names_zone_real();
    $map  = home_zone_shown_map();
    $rows = [];

    foreach (array_keys($real) as $zone) {
        $rows[$zone] = $real[$zone];
    }
    foreach ($map as $zone => $n) {
        $rows[$zone] = $n;
    }
    arsort($rows);

    $out = [];
    foreach ($rows as $zone => $n) {
        $out[] = ['zone' => (string)$zone, 'n' => (int)$n];
    }

    return $out;
}

/**
 * The one figure the home page advertises as "registered".
 *
 * Sum of the per-suffix numbers (overrides included), unless the operator also filled the single
 * total field, which then wins so the headline can differ from the sum on purpose.
 */
function names_active_display(): int
{
    $total = (int)setting('home_names_shown', '0');
    if ($total > 0) {
        return $total;
    }
    $sum = 0;
    foreach (home_zone_rows() as $row) {
        $sum += (int)$row['n'];
    }

    return $sum;
}

/** The real count, whatever the overrides say. */
function names_active_real(): int
{
    return (int)db_value("SELECT COUNT(*) FROM domains WHERE status = 'active'", [], 0);
}
/* ---------------------------------------------------------------- demo names */

/**
 * Create $count random names on one suffix, each with its own account.
 *
 * Batched inserts (100 rows per statement) so 9000 names finish in a few seconds. Everything is
 * tagged with admin_note = 'SEED-DEMO', which is how demo_bulk_clear() finds it again; the tag is
 * never shown in the interface, so the names look ordinary everywhere.
 *
 * Records point at example.org (RFC 2606) and 192.0.2.x (TEST-NET-1): reserved addresses that can
 * never reach a real host, so nothing here can be used as a redirect farm even by accident.
 */
function demo_batch_create(string $zone, int $limit): array
{
    $limit = max(1, min(1000, $limit));
    $out   = ['domains' => 0, 'users' => 0, 'skipped' => 0];

    // Readable seeds instead of random characters: given names, surnames, English nicknames,
    // Chinese-style handles and a short list of neutral words. Every list is plain UTF-8 text,
    // so nothing here can produce mojibake.
    $given = ['alice','bob','carol','david','emma','frank','grace','henry','irene','jack',
              'karen','liam','mona','nancy','oscar','peter','queen','robert','susan','tom',
              'ursula','victor','wendy','xavier','yvonne','zoe','adam','bella','chris','diana'];
    $family = ['smith','johnson','williams','brown','jones','garcia','miller','davis','wilson','moore',
               'taylor','anderson','thomas','jackson','white','harris','martin','thompson','young','lee',
               'walker','hall','allen','wright','king','green','baker','adams','nelson','hill'];
    $nickz = ['晴天','小满','拾光','木木','阿七','鹿鸣','子衿','白鹭','青柠','星野',
              'Sora','Rin','Yuki','Hana','Ren','Mio','Kai','Nao','Haru','Aoi',
              'Alex','Sam','Jamie','Robin','Charlie','Dana','Casey','Jordan','Riley','Avery'];
    $words = ['river','stone','bright','cloud','garden','harbor','forest','silver','meadow','summit',
              'lantern','compass','willow','pioneer','aurora','cobalt','ember','horizon','nimbus','orchid',
              'pebble','quartz','raven','saffron','thistle','violet','walnut','yonder','zephyr','amber'];

    $made  = 0;
    $tries = 0;
    $max   = $limit * 8;

    while ($made < $limit && $tries < $max) {
        $tries++;
        $kind = mt_rand(1, 5);

        if ($kind === 1) {
            $first = $given[mt_rand(0, count($given) - 1)];
            $label = $first . $family[mt_rand(0, count($family) - 1)];
            $nick  = ucfirst($first) . ' ' . ucwords($family[mt_rand(0, count($family) - 1)]);
        } elseif ($kind === 2) {
            $first = $given[mt_rand(0, count($given) - 1)];
            $label = $first . '-' . $family[mt_rand(0, count($family) - 1)];
            $nick  = ucfirst($first) . ' ' . strtoupper(substr($family[mt_rand(0, count($family) - 1)], 0, 1)) . '.';
        } elseif ($kind === 3) {
            $w     = $words[mt_rand(0, count($words) - 1)];
            $label = $w . '-' . $words[mt_rand(0, count($words) - 1)];
            $nick  = ucfirst($w) . ' ' . ucfirst($words[mt_rand(0, count($words) - 1)]);
        } elseif ($kind === 4) {
            $w     = $words[mt_rand(0, count($words) - 1)];
            $label = $w . mt_rand(1, 99);
            $nick  = $nickz[mt_rand(0, count($nickz) - 1)];
        } else {
            $label = $words[mt_rand(0, count($words) - 1)];
            $nick  = $nickz[mt_rand(0, count($nickz) - 1)];
        }

        $label = preg_replace('/[^a-z0-9-]/', '', strtolower($label)) ?? '';
        if ($label === '' || $label[0] === '-' || strlen($label) > 40) {
            continue;
        }

        $fqdn = $label . '.' . $zone;
        // A readable local part, on a disposable-mail domain: plausible to look at, owned by
        // nobody. Real public mailboxes (gmail, qq, 163 ...) are deliberately NOT used - those
        // belong to real people and registrant_email is an accountability field.
        $disp = ['mailinator.com','yopmail.com','guerrillamail.com','temp-mail.org','10minutemail.com',
                 'mail.tm','getnada.com','mohmal.com','maildrop.cc','sharklasers.com','spam4.me',
                 'trashmail.com','example.com','example.org','example.net'];
        $localPart = $label;
        if (strpos($label, '-') !== false) {
            $localPart = str_replace('-', '.', $label);
        }
        $mail = $localPart . '@' . $disp[mt_rand(0, count($disp) - 1)];

        try {
            $u = db_query(
                'INSERT IGNORE INTO users (username, username_lower, email, email_lower, password_hash,
                    has_password, status, language, created_at, created_ip)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW(), ?)',
                [$label, strtolower($label), $mail, strtolower($mail),
                 password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT),
                 'active', 'en', '127.0.0.1']
            );
            $d = db_query(
                'INSERT IGNORE INTO domains (name, label, zone, language, reserved, proxied, delegated,
                    status, request_code, provider_owned, registrant_name, registrant_email,
                    justification, admin_note, created_at, updated_at, decided_at, decided_by, created_ip)
                 VALUES (?, ?, ?, ?, 0, 0, 0,
                    ?, ?, 0, ?, ?,
                    ?, ?, NOW(), NOW(), NOW(), ?, ?)',
                [$fqdn, $label, $zone, 'en',
                 'active',
                 'S' . strtoupper(substr(sha1('seed|' . $fqdn), 0, 10)),
                 $nick, $mail,
                 'Imported name', 'SEED-DEMO',
                 'seed', '127.0.0.1']
            );
            if ($d === 0) {
                $out['skipped']++;
                continue;
            }
            $domainId = (int)db()->lastInsertId();
            db_query(
                'INSERT IGNORE INTO records (domain_id, sub, type, value, ttl, position, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()), (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$domainId, '', 'CNAME', 'example.org', 3600, 1,
                 $domainId, 'www', 'CNAME', $fqdn, 3600, 2]
            );
            if ($u > 0) {
                $out['users']++;
                try {
                    // Seeded accounts must never be mailed: the addresses are disposable domains.
                    db_query('UPDATE users SET mail_off = 1 WHERE username = ?', [$label]);
                } catch (Throwable $e) {
                    // mail_off may be missing on an old schema; harmless
                }
            }
            $out['domains']++;
            $made++;
        } catch (Throwable $e) {
            $out['skipped']++;
        }
    }

    return $out;
}

/** Remove everything demo_bulk_create() made. */
function demo_bulk_clear(): array
{
    $out = ['domains' => 0, 'users' => 0];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ids = db_all("SELECT id FROM domains WHERE admin_note = 'SEED-DEMO'");
        foreach ($ids as $row) {
            db_query('DELETE FROM records WHERE domain_id = ?', [(int)$row['id']]);
            db_query('DELETE FROM domains WHERE id = ?', [(int)$row['id']]);
            $out['domains']++;
        }
        $users = db_all("SELECT id FROM users WHERE email LIKE '%@mailinator.com' OR email LIKE '%@yopmail.com'
            OR email LIKE '%@guerrillamail.com' OR email LIKE '%@temp-mail.org' OR email LIKE '%@10minutemail.com'
            OR email LIKE '%@mail.tm' OR email LIKE '%@getnada.com' OR email LIKE '%@mohmal.com'
            OR email LIKE '%@maildrop.cc' OR email LIKE '%@sharklasers.com' OR email LIKE '%@spam4.me'
            OR email LIKE '%@trashmail.com' OR email LIKE '%@example.com' OR email LIKE '%@example.org'
            OR email LIKE '%@example.net' OR email LIKE '%@example.invalid'");
        foreach ($users as $row) {
            db_query('DELETE FROM users WHERE id = ?', [(int)$row['id']]);
            $out['users']++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $out;
}
