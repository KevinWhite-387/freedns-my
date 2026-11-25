# Security policy

freedns.my hands out names and serves DNS for them, so a hole here does not only affect this site: it
can affect every domain published under it. Please report problems privately.

## How to report

**Do not open a public issue.** Use one of these instead:

* GitHub's private reporting: **Security → Report a vulnerability** on this repository (preferred - it
  keeps the whole thread, the fix and the advisory together), or
* e-mail **security@freedns.my** with `SECURITY` in the subject.

Please include, as much as you can:

| | |
| --- | --- |
| What | A sentence on the class of problem (for example: "the API key check can be bypassed by …"). |
| Where | The file and, if you have it, the line; the URL and the exact request for anything web-facing. |
| How | Steps to reproduce, with the account or name state needed. A `curl` command or a raw HTTP request is ideal. |
| Impact | What an attacker gets: another holder's records, an account, the database, the mail service. |
| Proof | The response you got, with secrets and personal data removed. |

**Never test against another holder's account, and do not run a scanner against production.** A local
copy plus a database dump is enough for almost everything.

## What we will do

| When | What |
| --- | --- |
| Within 72 hours | We acknowledge your report and say whether we can reproduce it. |
| Within 7 days | We tell you our assessment: severity, and whether we intend to fix it. |
| On release | We credit you in the fix and in the changelog, unless you would rather stay anonymous. |

We do not run a paid bounty programme; this is a free service run by volunteers. We do try to be quick
and honest, and we will not take legal action against anyone who reports a problem in good faith,
stays inside their own account, and does not exfiltrate or publish other people's data.

## In scope

* Anything that lets one holder read or change **another holder's** names, records, mail or account.
* Authentication and session handling: the console sign-in, two-step sign-in, password reset, e-mail
  confirmation, GitHub sign-in.
* The open platform: OAuth 2.0 endpoints (`oauth/`), the resources under `open/v1/`, consent handling,
  scope enforcement, token and secret storage.
* The per-account API (`api.php`, `inc/api.php`) and dynamic DNS updates (`ddns.php`).
* SQL injection, remote code execution, path traversal, SSRF in the provider calls, and stored XSS in
  anything a holder can set (records, reviews, listings, chat, abuse reports).
* Leaking of `inc/config.php` or any provider/mail credential.
* The review boundary: a pending or revoked application, a suspended account, or a queued record change
  that takes effect without review.

## Out of scope

* Reports that the free service can be used to host something you dislike - use `/abuse.php` for that,
  it is the intended channel.
* Missing security headers, or cookie flags, without a demonstrated impact.
* Rate limiting on endpoints that cost nothing.
* Anything that requires an already-compromised account, a compromised device, or access to the
  operator's own machine.
* Automated scanner output pasted without a reproduction.

## Things we already assume

Documented so nobody spends a weekend on them: the public pages set no cookie unless a form needs CSRF
protection; secrets are stored as SHA-256 hashes (API keys, OAuth tokens, mailbox passwords use
encryption, never plaintext); tokens carry their scopes and are re-checked on every call; the redirect
address of an OAuth client is matched exactly; every state change in the back office is written to the
audit log.

## If you are a holder and your account was used

Write to **abuse@freedns.my** with the request code or the name involved. We can suspend a name, kill
every token and session of an account, and show you the audit log for it.
