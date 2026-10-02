# freedns-my
Free second-level domains under freedns.my - human review, no fees, no expiry.
<!--
  freedns.my - README for the GitHub repository.
  Paste this file into the repository as README.md and replace YOURNAME/freedns-my with the real path.
  It is written for a PUBLIC repository that carries NO source code and NO licence (all rights reserved).
-->

# freedns.my — 免费二级域名 · free second-level domains

[**English**](#-english) &nbsp;·&nbsp; [**中文**](#-中文)

![GitHub Repo stars](https://img.shields.io/github/stars/YOURNAME/freedns-my?style=social)
![Website](https://img.shields.io/badge/website-freedns.my-blue)

> **免费、无广告、永不过期的二级域名。** 人工审核，支持 A / AAAA / CNAME / TXT 记录与子域名，通过后自动发布，控制台可随时自助修改。
>
> **Free, ad-free, non-expiring second-level domains.** Human review, A / AAAA / CNAME / TXT records and
> sub-names, published automatically once approved, with a console for later changes.

**网址 / Website:** <https://freedns.my/> &nbsp;·&nbsp; **交流群 / Group:** <https://t.me/freednsmy>

---

## 🇬🇧 English

### What is this?

`freedns.my` is a small, free domain registry. You can register a second-level domain such as
`yourname.freedns.my` and point it at an address you control — for a personal site, a small tool, a tunnel,
a test setup or to learn how DNS records work.

**This repository is the project page, not the source code.** See [About this repository](#about-this-repository).

### What you get

| | |
| --- | --- |
| **Price** | Free. No registration fee, no renewal fee, no advertising on your name |
| **Expiry** | None — free names renew themselves for as long as you use them |
| **Record types** | `A`, `AAAA`, `CNAME`, `TXT` (no `MX`, see [limits](#honest-limits)) |
| **Sub-names** | Yes, for example `www.yourname` or `api.yourname` |
| **Records per name** | Several — around 20 by default (the operator can raise it) |
| **Review** | By a human, usually within a few days |
| **Later changes** | Self-service: your request code plus your e-mail address, or a console account |
| **Console** | E-mail + password, or **sign in with GitHub**; password reset by mail, CSV export, account deletion |
| **Publishing** | Approved records are pushed to Cloudflare automatically; you choose *proxied* (orange cloud) or *DNS only* |
| **Privacy** | No personal data published: the WHOIS lookup shows no name or e-mail address, and addresses in the public list are masked (`203.****.***.10`) |
| **Pages** | Plain text, no JavaScript, no trackers; English and Chinese; dark mode |
| **IPv6** | Fully supported (`AAAA`) |

### A good fit for

* Personal sites, blogs, portfolios, small tools and APIs
* Tunnels and self-hosting that need a stable name (frp, Cloudflare Tunnel, …)
* Learning how DNS records work — you can change them yourself
* Projects that need a name which stays free and does not expire
* Sub-names such as `www.yourname`

### ⚠️ Not a good fit for — please read

* **Bringing your own name servers.** The registry answers for the names itself; name-server delegation is
  not offered (the zones are not in the Public Suffix List yet). You cannot use your own NS.
* **E-mail.** There is no `MX` record support, so these names cannot receive mail.
* **Addresses that change often.** The registry publishes what you enter, so a dynamic home connection will
  break the name whenever the address changes.
* **Private addresses.** Only publicly routable addresses are accepted; private, loopback, link-local and
  CGNAT ranges are refused by the form.
* **Wildcards.** `*.yourname` is not supported.
* **Guarantees.** This is a free project run by a person, reviewed by hand. There is no SLA and no support
  contract.
* **Automatic TLS certificates.** Let's Encrypt and friends have limited support for domains outside the
  Public Suffix List; please check before you rely on it.

### How to apply

1. Open <https://freedns.my/request.php> and pick a suffix.
2. Choose a name and enter the IPv4 / IPv6 address (or a CNAME target) that should serve it, plus a short
   description of what the name is for.
3. Submit. A human reviews the request, usually within a few days, and you receive the decision by e-mail
   together with your request code.

Once the name is active you can add sub-names and further records yourself under
<https://freedns.my/manage.php> (request code + e-mail) or in the console at
<https://freedns.my/account/>.

### ⭐ Star this repository, get an extra allowance

Every account has a fixed registration allowance. **Starring this repository raises it by one name** — a
small thank-you for helping other people find the registry:

1. Sign in at <https://freedns.my/account/> and link your GitHub account in your profile.
2. Press **Star** on this repository.
3. Open the console, press *I starred it – check now*, and the extra allowance is added immediately.

The check compares your linked GitHub account with the stargazers of this repository, so the star really has
to come from your own account. One bonus per account; taking the star back later does not remove it.

### Useful links

| | |
| --- | --- |
| Home page | <https://freedns.my/> |
| Apply for a name | <https://freedns.my/request.php> |
| Registered names | <https://freedns.my/domains.php> |
| Check a name’s state | <https://freedns.my/status.php> |
| Change records | <https://freedns.my/manage.php> |
| Console (sign in / register) | <https://freedns.my/account/> |
| WHOIS lookup | <https://freedns.my/whois.php> |
| Policy and documentation | <https://freedns.my/doc.php> |
| Telegram group | <https://t.me/freednsmy> |

### About this repository

* `freedns.my` is a private project. **This repository does not carry its source code** and is not an open
  source release; it is the public page of the registry (what it is, who it is for, how to apply).
* No licence is granted: all rights reserved.
* Stars and questions are welcome — please use the [group](https://t.me/freednsmy) for questions, so that
  answers are visible to everybody.
* The registry does not publish personal data, and neither does this repository.

---

## 🇨🇳 中文

### 这是什么

`freedns.my` 是一个小型的**免费域名注册局**。你可以注册 `你的名字.freedns.my` 这样的二级域名，把它指向你自己
控制的地址 —— 用于个人网站、小工具、内网穿透、测试环境，或者单纯学习 DNS 记录怎么配。

**本仓库是这个项目的公开页面，不是源代码仓库**，详见 [关于本仓库](#关于本仓库)。

### 提供什么

| | |
| --- | --- |
| **价格** | 免费。没有注册费、没有续费、你的域名上也不会有广告 |
| **有效期** | 不过期 —— 只要你在用，免费域名自动续期 |
| **记录类型** | `A`、`AAAA`、`CNAME`、`TXT`（**不含 `MX`**，见 [不适合的情况](#不适合的情况请先阅读)） |
| **子域名** | 支持，例如 `www.你的名字`、`api.你的名字` |
| **记录条数** | 每个域名若干条，默认约 20 条（运营者可调高） |
| **审核** | 人工审核，通常几天内出结果 |
| **后续修改** | 自助：申请编号 + 邮箱，或注册控制台账号 |
| **控制台** | 邮箱 + 密码，或**用 GitHub 一键登录**；支持邮件重置密码、CSV 导出、删除账号 |
| **发布** | 审核通过后记录自动发布到 Cloudflare；代理（橙云）还是仅解析（DNS only）由你选择 |
| **隐私** | **不公开任何个人信息**：WHOIS 不显示姓名与邮箱，公开列表里的 IP 按 `203.****.***.10` 打码 |
| **页面** | 纯文本、无 JavaScript、无追踪脚本；中英双语；深色模式 |
| **IPv6** | 完整支持（`AAAA`） |

### 适合谁

* 个人主页、博客、作品集、小工具与 API
* 需要固定名字的内网穿透 / 自建服务（frp、Cloudflare Tunnel 等）
* 想学习 DNS 记录配置 —— 记录可以自己改
* 需要一个**长期免费、不会到期**的名字
* 需要 `www.你的名字` 这类子域名

### ⚠️ 不适合的情况（请先阅读）

* **自带域名服务器（NS）**：记录由注册局自己应答，**不提供 NS 委派**（这些后缀尚未进入公共后缀列表），
  因此无法使用你自己的 NS；
* **收发邮件**：不支持 `MX` 记录，这些域名**不能用来收邮件**；
* **地址经常变化**：注册局按你填写的地址应答，动态家宽地址一变，域名就失效；
* **私有地址**：只接受公网可路由地址，私有段、环回、链路本地、CGNAT 等会被表单直接拒绝；
* **泛解析**：不支持 `*.你的名字`；
* **服务承诺**：这是个人运营的免费项目、人工审核，**没有 SLA**，也没有商业支持；
* **自动签发 HTTPS 证书**：Let's Encrypt 等对公共后缀列表之外的域名支持有限，依赖前请先确认。

### 如何申请

1. 打开 <https://freedns.my/request.php>，选择一个后缀；
2. 填域名、填写要指向的 IPv4 / IPv6 地址（或 CNAME 目标），并写一句用途说明；
3. 提交。人工审核通常几天内完成，结果和**申请编号**会发到你的邮箱。

域名生效后，你可以自己添加子域名和更多记录：<https://freedns.my/manage.php>（申请编号 + 邮箱），
或登录控制台 <https://freedns.my/account/> 统一管理。

### ⭐ 给本仓库点 Star，获得额外配额

每个账号有固定的注册配额。**给本仓库点一个 Star，配额 +1** —— 算是帮我们让更多人发现这个注册局的一点感谢：

1. 登录 <https://freedns.my/account/>，在个人资料里**绑定 GitHub 账号**；
2. 在本仓库页面点右上角的 **Star**；
3. 回到控制台，按 **「我已点 Star，立即核验」**，配额立刻到账。

核验方式是拿你绑定的 GitHub 账号去比对本仓库的 Star 列表，所以这个 Star 必须来自你自己的账号。
每个账号限领一次；之后取消 Star 不会收回。

### 常用链接

| | |
| --- | --- |
| 首页 | <https://freedns.my/> |
| 申请域名 | <https://freedns.my/request.php> |
| 已注册域名列表 | <https://freedns.my/domains.php> |
| 查询域名状态 | <https://freedns.my/status.php> |
| 修改记录 | <https://freedns.my/manage.php> |
| 控制台（登录 / 注册） | <https://freedns.my/account/> |
| WHOIS 查询 | <https://freedns.my/whois.php> |
| 政策与文档 | <https://freedns.my/doc.php> |
| 交流群（Telegram） | <https://t.me/freednsmy> |

### 关于本仓库

* `freedns.my` 是个人项目，**本仓库不包含源代码**，也不是开源发布；它是注册局的公开页面（介绍、适用人群、申请方式）；
* **不授予任何许可**（保留所有权利 / all rights reserved）；
* 欢迎点 Star，也欢迎提问 —— 提问请到 [交流群](https://t.me/freednsmy)，这样答案对所有人可见；
* 注册局不公开个人信息，本仓库同样不会。

---

<p align="center">
  <sub>freedns.my — free second-level domains · 免费二级域名 · <a href="https://t.me/freednsmy">Telegram</a></sub>
</p>
