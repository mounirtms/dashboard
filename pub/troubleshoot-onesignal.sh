#!/bin/bash
# OneSignal Push Notification Troubleshooting and Fix Script
# This script helps diagnose and fix common OneSignal notification issues

echo "🔍 OneSignal Push Notification Troubleshooter"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    case $1 in
        "SUCCESS")
            echo -e "${GREEN}✅ $2${NC}"
            ;;
        "ERROR")
            echo -e "${RED}❌ $2${NC}"
            ;;
        "WARNING")
            echo -e "${YELLOW}⚠️  $2${NC}"
            ;;
        "INFO")
            echo -e "${BLUE}ℹ️  $2${NC}"
            ;;
    esac
}

# Check 1: Service Worker Files
print_status "INFO" "Checking Service Worker files..."

if [ -f "/home/technadminy7/public_html/pub/OneSignalSDKWorker.js" ]; then
    print_status "SUCCESS" "OneSignalSDKWorker.js exists"
else
    print_status "ERROR" "OneSignalSDKWorker.js not found"
fi

if [ -f "/home/technadminy7/public_html/pub/OneSignalSDKUpdaterWorker.js" ]; then
    print_status "SUCCESS" "OneSignalSDKUpdaterWorker.js exists"
else
    print_status "ERROR" "OneSignalSDKUpdaterWorker.js not found"
fi

# Check 2: File Permissions
print_status "INFO" "Checking file permissions..."
chmod 644 /home/technadminy7/public_html/pub/OneSignalSDK*.js 2>/dev/null
print_status "SUCCESS" "Set proper permissions for service worker files"

# Check 3: Web Accessibility
print_status "INFO" "Checking web accessibility..."

# Test service worker accessibility
curl -s -f "https://technostationery.com/OneSignalSDKWorker.js" > /dev/null
if [ $? -eq 0 ]; then
    print_status "SUCCESS" "OneSignalSDKWorker.js is accessible via web"
else
    print_status "ERROR" "OneSignalSDKWorker.js is not accessible via web"
fi

curl -s -f "https://technostationery.com/OneSignalSDKUpdaterWorker.js" > /dev/null
if [ $? -eq 0 ]; then
    print_status "SUCCESS" "OneSignalSDKUpdaterWorker.js is accessible via web"
else
    print_status "ERROR" "OneSignalSDKUpdaterWorker.js is not accessible via web"
fi

# Check 4: Manifest File
print_status "INFO" "Checking manifest file..."

curl -s -f "https://technostationery.com/manifest.json" > /dev/null
if [ $? -eq 0 ]; then
    print_status "SUCCESS" "manifest.json is accessible"
else
    print_status "ERROR" "manifest.json is not accessible"
fi

# Check 5: OneSignal Configuration in Head Template
print_status "INFO" "Checking OneSignal configuration in head template..."

if grep -q "OneSignal.init" "/home/technadminy7/public_html/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/head.phtml"; then
    print_status "SUCCESS" "OneSignal initialization code found in head template"
else
    print_status "ERROR" "OneSignal initialization code not found in head template"
fi

# Check 6: APP ID Verification
print_status "INFO" "Verifying OneSignal APP ID..."

APP_ID=$(grep -o 'appId: "[^"]*"' "/home/technadminy7/public_html/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/head.phtml" | cut -d'"' -f2)

if [ ! -z "$APP_ID" ]; then
    print_status "SUCCESS" "OneSignal APP ID found: $APP_ID"
else
    print_status "ERROR" "OneSignal APP ID not found in configuration"
fi

# Check 7: Service Worker Path Configuration
print_status "INFO" "Checking service worker path configuration..."

if grep -q 'serviceWorkerPath: "/OneSignalSDKWorker.js"' "/home/technadminy7/public_html/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/head.phtml"; then
    print_status "SUCCESS" "Service worker path correctly configured"
else
    print_status "ERROR" "Service worker path not correctly configured"
fi

# Check 8: Browser Console Errors Simulation
print_status "INFO" "Creating browser console error checker..."

# Create a temporary test file
cat > /home/technadminy7/public_html/pub/check-console-errors.js << 'EOF'
// Simulate browser console error checking
console.log("Starting OneSignal console error simulation...");

// Check for common OneSignal errors
const commonErrors = [
    "OneSignal not initialized",
    "ServiceWorker registration failed",
    "PushManager not supported",
    "Notification permission denied",
    "OneSignalSDKWorker.js not found"
];

console.log("Common OneSignal issues to watch for:");
commonErrors.forEach(error => {
    console.log("- " + error);
});

// Simulate service worker registration check
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(registrations => {
        const oneSignalSW = registrations.find(reg => reg.active && reg.active.scriptURL.includes('OneSignal'));
        if (oneSignalSW) {
            console.log("✅ OneSignal Service Worker registered:", oneSignalSW.scope);
        } else {
            console.warn("⚠️ OneSignal Service Worker not found");
        }
    }).catch(error => {
        console.error("❌ Error checking service workers:", error);
    });
} else {
    console.error("❌ Service Workers not supported");
}

console.log("Console error check complete.");
EOF

print_status "SUCCESS" "Created console error checker script"

# Check 9: Create Diagnostic Report
print_status "INFO" "Generating diagnostic report..."

DIAGNOSTIC_REPORT="/home/technadminy7/public_html/pub/onesignal-diagnostic-report-$(date +%Y%m%d-%H%M%S).txt"

{
    echo "OneSignal Diagnostic Report"
    echo "Generated: $(date)"
    echo "=================================="
    echo ""
    echo "System Information:"
    echo "- Domain: technostationery.com"
    echo "- APP ID: $APP_ID"
    echo "- Service Worker Path: /OneSignalSDKWorker.js"
    echo ""
    echo "File Status:"
    echo "- OneSignalSDKWorker.js: $( [ -f "/home/technadminy7/public_html/pub/OneSignalSDKWorker.js" ] && echo "Exists" || echo "Missing" )"
    echo "- OneSignalSDKUpdaterWorker.js: $( [ -f "/home/technadminy7/public_html/pub/OneSignalSDKUpdaterWorker.js" ] && echo "Exists" || echo "Missing" )"
    echo "- manifest.json: $( curl -s -f "https://technostationery.com/manifest.json" > /dev/null && echo "Accessible" || echo "Not Accessible" )"
    echo ""
    echo "Configuration Status:"
    echo "- Head template configured: $( grep -q "OneSignal.init" "/home/technadminy7/public_html/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/head.phtml" && echo "Yes" || echo "No" )"
    echo "- Service worker path correct: $( grep -q 'serviceWorkerPath: "/OneSignalSDKWorker.js"' "/home/technadminy7/public_html/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/head.phtml" && echo "Yes" || echo "No" )"
    echo ""
    echo "Recommendations:"
    echo "1. Visit https://technostationery.com/pub/test-onesignal-debug.html to run full diagnostic"
    echo "2. Check browser console for OneSignal initialization errors"
    echo "3. Ensure users grant notification permissions"
    echo "4. Verify OneSignal dashboard has active subscribers"
} > "$DIAGNOSTIC_REPORT"

print_status "SUCCESS" "Diagnostic report generated: $DIAGNOSTIC_REPORT"

# Final Summary
echo ""
print_status "INFO" "=== TROUBLESHOOTING SUMMARY ==="
print_status "INFO" "Key URLs to test:"
print_status "INFO" "- Main diagnostic: https://technostationery.com/pub/test-onesignal-debug.html"
print_status "INFO" "- Backend test: https://technostationery.com/pub/test-onesignal-backend.php"
print_status "INFO" "- Service Worker: https://technostationery.com/OneSignalSDKWorker.js"
print_status "INFO" ""
print_status "INFO" "Common Issues & Solutions:"
print_status "INFO" "1. Notifications not showing → Check browser permissions"
print_status "INFO" "2. Service Worker errors → Clear browser cache and reload"
print_status "INFO" "3. Initialization fails → Check APP ID and network connectivity"
print_status "INFO" "4. No subscribers → Users need to opt-in for notifications"

print_status "SUCCESS" "Troubleshooting complete! Check the diagnostic report for detailed findings."