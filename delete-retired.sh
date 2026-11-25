#!/bin/sh
# ============================================================================
# freedns.my - delete the files of the nine withdrawn features.
#
# Run this on the server, once, from the site root:
#
#     cd /www/wwwroot/freedns.my
#     sh delete-retired.sh
#
# Every file here is a PAGE, not a library: nothing else includes them, and the
# scan that produced this list found only links and labels pointing at them, which
# now land on /retired.php through the nginx rule below.
#
# Kept on purpose: account/nameservers.php and admin/nameservers.php (the operator
# asked to keep name servers), and inc/*.php libraries.
# ============================================================================

set -e
cd "$(dirname "$0")"

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
    rm -f "$f"
    echo "removed  $f"
  else
    echo "absent   $f"
  fi
done

echo
echo "Now add this to the server block in your nginx site config, then reload nginx:"
echo
echo "    error_page 404 /retired.php;"
echo
echo "    # nginx -t && nginx -s reload"
echo
echo "Without that line the deleted addresses answer with nginx's own 404. With it they show"
echo "the 'feature withdrawn' page that carries the operator's wording."