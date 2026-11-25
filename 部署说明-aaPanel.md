# freedns.my 全新部署（aaPanel ＋ PHP 8.1）

> 站点目录固定为 **/www/wwwroot/freedns.my/** ✓
> nginx 的 root 指向**站点根**（不是 public）✓

---

## 第 0 步：先删掉状态页（它现在占着这个域名）

状态页当初装在 `public/` 里，root 指向的是 `…/freedns.my/public` ✗ 全新部署前先清掉：

    # ① 备份（以防万一 ✓ 只留着备查，不占空间）
    mkdir -p /root/freedns-status-backup
    cp -a /www/wwwroot/freedns.my/public/. /root/freedns-status-backup/ 2>/dev/null
    cp -a /www/wwwroot/freedns.my/app/.    /root/freedns-status-backup/app/ 2>/dev/null

    # ② 删除状态页的全部文件（❌ 不要删掉整个 freedns.my 目录本身）
    rm -rf /www/wwwroot/freedns.my/public
    rm -rf /www/wwwroot/freedns.my/app
    rm -f  /www/wwwroot/freedns.my/*.zip /www/wwwroot/freedns.my/*.sql /www/wwwroot/freedns.my/*.md /www/wwwroot/freedns.my/*.conf

    # ③ 确认目录空了
    ls -la /www/wwwroot/freedns.my/

---

## 第 1 步：aaPanel 新建站点

  网站 → 添加站点

  | 项目 | 填什么 |
  | --- | --- |
  | 域名 | `freedns.my` 换行 `www.freedns.my` |
  | 根目录 | 默认 `/www/wwwroot/freedns.my`（**不要改** ✗ 不要加 /public） |
  | FTP | 不创建 |
  | 数据库 | **MySQL** ✓ 库名 `freedns_my` 用户名 `freedns_my`（密码记下来） |
  | PHP 版本 | **8.1** ✓（主站按 8.1 写的） |
  | 网站分类 | 默认 |

  创建完成后，先不要动「网站目录」，继续第 2 步。

---

## 第 2 步：上传文件

    mkdir -p /www/wwwroot/freedns.my
    # 把 freedns-deploy.zip 解压后的【全部内容】放到 /www/wwwroot/freedns.my/
    # 传完应该有：index.php  request.php  manage.php … inc/ account/ admin/ oauth/ open/ assets/ zh/ zh-hant/ ja/ ko/ ms/

  检查：

    ls -d /www/wwwroot/freedns.my/{inc,account,admin,oauth,open,assets,zh,zh-hant,ja,ko,ms}
    ls /www/wwwroot/freedns.my/zh | wc -l        # 应 55

---

## 第 3 步：aaPanel 里「网站目录」怎么设

  网站 → freedns.my → 设置 → **网站目录**

  | 项目 | 填什么 |
  | --- | --- |
  | 网站目录 | `/www/wwwroot/freedns.my` ✓（默认就是这个，不要改） |
  | 运行目录 | **留空 / 选 `/`** ✗✓ **不要选 /public** ✗（状态页才用 public） |
  | 防跨站攻击(open_basedir) | **保持开启** ✓（主站只读自己目录，开了不影响） |
  | 日志名称 | 默认即可 |
  | 访问日志 / 错误日志 | **都打开** ✓（出错时看 `/www/wwwlogs/freedns.my.error.log`） |

  ⚠️ 最容易错的就是「运行目录」✗ —— 选了 `/public` 就会 403 ✓（状态页才需要 public ✓ 主站不需要 ✓）

---

## 第 4 步：权限

    chown -R www:www /www/wwwroot/freedns.my
    find /www/wwwroot/freedns.my -type d -exec chmod 755 {} \;
    find /www/wwwroot/freedns.my -type f -exec chmod 644 {} \;
    chmod 775 /www/wwwroot/freedns.my/inc          # 安装时要写 inc/config.php

---

## 第 5 步：导入数据库

    cd /www/wwwroot/freedns.my
    mysql -u freedns_my -p freedns_my < schema.sql
    for f in upgrade-*.sql; do
      echo "== $f"
      mysql -u freedns_my -p freedns_my < "$f" || echo "   ✗ $f 报错了"
    done
    mysql -u freedns_my -p freedns_my -e "SHOW TABLES;" | wc -l

---

## 第 6 步：aaPanel 里「配置文件」怎么设

  网站 → freedns.my → 设置 → 配置文件（先备份原文件 ✗）

  需要保证有这几段（aaPanel 默认已生成 root / index / php，只需**加**下面四段）：

    # ① 禁止访问核心代码与版本库
    location ~ ^/(inc|\.git)/ { deny all; return 404; }

    # ② 禁止下载 SQL / 文档 / 备份
    location ~* \.(sql|md|bak|log|ini)$ { deny all; return 404; }

    # ③ 禁止点文件
    location ~ /\. { deny all; return 404; }

    # ④ 伪静态（主站用 query string 路由）
    location / { try_files $uri $uri/ /index.php?$query_string; }

  并且确认 PHP 那段里有这一行（看不到真实报错就是缺它 ✗）：

    fastcgi_intercept_errors off;

  改完：

    nginx -t && nginx -s reload

  另外「设置 → 伪静态」保持 **none** ✓（我们自己写了 try_files ✓ 不要用面板模板 ✗）

---

## 第 7 步：SSL

  网站 → freedns.my → SSL → Let's Encrypt → 勾选 `freedns.my` 与 `www.freedns.my` → 申请 → 开启「强制 HTTPS」

---

## 第 8 步：安装

  浏览器打开 `https://freedns.my/install.php`
    填数据库（127.0.0.1 / 库名 / 用户名 / 密码）
    建站长账号（用户名 ＋ 密码）
  → 看到安装完成
  → **立刻删除安装脚本** ✗

    rm -f /www/wwwroot/freedns.my/install.php

---

## 第 9 步：检查

    /www/server/php/81/bin/php -l /www/wwwroot/freedns.my/index.php
    curl -s -o /dev/null -w "首页  %{http_code}\n" https://freedns.my/                # 200
    curl -s -o /dev/null -w "中文  %{http_code}\n" https://freedns.my/zh/             # 200
    curl -s -o /dev/null -w "后台  %{http_code}\n" https://freedns.my/admin/          # 200
    curl -s -o /dev/null -w "inc   %{http_code}\n" https://freedns.my/inc/config.php  # 404

---

## 第 10 步：定时任务

  后台 → 设置 里找 cron key，然后：

    crontab -e
    */10 * * * * curl -s "https://freedns.my/mail-cron.php?key=KEY&limit=20" >/dev/null
    */15 * * * * curl -s "https://freedns.my/alert-cron.php?key=KEY&limit=25" >/dev/null

---

## 可选：两选项入口页

    cd /www/wwwroot/freedns.my
    cp index.php index.php.bak      # 备份原首页
    cp entry.php index.php          # 启用两选项页（DNSHE / FreeDns）
    # 改回正常首页： cp index.php.bak index.php

---

## 常见问题

  | 现象 | 原因 |
  | --- | --- |
  | 403 | 运行目录选成了 /public ✗ 或 root 指错 |
  | 语言页 404 | zh/ ja/ ko/ ms/ zh-hant/ 没传全（各 55 个文件） |
  | 500 | 看 /www/wwwlogs/freedns.my.error.log 最后一条 PHP Fatal error |
  | inc/config.php 能访问 | 缺 `location ~ ^/(inc|\.git)/ { deny all; }` |
  | 装完忘记删 install.php | 任何人可重装 ✗ 必须删 |