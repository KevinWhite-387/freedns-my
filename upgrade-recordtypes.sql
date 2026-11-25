-- freedns.my - the record type column has to learn the newer types.
--
-- The table was created when only A, AAAA, CNAME and TXT existed, and its "type" column is an ENUM: a value
-- outside that list is refused by MySQL/MariaDB (in strict mode the whole insert fails), so MX, SRV, CAA and
-- PTR records could not be stored at all. This widens the list; NS is included so a future delegation
-- feature needs no second migration.
--
-- How to run it (aaPanel): back up first, then phpMyAdmin -> Import (or open /selftest.php and press the
-- repair button). Running it twice is harmless.

ALTER TABLE records
  MODIFY COLUMN type ENUM('A','AAAA','CNAME','TXT','MX','SRV','CAA','PTR','NS') NOT NULL;

-- If your installation still stores records without a per-record proxy flag, this adds it:
ALTER TABLE records
  ADD COLUMN proxied TINYINT(1) NOT NULL DEFAULT 0 AFTER ttl;
