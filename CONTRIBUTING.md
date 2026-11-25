# Contributing

Thanks for wanting to help. This is a small project with strong opinions, so the rules below are short
and are actually enforced in review.

## The shape of the thing

* **Plain PHP, no framework, no Composer, no build step.** A page is a file; a library is a file in `inc/`.
* **No JavaScript on visitor-facing pages.** Public pages must work with scripting disabled - the chat
  page refreshes with a button, the forms are ordinary POSTs. (`/admin/` may use a little, but it does
  not today either.)
* **No images in the interface.** It is plain text and monospace on purpose.
* **English in the code, the user's language in the interface.** Every comment and every commit message
  is English. Anything a visitor reads goes through `t()` with a key that exists in *all six*
  catalogues (`inc/lang/en.php`, `zh.php`, `zh-hant.php`, `ja.php`, `ko.php`, `ms.php`) - the counts must
  stay equal, or the language switcher starts advertising languages as "in progress".
* **The back office is English only.** That is deliberate; do not translate it.

## Before you open a pull request

```bash
# 1) the syntax sweep - it must print nothing
find . -name '*.php' -exec php -l {} \; 2>&1 | grep -v 'No syntax errors' | head

# 2) the structure check (balanced brackets, no duplicate function names)
python3 _tools/php_sanity.py .            # if you have the tools folder

# 3) the language check (no missing keys, no placeholder drift)
python3 _tools/check_lang.py
```

A blank 500 with an empty error log is almost always a syntax error or a doubled function name; the
sweep above catches both before a user does.

## The habits that keep this codebase alive

| Practice | Why |
| --- | --- |
| `declare(strict_types=1);` at the top of every file | It has caught real bugs here. |
| Escape everything printed with `h()` | No exceptions; there is no template engine to do it for you. |
| CSRF on every POST: `csrf_verify()` and `csrf_field()` | Every form in the project does this. |
| `db_query()` / `db_one()` / `db_all()` with placeholders | Never build SQL by concatenation. |
| Write to the audit log: `log_action(...)` | Every state change in the back office is traceable. |
| Settings through `setting()` / `set_setting()` | Never edit the database by hand for configuration. |
| Migrations as `upgrade-<feature>.sql`, `CREATE TABLE IF NOT EXISTS`, no `AFTER column` | The aaPanel importer aborts the whole file on the first error, so a migration must be safe to run twice and must not assume column order. |
| New table? Add it to `selftest.php` | That page is how the operator sees a missing migration before a user does. |
| 4 spaces, no tabs, lines under ~120 characters | Consistency beats taste. |

## Commits and branches

* Branch: `fix/<what>`, `feat/<what>`, `docs/<what>`.
* Commit subject in the imperative, English, under 72 characters: `Fix the API refusing every key`.
* One idea per commit. A commit that fixes a bug *and* reformats a file will be asked to split.
* Reference the issue if there is one: `… (#12)`.

## What must never be committed

| Never | Because |
| --- | --- |
| `inc/config.php` | Database password, provider tokens, mail credentials. It is in `.gitignore`; keep it there. |
| Anything under `uploads/` | Other people's files. |
| `*.log`, `*.zip`, `*.bak`, database dumps | Noise at best, personal data at worst. |
| Real user data of any kind | E-mail addresses, names, record contents from production. |

If a secret does get pushed: **rotate it first**, then worry about history. Rotating takes minutes;
rewriting git history takes hours and still leaves the value in every clone and fork.

## Changing something a user reads

If you add a string to the interface, add the key to **all six** catalogues in the same pull request -
English and Chinese at minimum, and if you cannot translate the other four, put the English sentence
there so the counts stay equal and the "in progress" notice does not appear for the whole site.

## Changing something that talks to a provider

Cloudflare, DNSHE and the mail service are each wrapped in `inc/<provider>.php`. Keep the provider
detail in there: no page and no library outside that file should know a provider's API shape. A new
provider is a new file of the same kind, and a line in the zone settings - nothing else.

## Reporting a problem instead of fixing it

That is fine, and useful. Please include: what you did, what you expected, what happened, the exact
error line from `php-error.log` or the nginx log, and your PHP version. "It does not work" cannot be
acted on; one log line usually can.
