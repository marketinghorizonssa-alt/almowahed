#!/bin/sh
set -e
ROOT=/home/u414915683/domains/almowahid.sa/public_html
mkdir -p "$ROOT/ads/assets/fonts"
cp "$ROOT/wp-content/themes/twentytwentyfive/assets/fonts/beiruti/Beiruti-VariableFont_wght.woff2" "$ROOT/ads/assets/fonts/Beiruti-VariableFont_wght.woff2"
cp "$ROOT/ads/llms.txt" "$ROOT/llms.txt"
cp "$ROOT/ads/robots-root.txt" "$ROOT/robots.txt"
