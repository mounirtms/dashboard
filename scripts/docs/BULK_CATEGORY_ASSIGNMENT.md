# Bulk Category Assignment Solutions

This document describes two approaches for assigning a large number of products to a category in Magento:

## Approach 1: Fixed Node.js Script (REST API)

The original Node.js script had an issue with field naming. The corrected version (`assignProductsToCategoryFixed.js`) properly formats the request data for the Magento REST API.

### Usage:
```bash
node assignProductsToCategoryFixed.js 1798 --file=promo-skus.txt
```

### Options:
- `--dry-run`: Show what would be done without making actual API calls
- `--batch-size N`: Number of products to process at once (default: 1)
- `--retry-attempts N`: Number of retry attempts for failed requests (default: 2)

### Advantages:
- Works with any Magento installation
- No direct database access required
- Can be run remotely

### Disadvantages:
- Slower for large batches due to API limitations
- Subject to API rate limits
- More prone to timeouts

## Approach 2: PHP Script (Direct Database)

The PHP script (`assign-products-to-category-bulk.php`) directly inserts records into the database, bypassing the API entirely.

### Usage:
```bash
# Dry run first to see what would be done
php assign-products-to-category-bulk.php --category-id=1798 --file=promo-skus.txt --dry-run

# Actually perform the assignment
php assign-products-to-category-bulk.php --category-id=1798 --file=promo-skus.txt
```

### Advantages:
- Much faster for large batches
- No API rate limits
- More reliable for bulk operations
- Automatically reindexes and flushes cache

### Disadvantages:
- Requires server access
- Requires Magento CLI access
- Bypasses some business logic

## Recommendation

For bulk assignments of more than 10 products:
1. Use the PHP script (Approach 2)
2. Always run with `--dry-run` first to verify what will be done
3. Run during low-traffic periods

For smaller batches or remote operations:
1. Use the Node.js script (Approach 1)
2. Use a smaller batch size (1-5 products)
3. Increase retry attempts if needed

## Error Handling

Both scripts will:
- Report products that don't exist
- Skip products already assigned to the category
- Save failed assignments to a file for review
- Handle API timeouts with retry logic

## Post-Assignment Steps

After running either script, you may want to:
1. Check the frontend to verify products appear in the category
2. Run a full reindex if needed:
   ```bash
   php bin/magento indexer:reindex
   ```
3. Clear cache:
   ```bash
   php bin/magento cache:flush
   ```