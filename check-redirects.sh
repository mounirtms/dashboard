#!/bin/bash
echo "=== Checking All Sites for Redirect Issues ==="
echo "Date: $(date)"
echo ""

sites=(
    "https://technostationery.com"
    "https://beta.technostationery.com"
    "https://dev.technostationery.com"
    "https://lms.technostationery.com"
    "https://dashboard.technostationery.com"
    "https://pim.technostationery.com"
)

for site in "${sites[@]}"; do
    echo "=== Testing: $site ==="
    
    # Follow redirects and show the chain
    echo "Redirect chain:"
    curl -sL -w "\nFinal URL: %{url_effective}\nHTTP Code: %{http_code}\nRedirect Count: %{num_redirects}\n" \
         -o /dev/null "$site" 2>&1
    
    # Check for redirect loops (max 5 redirects)
    echo "Checking for loops (max 5 redirects):"
    curl -sI --max-redirs 5 "$site" 2>&1 | head -20
    
    echo ""
    echo "---"
    echo ""
done

echo "=== Checking Backend Ports ==="
echo "Port 81 (Backend):"
curl -sI http://127.0.0.1:81/ -H "Host: dashboard.technostationery.com" | head -10

echo ""
echo "Port 8888 (Varnish):"
curl -sI http://127.0.0.1:8888/ -H "Host: dashboard.technostationery.com" | head -10
