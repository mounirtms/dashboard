#!/bin/bash
echo "Testing PIM after Cloudflare fix..."
echo ""
echo "Test 1: Check redirect count"
curl -sL -o /dev/null -w "URL: %{url_effective}\nCode: %{http_code}\nRedirects: %{num_redirects}\n" https://pim.technostationery.com/
echo ""
echo "Test 2: Check response headers"
curl -sI https://pim.technostationery.com/ | head -10
echo ""
echo "Test 3: Try accessing index.php directly"
curl -sI https://pim.technostationery.com/index.php | head -5
echo ""
if curl -sL https://pim.technostationery.com/ 2>&1 | grep -q "akeneo\|login\|pim"; then
    echo "✅ SUCCESS: PIM is now accessible!"
else
    echo "⚠️  Still having issues - check Cloudflare settings"
fi
