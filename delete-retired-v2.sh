#!/bin/sh
# ============================================================================
# freedns.my - delete the nine withdrawn features for real.
#
# The operator wants a plain 404 on those addresses: no explanation page, no
# redirect, nothing. This script does exactly that and nothing else:
#
#   1. deletes the sixteen page files (the operator's list, name servers kept)
#   2. deletes retired.php, the fallback page, so no 404 is rewritten to it
#   3. checks afterwards that nothing is left and that the site still parses
#
# It does NOT touch inc/*.php: ads.php, shop.php, rainyun.php and reviews.php are
# libraries that other pages still load, and deleting them would break the site.
# Their pages are gone, so nothing calls into them any more.
#
# Run from the site root:
#
#     cd /www/wwwroot/freedns.my
#     sh delete-retired-v2.sh
#
# ============================================================================

cd "$(dirname "$0")" || exit 1

PHP=/www/server/php/81/bin/php
[ -x "$PHP" ] || PHP=php

echo "=== 1. deleting the withdrawn pages ==="
for f in \
  account/email.php \
  account/forward.php \
  account/forwarding.php \
  account/notify.php \
  account/shop.php \
  account/bonus.php \
  account/servers.php \
  account/theme.php \
  account/ads.php \
  advertise.php \
  reviews.php \
  experimental.php \
  admin/servers.php \
  admin/forward-requests.php \
  admin/reviews.php \
  admin/shop.php
do
  if [ -f "$f" ]; then
    rm -f "$f" && echo "  deleted  $f"
  else
    echo "  absent   $f"
  fi
done

echo
echo "=== 2. deleting the fallback page (you asked for a real 404) ==="
for f in retired.php; do
  if [ -f "$f" ]; then
    rm -f "$f" && echo "  deleted  $f"
  else
    echo "  absent   $f"
  fi
done

echo
echo "=== 3. checking ==="
left=0
for f in \
  account/email.php account/forward.php account/forwarding.php account/notify.php \
  account/shop.php account/bonus.php account/servers.php account/theme.php account/ads.php \
  advertise.php reviews.php experimental.php \
  admin/servers.php admin/forward-requests.php admin/reviews.php admin/shop.php \
  retired.php
do
  if [ -f "$f" ]; then
    echo "  STILL THERE  $f"
    left=$((left + 1))
  fi
done
[ "$left" -eq 0 ] && echo "  all gone"

echo
echo "=== 4. syntax check of what is left ==="
bad=0
for f in inc/views.php inc/bootstrap.php inc/retired.php sitemap.php selftest.php account/index.php; do
  if [ -f "$f" ]; then
    out=$("$PHP" -l "$f" 2>&1)
    case "$out" in
      *"No syntax errors"*) echo "  ok       $f" ;;
      *) echo "  FAIL     $f"; echo "$out"; bad=$((bad + 1)) ;;
    esac
  fi
done
[ "$bad" -eq 0 ] || echo "  !! $bad file(s) failed the syntax check"

echo
echo "=== 5. nginx: remove the 404 rewrite if you added it ==="
echo "  If the site config contains this line, delete it - otherwise a 404 is"
echo "  rewritten to a page instead of being a 404:"
echo
echo "      error_page 404 /retired.php;      <-- remove this line"
echo
echo "  Then:  nginx -t && nginx -s reload"
echo
echo "Done. Those addresses now answer 404 with nothing else attached."
