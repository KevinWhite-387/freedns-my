-- freedns.my - upgrade for mail preferences.
--
-- A console account may switch off the notifications it does not want. The column holds the kinds that are
-- SWITCHED OFF, separated by commas; an empty value means "send everything", which is also what every
-- account that existed before this release gets, so nothing changes until somebody opts out.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). "Duplicate column" on a second run is harmless.

ALTER TABLE users
  ADD COLUMN mail_off VARCHAR(255) DEFAULT NULL AFTER referred_by;

-- Known kinds (see mail_kinds()):
--   chat     - an answer of the registry in the support chat
--   transfer - transfer tickets of the account (code issued, holder informed, completed, rejected)
--   market   - market offers (approved, rejected, somebody wants to buy)
--   shop     - the quota shop (promotion approved or rejected)
--   account  - account messages (password reset, address confirmation, sign-in help)
--
-- Messages that carry a password reset link are ALWAYS sent: switching them off would lock people out.
