# Bulk Product to Category Assignment

This directory contains two solutions for assigning products to categories in bulk:

## Files

1. `assignProductsToCategoryFixed.js` - Fixed Node.js script using REST API
2. `assign-products-to-category-bulk.php` - PHP script using direct database insertion
3. `promo-skus.txt` - List of SKUs to assign to category 1798
4. `test-skus.txt` - Small test set of SKUs
5. `BULK_CATEGORY_ASSIGNMENT.md` - Detailed documentation

## Problem Fixed

The original Node.js script was failing with HTTP 500 errors due to incorrect field naming in the product update request. The error was:
```
Property "CategoryIds" does not have accessor method "getCategoryIds" in class "Magento\Catalog\Api\Data\ProductInterface"
```

This was fixed by properly formatting the request data to use `category_ids` (lowercase with underscore) instead of `CategoryIds`.

## Recommended Approach

For assigning 145 products to category 1798:

### Option 1: Use the PHP script (Recommended - faster and more reliable)
```bash
# First, do a dry run to see what would happen
php assign-products-to-category-bulk.php --category-id=1798 --file=promo-skus.txt --dry-run

# Then run for real
php assign-products-to-category-bulk.php --category-id=1798 --file=promo-skus.txt
```

### Option 2: Use the fixed Node.js script
```bash
node assignProductsToCategoryFixed.js 1798 --file=promo-skus.txt --batch-size=5 --retry-attempts=3
```

## After Running

After either approach, flush the cache and reindex:
```bash
php bin/magento cache:flush
php bin/magento indexer:reindex
```