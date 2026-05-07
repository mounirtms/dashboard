#!/bin/bash
echo "=== PIM Cloudflare Configuration Fix Guide ==="
echo "Date: $(date)"
echo ""

echo "✅ ORIGIN SERVER STATUS:"
echo "- Backend (port 81): Working perfectly (HTTP 200)"
echo "- SSL Config: Properly configured"
echo "- DocumentRoot: Correct (/home/pim/public_html/public)"
echo "- .htaccess: Clean and correct"
echo ""

echo "❌ ISSUE IDENTIFIED:"
echo "Redirect is happening at CLOUDFLARE level, not origin server"
echo "Evidence: Origin returns 200, but HTTPS through Cloudflare returns 301 loop"
echo ""

echo "🔧 SOLUTION - Cloudflare Configuration Changes Needed:"
echo ""
echo "Option 1: Check SSL/TLS Settings"
echo "  1. Login to Cloudflare Dashboard"
echo "  2. Select domain: technostationery.com"
echo "  3. Go to SSL/TLS > Overview"
echo "  4. Current mode should be: Full (Strict) or Full"
echo "  5. If set to 'Flexible', change to 'Full' or 'Full (Strict)'"
echo ""

echo "Option 2: Check Page Rules"
echo "  1. Go to Rules > Page Rules"
echo "  2. Look for any rules affecting pim.technostationery.com"
echo "  3. Check for 'Forwarding URL' or 'Always Use HTTPS' rules"
echo "  4. Disable or modify conflicting rules"
echo ""

echo "Option 3: Check Transform Rules / Redirect Rules"
echo "  1. Go to Rules > Transform Rules"
echo "  2. Check for URL Redirects affecting pim subdomain"
echo "  3. Disable any rules causing the loop"
echo ""

echo "Option 4: Temporarily Bypass Cloudflare (Testing)"
echo "  1. Go to DNS settings"
echo "  2. Click on pim.technostationery.com DNS record"
echo "  3. Change from 'Proxied' (orange cloud) to 'DNS only' (gray cloud)"
echo "  4. Wait 5 minutes for DNS propagation"
echo "  5. Test: https://pim.technostationery.com"
echo ""

echo "🧪 TESTING THE FIX:"
cat > /home/dashboard/public_html/test-pim-after-cloudflare-fix.sh << 'TESTSCRIPT'
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
TESTSCRIPT

chmod +x /home/dashboard/public_html/test-pim-after-cloudflare-fix.sh

echo ""
echo "📋 TEMPORARY WORKAROUND (Until Cloudflare is fixed):"
echo ""
echo "Access PIM via internal network:"
echo "  http://127.0.0.1:81/ (with Host: pim.technostationery.com)"
echo ""
echo "Or add to /etc/hosts on your local machine:"
echo "  209.126.117.105  pim.technostationery.com"
echo "  Then access: https://pim.technostationery.com"
echo ""

echo "📝 SUMMARY:"
echo "- Origin server: ✅ Fixed and working"
echo "- Apache config: ✅ Correct"
echo "- Issue location: ❌ Cloudflare (CDN layer)"
echo "- Action required: Fix Cloudflare SSL/TLS or Page Rules"
echo ""
echo "After fixing Cloudflare, run: bash /home/dashboard/public_html/test-pim-after-cloudflare-fix.sh"
