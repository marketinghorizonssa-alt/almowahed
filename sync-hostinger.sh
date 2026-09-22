#!/bin/sh
set -eu
ROOT=/home/u414915683/domains/almowahid.sa/public_html/ads
BASE=https://raw.githubusercontent.com/marketinghorizonssa-alt/almowahed/main
curl -fsSL "$BASE/assets/app.js" -o "$ROOT/assets/app.js.tmp"
curl -fsSL "$BASE/lead.php" -o "$ROOT/lead.php.tmp"
php -l "$ROOT/lead.php.tmp" >/dev/null
mv "$ROOT/assets/app.js.tmp" "$ROOT/assets/app.js"
mv "$ROOT/lead.php.tmp" "$ROOT/lead.php"
sh "$ROOT/deploy-post.sh"
date -Is > "$ROOT/.last-github-sync"
echo ALMOWAHID_GITHUB_SYNC_OK
