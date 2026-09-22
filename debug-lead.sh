#!/bin/sh
curl -sS -i -X POST https://almowahid.sa/ads/lead.php \
  -H 'Content-Type: application/json' \
  --data '{"full_name":"TEST-SERVER","phone":"+966555555555","privacy_consent":"yes","service":"مكتب استقدام","page_url":"https://almowahid.sa/ads/"}'
