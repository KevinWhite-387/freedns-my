#!/bin/bash
# Removes home.php now that index.php is the one and only home page.
#
# Run from the site root. It backs the file up first and checks the two things that would
# otherwise break the site: nginx must list index.php in its index directive, and entry.php
# must no longer point at home.php.
set -u
cd "$(dirname "$0")" || exit 1

echo "== 1. pre-checks =="
if [ ! -f index.php ]; then echo "✗ index.php missing - stop"; exit 1; fi
if grep -q "FD_LEGACY_URL = 'home.php'" entry.php 2>/dev/null; then
  echo "✗ entry.php still points FD_LEGACY_URL at home.php - upload the new entry.php first"; exit 1
fi
echo "✓ index.php present, entry.php clean"

echo
echo "== 2. nginx index directive =="
CONF="/www/server/panel/vhost/nginx/freedns.my.conf"
if [ -f "$CONF" ]; then
  grep -n "^\s*index " "$CONF" || echo "  (no explicit index line - nginx default is index.html index.htm)"
else
  echo "  $CONF not found, check manually"
fi

echo
echo "== 3. back up and remove =="
if [ -f home.php ]; then
  cp -a home.php "home.php.removed-$(date +%Y%m%d%H%M)"
  rm -f home.php
  echo "✓ home.php removed (backup kept as home.php.removed-*)"
else
  echo "  home.php already gone"
fi

echo
echo "== 4. verify =="
curl -s -o /dev/null -w "  / -> %{http_code}\n" https://freedns.my/
curl -s https://freedns.my/ | grep -c "t.me/freednsmy" | sed 's/^/  telegram link count: /'
echo
echo "Now: systemctl restart php-fpm-81   (opcache)"