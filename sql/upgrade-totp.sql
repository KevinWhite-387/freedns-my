-- freedns.my - upgrade for optional two-factor authentication (TOTP).
--
-- Nobody is forced to use it: an account may switch it on for itself, and switch it off again with a
-- current code. It is offered for console accounts and for the back office accounts, because both can do
-- damage if the password leaks.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Duplicate column" on a second run is harmless.

ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER github_linked_at,
  ADD COLUMN totp_at DATETIME DEFAULT NULL AFTER totp_secret;

ALTER TABLE admins
  ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER gate_pass,
  ADD COLUMN totp_at DATETIME DEFAULT NULL AFTER totp_secret;

-- The secret is only stored once the account confirmed a code from it, so a half-finished setup can never
-- lock anybody out; totp_at holds the moment it was switched on.
