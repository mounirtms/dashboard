#!/bin/bash

# Consolidated Optimization Script
# Runs all optimization tasks for the MAB project

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Check if we're in the right directory
if [ ! -f "bin/magento" ]; then
    error "This script must be run from the Magento root directory"
fi

log "Starting consolidated optimization process..."

# 1. Clear all caches
log "Step 1: Clearing all caches..."
php bin/magento cache:clean
php bin/magento cache:flush

# 2. Run Node.js optimization scripts
log "Step 2: Running Node.js optimization scripts..."

# Minify static assets
log "  Minifying static assets..."
npm run minify-static

# Optimize images
log "  Optimizing images..."
npm run resize-images

# 3. Reindex everything
log "Step 3: Reindexing..."
php bin/magento indexer:reindex

# 4. Compile DI
log "Step 4: Compiling dependency injection..."
php bin/magento setup:di:compile

# 5. Deploy static content
log "Step 5: Deploying static content..."
php bin/magento setup:static-content:deploy -f

# 6. Final cache clear
log "Step 6: Final cache clear..."
php bin/magento cache:clean
php bin/magento cache:flush

success "Consolidated optimization completed successfully!"
log "Summary of operations:"
log "  - Cleared caches (2 times)"
log "  - Minified static assets"
log "  - Optimized images"
log "  - Reindexed all indexes"
log "  - Compiled DI"
log "  - Deployed static content"
log "  - Final cache clear"

echo ""
log "Next steps:"
log "  - Test frontend and admin functionality"
log "  - Monitor performance improvements"
log "  - Check error logs for any issues"
```

```
#!/bin/bash

# Consolidated Optimization Script
# Runs all optimization tasks for the MAB project

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Check if we're in the right directory
if [ ! -f "bin/magento" ]; then
    error "This script must be run from the Magento root directory"
fi

log "Starting consolidated optimization process..."

# 1. Clear all caches
log "Step 1: Clearing all caches..."
php bin/magento cache:clean
php bin/magento cache:flush

# 2. Run Node.js optimization scripts
log "Step 2: Running Node.js optimization scripts..."

# Minify static assets
log "  Minifying static assets..."
npm run minify-static

# Optimize images
log "  Optimizing images..."
npm run resize-images

# 3. Reindex everything
log "Step 3: Reindexing..."
php bin/magento indexer:reindex

# 4. Compile DI
log "Step 4: Compiling dependency injection..."
php bin/magento setup:di:compile

# 5. Deploy static content
log "Step 5: Deploying static content..."
php bin/magento setup:static-content:deploy -f

# 6. Final cache clear
log "Step 6: Final cache clear..."
php bin/magento cache:clean
php bin/magento cache:flush

success "Consolidated optimization completed successfully!"
log "Summary of operations:"
log "  - Cleared caches (2 times)"
log "  - Minified static assets"
log "  - Optimized images"
log "  - Reindexed all indexes"
log "  - Compiled DI"
log "  - Deployed static content"
log "  - Final cache clear"

echo ""
log "Next steps:"
log "  - Test frontend and admin functionality"
log "  - Monitor performance improvements"
log "  - Check error logs for any issues"