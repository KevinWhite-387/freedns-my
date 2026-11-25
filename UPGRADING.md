# How to upgrade freedns.my

This file travels with the package. It lists what has to be imported, what has to be switched on in the back
office and what should be tested after an upgrade. The site itself does not link to it.

## 1. Statements to import (in this order)

| # | File | What it adds |
| --- | --- | --- |
| 1 | `upgrade-transfer.sql` | transfer tickets: `transfer_requests`, `transfer_messages` |
| 2 | `upgrade-market.sql` | the market: `market_listings`, `market_enquiries` |
| 3 | `upgrade-ns.sql` | own name servers: `zones.allow_ns`, `domains.delegated/nameservers/glue` |
| 4 | `upgrade-shop.sql` | the shop: `shop_promos`, `users.invite_code/referred_by`, `domains.ref_code` |
| 5 | `upgrade-chat.sql` | the chat: `chat_threads`, `chat_messages` |
| 6 | `upgrade-ddns.sql` | dynamic updates: `domains.ddns_token/ddns_updated_at` |
| 7 | `upgrade-api.sql` | the user API: `api_tokens` |
| 8 | `upgrade-mailroute.sql` | mail forwarding: `mail_routes`, `zones.cf_account_id` |
| 9 | `upgrade-abuse.sql` | abuse reports: `abuse_reports` |
| 10 | `upgrade-totp.sql` | two-factor: `users.totp_*`, `admins.totp_*` |

Easier: open `/selftest.php` (owner only). It lists every missing table and column, tick them all and press
**Apply the selected statements** - the repair runs each statement on its own and reports the result.
A second run that says "duplicate column" or "already exists" is harmless.

## 2. What to switch on in the back office

1. **Name servers** - tick *Allow own name servers* for the suffixes whose upstream really allows it
   (zones in the Public Suffix List, for example `us.ci` and `de5.net`).
2. **Settings** - review `changelog` / `changelog_zh`, `terms_text` / `terms_text_zh` and
   `privacy_text` / `privacy_text_zh` and put your real contact details in.
3. **Shop** - check the three numbers: `star_bonus_amount`, `promo_bonus_amount`, `promo_max_total`.
4. **DNS** - every suffix needs its publishing service (Cloudflare or DNSHE) and its credentials.

## 3. Outside permissions

* **Cloudflare token**: add *Zone → Email Routing → Edit* (needed by mail forwarding) and include the
  zone in the token's resources.
* **GitHub token** (star reward): a classic token needs no scope at all; a fine-grained token needs
  *Public Repositories (read-only)*. Always press the check button after saving it.

## 4. What to test after an upgrade

| Feature | How | Expected |
| --- | --- | --- |
| self test repair | `/selftest.php`, tick and apply | every statement reports `applied`, then all checks pass |
| DDNS | console, create a token, `curl "…&ip=1.2.3.4"` | `good 1.2.3.4`, then `nochg`, a wrong token gives `badauth` |
| new record types | add an MX `10 mail.yourname` | saved and published |
| wildcard | add an A record on the sub-name `*` | saved |
| user API | `curl -H "Authorization: Bearer <token>" ".../api.php?action=domains"` | JSON list of the account's names |
| mail forwarding | create `info@yourname`, confirm at Cloudflare, send a mail | it is forwarded; after removal it is refused |
| health page | back office → **Health** | no 404, everything OK |
| terms / privacy / abuse | submit a report on `/abuse.php` | it shows up under **Abuse** |
| two-factor | switch it on with a **test account**, sign out and in | the six-digit code is asked for |
| export / closing | download the data with a test account | the file holds account, names, records, tickets, chat |
| bulk records | back office → **Bulk records**, export and paste back | the preview matches, applying changes nothing |

## 5. Rolling back

Almost everything shipped here is a **new file**, so a single feature can be taken out by deleting its
pages and its `inc/*.php`. Only these points touch existing flows:

* `require_user()` (inc/users.php) and `require_admin()` (inc/auth.php) - the optional second factor;
* `manage.php` and `account/manage.php` - the record lock during a transfer, and read-only for names that
  use their own name servers;
* `admin/decision.php` - DNSHE names cannot be deleted;
* `inc/dnshe.php` - the subdomain is never deleted at DNSHE;
* `t()` (inc/functions.php) - tolerates a translation with more placeholders than arguments.
