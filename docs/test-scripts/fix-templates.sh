#!/bin/bash
# Fix view_preprocessed template issues

echo "=== Fixing view_preprocessed templates ==="

# Create directory structure and copy critical templates
mkdir -p var/view_preprocessed/pub/static/{vendor,app/code,app/design/frontend}

# Magento core templates
templates=(
    "vendor/magento/module-theme/view/base/templates/root.phtml:vendor/magento/module-theme/view/base/templates/root.phtml"
    "vendor/magento/module-catalog/view/frontend/templates/frontend_storage_manager.phtml:vendor/magento/module-catalog/view/frontend/templates/frontend_storage_manager.phtml"
    "vendor/magento/module-customer/view/frontend/templates/account/authentication-popup.phtml:vendor/magento/module-customer/view/frontend/templates/account/authentication-popup.phtml"
    "vendor/magento/module-customer/view/frontend/templates/js/section-config.phtml:vendor/magento/module-customer/view/frontend/templates/js/section-config.phtml"
    "vendor/magento/module-customer/view/frontend/templates/js/customer-data.phtml:vendor/magento/module-customer/view/frontend/templates/js/customer-data.phtml"
)

for template in "${templates[@]}"; do
    src=$(echo $template | cut -d: -f1)
    dest=$(echo $template | cut -d: -f2)
    if [ -f "$src" ]; then
        target_dir="var/view_preprocessed/pub/static/$(dirname $dest)"
        mkdir -p "$target_dir"
        cp "$src" "var/view_preprocessed/pub/static/$dest" 2>/dev/null
        echo "  ✓ Copied: $dest"
    fi
done

# Sm theme templates
if [ -f "app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/backtotop.phtml" ]; then
    mkdir -p "var/view_preprocessed/pub/static/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html"
    cp "app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/backtotop.phtml" \
       "var/view_preprocessed/pub/static/app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/"
    echo "  ✓ Copied: backtotop.phtml"
fi

# Sm Market footer
if [ -f "app/design/frontend/Sm/market/Sm_Market/templates/html/footer.phtml" ]; then
    mkdir -p "var/view_preprocessed/pub/static/app/design/frontend/Sm/market/Sm_Market/templates/html"
    cp "app/design/frontend/Sm/market/Sm_Market/templates/html/footer.phtml" \
       "var/view_preprocessed/pub/static/app/design/frontend/Sm/market/Sm_Market/templates/html/"
    echo "  ✓ Copied: footer.phtml (Sm/market)"
fi

# Fix permissions
chown -R dev:dev var/view_preprocessed
chmod -R 777 var/view_preprocessed

echo "=== Template fix complete ==="
