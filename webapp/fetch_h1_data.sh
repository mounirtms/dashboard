#!/bin/bash
TOKEN=$(python3 -c "import json; print(json.load(open('/home/dashboard/public_html/config/magento_credentials.json'))['prod']['token'])")

BASE="https://technostationery.com/rest/V1/orders"

# Fetch 5 pages of 100 each = 500 (covers all 498)
for PAGE in 1 2 3 4 5; do
  URL="${BASE}?searchCriteria[filterGroups][0][filters][0][field]=status&searchCriteria[filterGroups][0][filters][0][value]=CMD_Done&searchCriteria[filterGroups][0][filters][0][conditionType]=eq&searchCriteria[filterGroups][1][filters][0][field]=created_at&searchCriteria[filterGroups][1][filters][0][value]=2026-01-01+00:00:00&searchCriteria[filterGroups][1][filters][0][conditionType]=gteq&searchCriteria[filterGroups][2][filters][0][field]=created_at&searchCriteria[filterGroups][2][filters][0][value]=2026-07-01+00:00:00&searchCriteria[filterGroups][2][filters][0][conditionType]=lt&searchCriteria[pageSize]=100&searchCriteria[currentPage]=${PAGE}&fields=items[grand_total,created_at,shipping_address[region]]"
  echo "Fetching page ${PAGE}..."
  curl -s -g -H "Authorization: Bearer $TOKEN" "$URL" > /home/dashboard/public_html/webapp/orders_page${PAGE}.json
  sleep 0.3
done
echo "Done!"
