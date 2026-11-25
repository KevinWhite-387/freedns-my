# 把二级域名指向 Cloudflare Pages / GitHub Pages / Vercel

> 适用：本站（freedns.my 注册局）发出的名字，例如 `alice.vc.ci`、`bob.kz.ci`。
> 结论先说：**能，用一条 CNAME 就行**，不需要在本站做任何特殊设置。

---

## 一、原理（30 秒看懂）

```
alice.vc.ci      ──CNAME──▶   my-project.pages.dev
（本站的记录）                 （Cloudflare Pages 给你的地址）

知乎、Vercel、GitHub Pages 都是这个套路：
  它们给你一个固定域名，你只需要把自己的域名"别名"过去。
```

本站的 `CNAME` 记录是**公开类型**（无需开发者权限 ✓），四家 DNS 服务商都支持 ✓
（Cloudflare ✓ Spaceship ✓ ANYNS ✓ DNSHE ✓）。

---

## 二、操作步骤（以 Cloudflare Pages 为例）

### ① 在 Cloudflare Pages 拿到地址
```
Cloudflare 后台 → Workers & Pages → 你的项目 → 部署详情
你会看到一个地址，形如： my-project.pages.dev
```

### ② 在本站控制台加一条 CNAME
```
登录 → 我的域名 → 选 alice.vc.ci → 管理记录 → 添加记录

  类型      CNAME
  主机记录   @          ← 根域（alice.vc.ci 本身就是目标）
  或者      www        ← 只让 www.alice.vc.ci 过去
  记录值     my-project.pages.dev
  TTL       300
```
保存后**即时生效**（等待 TTL 缓存，通常 1–5 分钟）。

### ③ 用 `dig` 确认
```bash
dig +short alice.vc.ci CNAME
# 期望：my-project.pages.dev.

dig +short www.alice.vc.ci
# 期望：my-project.pages.dev. 然后是被解析出的 IP
```

---

## 三、三个平台的具体写法

| 平台 | 它会给你 | 你在本站填 |
| --- | --- | --- |
| **Cloudflare Pages** | `my-project.pages.dev` | CNAME → `my-project.pages.dev` ✓ |
| **GitHub Pages** | `<用户>.github.io` | CNAME → `<用户>.github.io` ✓ |
| **Vercel** | `my-project.vercel.app` | CNAME → `my-project.vercel.app` ✓ |
| **Netlify** | `my-project.netlify.app` | CNAME → `my-project.netlify.app` ✓ |

### 各自的额外一步（平台侧，不是本站）

**Cloudflare Pages** ✓ 无需额外操作（CNAME 就行）
**GitHub Pages** ✗ 要在仓库 → Settings → Pages → **Custom domain** 里填 `alice.vc.ci` ✓
  → 它会在仓库里生成一个 `CNAME` 文件 ✓ 并**自动申请 HTTPS 证书** ✓（可能要几分钟 ✗）
**Vercel / Netlify** ✓ 在项目的 Domains 里添加 `alice.vc.ci` ✓ 然后按提示验证 ✓

---

## 四、⚠️ 三个必须知道的限制

### 1. 根域 CNAME 只有 Cloudflare 能"压平"
```
alice.vc.ci   CNAME   my-project.pages.dev     ← 严格说 DNS 规范不允许根域有 CNAME ✗
```
- **Cloudflare** 托管的区域会自动"压平"（flattening）✓ 能用 ✓
- **Spaceship / ANYNS / DNSHE** 通常**拒绝**根域 CNAME ✗
  → 解法：把目标放在 **`www`** 上 ✓（`www.alice.vc.ci` → Pages ✓✓）
  → 或者做 **A 记录**指向平台的 IP ✗（不推荐 ✗ IP 会变 ✓）

### 2. HTTPS 证书是平台签的，不是本站签的
本站（freedns.my 注册局）**不签发证书** ✗ → 证书由 Pages / GitHub / Vercel 通过 **Let's Encrypt** 自己签 ✓
→ 前提是**平台的验证能访问到你的域名** ✓ → 所以 CNAME **必须先生效** ✓ 再去平台点"验证" ✓✓

### 3. 平台侧"绑定自定义域名"和"本站 CNAME"要对应
```
本站:   alice.vc.ci → CNAME → my-project.pages.dev   ✓（必须先在）
平台:   my-project  → Custom domain → alice.vc.ci    ✓（然后再加）
```
**顺序反了** ✗ → 平台验证失败 ✗✓（因为那时 DNS 还没生效 ✓）

---

## 五、本站已经为此准备好的东西 ✓

| 能力 | 状态 |
| --- | --- |
| `CNAME` 记录（公开类型，免审） | ✓ 四家服务商都支持 |
| 通配符子域（`*`、`*.label`） | ✓ `valid_sub_label()` 支持 |
| 自动发布到 DNS 服务商 | ✓ 批准记录即推送（Cloudflare / Spaceship / ANYNS / DNSHE） |
| `AAAA` / `TXT`（验证用） | ✓ 都是公开类型 |
| 根域 CNAME 压平 | ⚠️ 仅当后缀托管在 **Cloudflare** |

---

## 六、要不要做"一键"？✗

真做成"一键创建 Pages 项目并绑定"需要 ✗：
```
① 用户把自己的 GitHub / Cloudflare 账号授权给本站（OAuth ✓ 像 vc.ci 那样）
② 本站调 Cloudflare API 建 Pages 项目 → 上传代码 → 绑定自定义域名
③ 但 Cloudflare Pages 的"自定义域名"要求域名必须在【同一个 Cloudflare 账号】
   ✗ 你的名字在注册局的账号里 ✗ 所以这条路走不通 ✗✗
```
⇒ **结论**：`CNAME 到 *.pages.dev` 是**唯一可行**且**最省事**的方式 ✓✓ ——
   本站不需要新功能 ✓ 用户自己两条操作（CNAME ＋ 平台加域名）就好 ✓

---

## 七、如果想让用户完全不用懂 DNS ✗

可以做（需要开发 ✓）：
```
后端调用 Cloudflare API:
  POST /accounts/{acc}/pages/projects  → 建项目
  POST /accounts/{acc}/pages/projects/{p}/domains  → 加自定义域名
    ↑ 这个接口只接受【本账号内的 zone】✗  → 对本站发出的名字无效 ✗
```
⇒ 换平台（Netlify / Vercel）也是同样的限制 ✗
⇒ **唯一例外**：如果本站把用户名字的**区域托管在用户自己的 Cloudflare 账号**里 ✗
   （即"自带 Cloudflare 账号"模式 ✓ 那要让用户填自己的 API token ✓）
   → 这在本站是**已支持**的 ✓（`zones.cf_token` 每个后缀独立令牌 ✓ 只是粒度是后缀不是用户 ✗）
