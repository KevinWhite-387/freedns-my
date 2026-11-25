-- freedns.my - upgrade for dynamic updates (DDNS).
--
-- A holder can generate an update token per name and point a router, a NAS (Synology, QNAP), a Raspberry
-- Pi or a script at the update address; the A / AAAA record of that name follows the address it reports.
--
-- How to run it (aaPanel):
--   1. back up the database first (Databases -> Backup);
--   2. phpMyAdmin -> your database -> Import -> choose this file (or paste it into the SQL tab);
--   3. run it once. "Duplicate column" on a second run is harmless.
--   (Or simply open /selftest.php in the back office and press the repair button.)

ALTER TABLE domains
  ADD COLUMN ddns_token VARCHAR(64) DEFAULT NULL AFTER ref_code,
  ADD COLUMN ddns_updated_at DATETIME DEFAULT NULL AFTER ddns_token;

CREATE INDEX idx_domain_ddns ON domains (ddns_token);

-- ddns_token holds a per-name secret; anybody who knows it may change that name's address, so it can be
-- reset in the console at any time. ddns_updated_at throttles the endpoint and is shown to the holder.
