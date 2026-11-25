#!/bin/sh
# ============================================================================
# freedns.my - delete the withdrawn features for real, wrappers included.
#
# Version 2 deleted the pages but left the language wrappers behind:
#
#     zh/account/shop.php   ->  require dirname(__DIR__, 2) . '/account/shop.php'
#
# With the target gone, that wrapper is a 500 (require of a missing file), which is
# what the operator saw. This version deletes:
#
#   1. the sixteen pages
#   2. retired.php, the fallback page (a plain 404 was asked for)
#   3. every language wrapper for them, in zh, zh-hant, ja, ko, ms
#   4. any other wrapper in those directories whose target no longer exists
#
# Kept: account/nameservers.php, admin/nameservers.php, and every inc/*.php library.
#
#     cd /www/wwwroot/freedns.my
#     sh delete-retired-v3.sh
# ============================================================================

cd "$(dirname "$0")" || exit 1

PHP=/www/server/php/81/bin/php
[ -x "$PHP" ] || PHP=php

PAGES="account/email.php account/forward.php account/forwarding.php account/notify.php
account/shop.php account/bonus.php account/servers.php account/theme.php account/ads.php
advertise.php reviews.php experimental.php
admin/servers.php admin/forward-requests.php admin/reviews.php admin/shop.php"

LANGS="zh zh-hant ja ko ms"

echo "=== 1. pages ==="
for f in $PAGES retired.php; do
  if [ -f "$f" ]; then rm -f "$f" && echo "  deleted  $f"; else echo "  absent   $f"; fi
done

echo
echo "=== 2. language wrappers for those pages ==="
removed=0
for l in $LANGS; do
  for f in $PAGES; do
    w="$l/$f"
    if [ -f "$w" ]; then rm -f "$w"; removed=$((removed + 1)); echo "  deleted  $w"; fi
  done
done
echo "  $removed wrapper(s) deleted"

echo
echo "=== 3. sweeping every wrapper whose target is missing ==="
swept=0
for l in $LANGS; do
  [ -d "$l" ] || continue
  find "$l" -name '*.php' | while read -r w; do
    rel=${w#"$l"/}
    if [ ! -f "$rel" ]; then
      rm -f "$w"
      echo "  swept    $w  (target $rel is gone)"
      swept=$((swept + 1))
    fi
  done
done

echo
echo "=== 4. nothing left? ==="
left=0
for f in $PAGES retired.php; do
  [ -f "$f" ] && { echo "  STILL THERE  $f"; left=$((left + 1)); }
done
for l in $LANGS; do
  for f in $PAGES; do
    [ -f "$l/$f" ] && { echo "  STILL THERE  $l/$f"; left=$((left + 1)); }
  done
done
[ "$left" -eq 0 ] && echo "  all gone"

echo
echo "=== 5. syntax check of the pages that remain ==="
bad=0
for f in index.php sitemap.php selftest.php inc/views.php inc/bootstrap.php inc/retired.php \
         account/index.php account/nameservers.php admin/index.php admin/nameservers.php \
         zh/index.php zh/account/index.php; do
  if [ -f "$f" ]; then
    out=$("$PHP" -l "$f" 2>&1)
    case "$out" in
      *"No syntax errors"*) echo "  ok       $f" ;;
      *) echo "  FAIL     $f"; echo "$out"; bad=$((bad + 1)) ;;
    esac
  fi
done
[ "$bad" -eq 0 ] || echo "  !! $bad file(s) failed"

echo
echo "=== 6. nginx ==="
echo "  Make sure the site config does NOT contain:"
echo "      error_page 404 /retired.php;"
echo "  then: nginx -t && nginx -s reload"
echo
echo "Those addresses now answer a plain 404."