#!/bin/sh
set -e
ROOT=/home/u414915683/domains/almowahid.sa/public_html
mkdir -p "$ROOT/ads/assets/fonts"
curl -fsSL https://fonts.gstatic.com/s/beiruti/v5/JTUXjIU69Cmr9FGcSA1t4FZA.woff2 -o "$ROOT/ads/assets/fonts/Beiruti-Arabic.woff2"
cp "$ROOT/ads/llms.txt" "$ROOT/llms.txt"
cp "$ROOT/ads/robots-root.txt" "$ROOT/robots.txt"
