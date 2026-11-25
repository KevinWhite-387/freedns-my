# GitHub 申请 → 人工发码 → GitHub 回帖（操作手册）

## 一、仓库要放的文件

```
.github/ISSUE_TEMPLATE/invite-request.yml     ← 申请表单（含隐私同意勾选 ✓）
```

放好后，别人在你仓库点 **New issue** 会看到 **Invitation request** 模板 ✓
没有这个文件就看不到模板 ✗

## 二、你的处理流程（每次 3 分钟）

```
① GitHub 收到申请 issue
   ↓ 看三件事：邮箱是否真实 ✓ 用途是否合理 ✓ 两个勾选都同意了吗 ✓

② 后台生成码
   https://freedns.my/admin/invites.php
   → 备注填 GitHub 用户名（例：KevinWhite-387）
   → 生成 1 个 → 复制 FD-XXXX-XXXX

③ 人工发邮件到 issue 里填的邮箱
   标题：freedns.my 邀请码 / Your freedns.my invite code
   正文：
   ────────────────────────────────
   Hi，

   你的注册申请已通过。邀请码（只能用一次）：

       FD-XXXX-XXXX

   注册地址：https://freedns.my/account/register.php
   邀请码填在表单的「邀请码」一栏。

   收到后请尽快注册，注册完这个码就失效了。
   ────────────────────────────────

④ 回 GitHub 那条 issue（模板见下）

⑤ 对方注册后，后台 「Invite codes → 谁用了哪个码」能看到对应账号 ✓
```

## 三、GitHub 回帖模板

**通过：**
```
Approved ✅

A single-use invitation code has been sent to the address you provided.
Please check that inbox (and the spam folder) and register here:

https://freedns.my/account/register.php

The code works once; once the account exists it is spent.
```

**拒绝：**
```
Thanks for the request, but we cannot approve it right now.

Reason: <一句话理由>

You are welcome to open a new request later.
```

**邮箱不可用：**
```
Your request is approved, but the mail to <address> bounced.

Please reply here with a working address and we will send the code again.
```

## 四、必须做的两件防护

| 事 | 为什么 |
| --- | --- |
| 每个码 **1 次** | 码被贴到公开群就等于没拦 ✗ |
| 备注写 **GitHub 用户名** | 事后能对上是哪个申请 ✓ |

## 五、开关

想临时**关闭**邀请限制（比如自己测试注册 ✓）：
```sql
UPDATE settings SET setting_value = '0' WHERE setting_key = 'invite_required';   -- 关闭
UPDATE settings SET setting_value = '1' WHERE setting_key = 'invite_required';   -- 打开
```
⚠️ 后台设置页那个输入框是「每日注册上限」✓ 和这个不是一回事 ✓
（需要我把 `invite_required` 也做成设置页开关 ✓ 说一声 ✓）