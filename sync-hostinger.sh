#!/bin/sh
set -eu
ROOT=/home/u414915683/domains/almowahid.sa/public_html/ads
BASE=https://raw.githubusercontent.com/marketinghorizonssa-alt/almowahed/main

FILES="index.html about/index.html domestic-workers/index.html drivers/index.html ethiopia/index.html faq/index.html nationalities/index.html offers-fast/index.html philippines/index.html privacy/index.html recruitment-office/index.html assets/style.css assets/app.js lead.php retry_leads.php google-lead-webhook.php"

for P in $FILES; do
  mkdir -p "$ROOT/$(dirname "$P")"
  curl -fsSL "$BASE/$P" -o "$ROOT/$P.tmp"
done

php -l "$ROOT/lead.php.tmp" >/dev/null
php -l "$ROOT/retry_leads.php.tmp" >/dev/null
php -l "$ROOT/google-lead-webhook.php.tmp" >/dev/null

for P in $FILES; do
  mv "$ROOT/$P.tmp" "$ROOT/$P"
done

sh "$ROOT/deploy-post.sh"
date -Is > "$ROOT/.last-github-sync"
echo ALMOWAHID_ATTRIBUTION_SYNC_OK
