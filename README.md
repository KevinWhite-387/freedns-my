# freedns.my

A small, deliberately plain registry for free second-level names: visitors request a name, an operator
reviews it, and the holder manages DNS records from a console. Plain PHP 8.2 and MySQL; no framework, no
build step, no JavaScript on the public pages.

## What is inside

| Area | Where |
| --- | --- |
| Public pages (home, domain list, WHOIS, request, status, docs) | `*.php` in the root |
| Holder console (names, records, quotas, API tokens) | `account/` |
| Operator back office | `admin/` |
| Shared code | `inc/` (bootstrap, database, publishing, mail, invites, …) |
| Schema and upgrades | `*.sql` (start with `schema.sql`, then the `upgrade-*.sql` you need) |
| Other languages | `ja/`, `ko/`, `ms/`, `zh/`, `zh-hant/` (thin wrappers that force a language) |

## Requirements

- PHP **8.2** with `pdo_mysql`, `curl`, `mbstring`, `openssl`, `json`
- MySQL 5.7+ or MariaDB 10.3+
- nginx or Apache with the site root as the document root

## Install, in short

```bash
# 1. put the tree in the document root, then create the database
mysql -uroot -p -e "CREATE DATABASE freedns_my CHARACTER SET utf8mb4"

# 2. import the schema, then any upgrades you want
mysql -uroot -p freedns_my < schema.sql
mysql -uroot -p freedns_my < upgrade-invites.sql        # invitation codes (optional)

# 3. point the code at the database
cp inc/config.sample.php inc/config.php
#    edit host / name / user / password

# 4. create the first operator account
php tools-create-admin.php            # or: INSERT INTO admins ... with a password_hash

# 5. open the site
```

## Registration is by invitation

Set `invite_required` to `1` (the default) and nobody can register without a code. Codes are created in
**Admin → People → Invite codes**, mailed by hand, and spent on use. `docs/GITHUB-INVITE-FLOW.md`
describes the GitHub issue → e-mail → account loop this deployment uses.

## Notes on data

Nothing in this repository is a live database. Operator settings (API keys, provider credentials, the
e-mail account used for outbound mail) live in the `settings` table of your own installation, **not** in
the code. Keep `inc/config.php` out of version control.

## License

MIT - see `LICENSE`.