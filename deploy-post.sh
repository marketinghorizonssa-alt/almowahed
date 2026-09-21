#!/bin/sh
set -e
ROOT=/home/u414915683/domains/almowahid.sa/public_html
mkdir -p "$ROOT/ads/assets/fonts" "$ROOT/ads/assets/img"
curl -fsSL https://fonts.gstatic.com/s/beiruti/v5/JTUXjIU69Cmr9FGcSA1t4FZA.woff2 -o "$ROOT/ads/assets/fonts/Beiruti-Arabic.woff2"
cp "$ROOT/wp-content/uploads/2026/07/image_3-768x512.jpeg" "$ROOT/ads/assets/img/hero-home-768.jpeg"
cp "$ROOT/wp-content/uploads/2026/07/image_3-1024x683.jpeg" "$ROOT/ads/assets/img/hero-home-1024.jpeg"
cp "$ROOT/wp-content/uploads/2026/07/image_6-768x512.jpeg" "$ROOT/ads/assets/img/hero-arrival-768.jpeg"
cp "$ROOT/wp-content/uploads/2026/07/image_6-1024x683.jpeg" "$ROOT/ads/assets/img/hero-arrival-1024.jpeg"
cp "$ROOT/wp-content/uploads/2026/07/image_5.jpg" "$ROOT/ads/assets/img/hero-philippines.jpg"
cp "$ROOT/ads/llms.txt" "$ROOT/llms.txt"
cp "$ROOT/ads/robots-root.txt" "$ROOT/robots.txt"

