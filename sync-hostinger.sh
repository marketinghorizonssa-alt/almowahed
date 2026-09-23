#!/bin/sh
set -eu
ROOT=/home/u414915683/domains/almowahid.sa/public_html/ads
BASE=https://raw.githubusercontent.com/marketinghorizonssa-alt/almowahed/main

curl -fsSL "$BASE/index.html" -o "$ROOT/index.html.tmp"
curl -fsSL "$BASE/assets/style.css" -o "$ROOT/assets/style.css.tmp"
curl -fsSL "$BASE/assets/app.js" -o "$ROOT/assets/app.js.tmp"
curl -fsSL "$BASE/lead.php" -o "$ROOT/lead.php.tmp"
curl -fsSL "$BASE/retry_leads.php" -o "$ROOT/retry_leads.php.tmp"

php -l "$ROOT/lead.php.tmp" >/dev/null
php -l "$ROOT/retry_leads.php.tmp" >/dev/null

mv "$ROOT/index.html.tmp" "$ROOT/index.html"
mv "$ROOT/assets/style.css.tmp" "$ROOT/assets/style.css"
mv "$ROOT/assets/app.js.tmp" "$ROOT/assets/app.js"
mv "$ROOT/lead.php.tmp" "$ROOT/lead.php"
mv "$ROOT/retry_leads.php.tmp" "$ROOT/retry_leads.php"

sh "$ROOT/deploy-post.sh"
date -Is > "$ROOT/.last-github-sync"
echo ALMOWAHID_GITHUB_SYNC_OK
