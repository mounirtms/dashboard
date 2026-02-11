#!/bin/bash
# Fix Page 2 Loading Mask Issue
# NO DOWNTIME - Safe to run during business hours

echo "========================================"
echo "FIX PAGE 2 LOADING MASK STUCK ISSUE"
echo "========================================"
echo ""

MAGENTO_ROOT="/home/technadminy7/public_html"
TARGET_FILE="$MAGENTO_ROOT/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/page-loading.phtml"
FIXED_FILE="$MAGENTO_ROOT/webapp/page-loading-FIXED.phtml"
BACKUP_FILE="$TARGET_FILE.backup.$(date +%Y%m%d_%H%M%S)"

cd "$MAGENTO_ROOT" || exit 1

echo "Current working directory: $(pwd)"
echo ""

# Step 1: Verify files exist
echo "Step 1: Verifying files..."
if [ ! -f "$TARGET_FILE" ]; then
    echo "❌ ERROR: Target file not found: $TARGET_FILE"
    exit 1
fi

if [ ! -f "$FIXED_FILE" ]; then
    echo "❌ ERROR: Fixed file not found: $FIXED_FILE"
    exit 1
fi

echo "✅ Both files found"
echo ""

# Step 2: Show diff
echo "Step 2: Showing changes..."
echo "────────────────────────────────────────"
diff -u "$TARGET_FILE" "$FIXED_FILE" | head -50
echo "────────────────────────────────────────"
echo ""

# Step 3: Confirm
read -p "Apply this fix? This will backup the original file. (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Fix cancelled by user"
    exit 0
fi

# Step 4: Backup original
echo ""
echo "Step 3: Backing up original file..."
cp "$TARGET_FILE" "$BACKUP_FILE"
if [ $? -eq 0 ]; then
    echo "✅ Backup created: $BACKUP_FILE"
else
    echo "❌ Backup failed"
    exit 1
fi
echo ""

# Step 5: Apply fix
echo "Step 4: Applying fix..."
cp "$FIXED_FILE" "$TARGET_FILE"
if [ $? -eq 0 ]; then
    echo "✅ Fix applied successfully"
else
    echo "❌ Fix failed, restoring backup..."
    cp "$BACKUP_FILE" "$TARGET_FILE"
    exit 1
fi
echo ""

# Step 6: Verify file
echo "Step 5: Verifying fix..."
if grep -q "maxLoadTime = 10000" "$TARGET_FILE"; then
    echo "✅ Fix verified - timeout logic present"
else
    echo "⚠️ WARNING: Could not verify fix"
fi
echo ""

# Step 7: Clear caches
echo "Step 6: Clearing caches..."
php bin/magento cache:clean layout full_page 2>&1 | tail -5
if [ $? -eq 0 ]; then
    echo "✅ Caches cleared"
else
    echo "⚠️ WARNING: Cache clear may have failed"
fi
echo ""

# Step 8: Summary
echo "========================================"
echo "FIX APPLIED SUCCESSFULLY"
echo "========================================"
echo ""
echo "Changes Made:"
echo "  ✅ Added timeout fallback (10 seconds)"
echo "  ✅ Added broken image error handling"
echo "  ✅ Added image count tracking"
echo "  ✅ Added console logging for debugging"
echo "  ✅ Added window load safety"
echo "  ✅ Added back/forward cache handling"
echo ""
echo "Backup Location:"
echo "  $BACKUP_FILE"
echo ""
echo "Next Steps:"
echo "  1. Test on multiple pages with page 2"
echo "  2. Check browser console for logs"
echo "  3. Test with slow network (throttle to 3G)"
echo "  4. Verify loading mask hides properly"
echo ""
echo "Test URLs:"
echo "  https://www.technostationery.com/tous-les-produits.html?p=2"
echo "  https://www.technostationery.com/tous-les-produits/scolaire.html?p=2"
echo ""
echo "Rollback (if needed):"
echo "  cp $BACKUP_FILE $TARGET_FILE"
echo "  php bin/magento cache:clean layout full_page"
echo ""
echo "Monitor:"
echo "  tail -f var/log/system.log | grep -i error"
echo ""
echo "✅ Fix complete - NO DOWNTIME"
