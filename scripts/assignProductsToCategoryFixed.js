// Required libraries
const fs = require('fs');
const axios = require('axios');
const { getMagentoAdminToken, getMagentoBaseUrl } = require('./magentoTokenUtils'); // Token management

// Magento API Configuration
const MAGENTO_BASE_URL = getMagentoBaseUrl() + '/rest/V1';

/**
 * Assign products to a specific category by SKU
 * @param {Array} skus - Array of product SKUs to assign
 * @param {Number} categoryId - The category ID to assign products to
 * @param {String} token - Magento admin token
 * @param {Object} options - Options for assignment
 */
async function assignProductsToCategory(skus, categoryId, token, options = {}) {
    if (!skus || skus.length === 0) {
        console.log(`No SKUs provided for category ${categoryId}.`);
        return;
    }

    const endpointBase = `${MAGENTO_BASE_URL}/products`;
    const skipApi = process.env.SKIP_API === '1' || options.skipApi;
    const batchSize = options.batchSize || 1; // Process one at a time to minimize errors
    const retryAttempts = options.retryAttempts || 2; // Reduced retry attempts
    const failedSkus = []; // Track failed SKUs

    console.log(`Assigning ${skus.length} SKUs to category ${categoryId} (skipApi=${skipApi})`);

    // Process in batches
    for (let i = 0; i < skus.length; i += batchSize) {
        const batch = skus.slice(i, i + batchSize);
        console.log(`Processing batch ${Math.floor(i/batchSize) + 1}/${Math.ceil(skus.length/batchSize)} (${batch.length} items)`);

        // Process each SKU in the batch
        for (const sku of batch) {
            try {
                if (skipApi) {
                    console.log(`[DRY RUN] Would assign SKU ${sku} -> category ${categoryId}`);
                    continue;
                }

                const url = `${endpointBase}/${encodeURIComponent(sku)}`;
                console.log(`Processing SKU: ${sku}`);

                // Get the existing product with retry logic
                let getResp;
                let getProductSuccess = false;
                
                for (let attempt = 1; attempt <= retryAttempts; attempt++) {
                    try {
                        console.log(`  Fetching product data (attempt ${attempt}/${retryAttempts})`);
                        getResp = await axios.get(url, {
                            headers: { 
                                Authorization: `Bearer ${token}`, 
                                Accept: 'application/json' 
                            },
                            timeout: 45000 // Increased timeout to 45 seconds
                        });
                        getProductSuccess = true;
                        console.log(`  Successfully fetched product data for ${sku}`);
                        break;
                    } catch (getErr) {
                        console.log(`  Failed to fetch product data (attempt ${attempt}/${retryAttempts}):`, getErr.message);
                        if (attempt === retryAttempts) {
                            throw getErr; // Re-throw on final attempt
                        }
                        // Wait before retry
                        await new Promise(resolve => setTimeout(resolve, 2000 * attempt));
                    }
                }

                if (!getProductSuccess) {
                    console.error(`Failed to fetch product ${sku} after ${retryAttempts} attempts`);
                    failedSkus.push({sku, reason: 'Failed to fetch product data'});
                    continue;
                }

                const existingProduct = getResp.data && getResp.data.product ? getResp.data.product : getResp.data;
                const productObj = Object.assign({}, existingProduct);
                
                // Log current categories
                const existingCats = (productObj.category_ids || []).map(String);
                console.log(`  Current categories: [${existingCats.join(', ')}]`);
                
                // Merge category IDs ensuring no duplicates
                const merged = Array.from(new Set([...existingCats, String(categoryId)]));
                productObj.category_ids = merged;
                console.log(`  New categories: [${merged.join(', ')}]`);

                // Update the product with new category assignment with retry logic
                let putResp;
                let updateProductSuccess = false;
                
                for (let attempt = 1; attempt <= retryAttempts; attempt++) {
                    try {
                        console.log(`  Updating product (attempt ${attempt}/${retryAttempts})`);
                        // Fix the field name to be "category_ids" instead of "CategoryIds"
                        const updateData = {
                            product: {
                                sku: productObj.sku,
                                category_ids: productObj.category_ids
                            }
                        };
                        
                        putResp = await axios.put(url, updateData, {
                            headers: {
                                Authorization: `Bearer ${token}`,
                                'Content-Type': 'application/json',
                                Accept: 'application/json'
                            },
                            timeout: 45000 // Increased timeout to 45 seconds
                        });
                        updateProductSuccess = true;
                        console.log(`✓ SKU ${sku} assigned to category ${categoryId}: HTTP ${putResp.status}`);
                        break;
                    } catch (putErr) {
                        const status = putErr.response ? putErr.response.status : null;
                        console.log(`  Failed to update product (attempt ${attempt}/${retryAttempts}): HTTP ${status}`);
                        
                        // If it's a client error (4xx), don't retry
                        if (status && status >= 400 && status < 500) {
                            console.error(`✗ Failed to assign category ${categoryId} to SKU ${sku}: HTTP ${status}`, putErr.response ? putErr.response.data : putErr.message);
                            failedSkus.push({sku, reason: `Client error: ${status}`, details: putErr.response ? putErr.response.data : putErr.message});
                            break;
                        }
                        
                        if (attempt === retryAttempts) {
                            console.error(`✗ Failed to assign category ${categoryId} to SKU ${sku} after ${retryAttempts} attempts: HTTP ${status}`, putErr.response ? putErr.response.data : putErr.message);
                            failedSkus.push({sku, reason: `Server error after ${retryAttempts} attempts: ${status}`, details: putErr.response ? putErr.response.data : putErr.message});
                            break;
                        }
                        
                        // Wait before retry with exponential backoff
                        await new Promise(resolve => setTimeout(resolve, 3000 * attempt));
                    }
                }
            } catch (error) {
                console.error(`Unexpected error assigning category ${categoryId} to SKU ${sku}:`, error.message);
                failedSkus.push({sku, reason: 'Unexpected error', details: error.message});
            }
        }

        // Add a longer delay between batches to avoid overwhelming the API
        if (i + batchSize < skus.length && !skipApi) {
            console.log('Waiting 5 seconds before next item...');
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
    }
    
    // Summary
    console.log('\n=== ASSIGNMENT SUMMARY ===');
    console.log(`Total SKUs processed: ${skus.length}`);
    console.log(`Successfully assigned: ${skus.length - failedSkus.length}`);
    console.log(`Failed assignments: ${failedSkus.length}`);
    
    if (failedSkus.length > 0) {
        console.log('\nFailed SKUs:');
        failedSkus.forEach(({sku, reason, details}) => {
            console.log(`  - ${sku}: ${reason}`);
            if (details) {
                console.log(`    Details: ${JSON.stringify(details)}`);
            }
        });
        
        // Save failed SKUs to a file
        try {
            const failedSkusFile = `failed_category_${categoryId}_assignments.json`;
            fs.writeFileSync(failedSkusFile, JSON.stringify(failedSkus, null, 2));
            console.log(`\nFailed assignments saved to: ${failedSkusFile}`);
        } catch (writeErr) {
            console.error('Failed to write failed SKUs to file:', writeErr.message);
        }
    }
}

/**
 * Load SKUs from a JSON file
 * @param {String} filePath - Path to the JSON file containing SKUs
 * @returns {Array} Array of SKUs
 */
function loadSkusFromFile(filePath) {
    try {
        const data = fs.readFileSync(filePath, 'utf8');
        const skus = JSON.parse(data);
        if (!Array.isArray(skus)) {
            throw new Error('JSON file must contain an array of SKUs');
        }
        return skus;
    } catch (error) {
        console.error(`Error loading SKUs from file ${filePath}:`, error.message);
        return [];
    }
}

/**
 * Main function to run the script
 */
async function main() {
    // Get command line arguments
    const args = process.argv.slice(2);
    
    if (args.length < 2) {
        console.log('Usage: node assignProductsToCategoryFixed.js <categoryId> <sku1,sku2,...> [options]');
        console.log('   or: node assignProductsToCategoryFixed.js <categoryId> --file=<filePath> [options]');
        console.log('');
        console.log('Options:');
        console.log('  --dry-run           Run without making actual API calls');
        console.log('  --batch-size        Number of products to process at once (default: 5)');
        console.log('  --retry-attempts    Number of retry attempts for failed requests (default: 3)');
        console.log('');
        console.log('Examples:');
        console.log('  node assignProductsToCategoryFixed.js 15 sku1,sku2,sku3');
        console.log('  node assignProductsToCategoryFixed.js 15 --file=./skus.json');
        console.log('  node assignProductsToCategoryFixed.js 15 sku1,sku2 --dry-run');
        return;
    }

    const categoryId = args[0];
    let skus = [];
    
    // Check if SKUs are provided directly or from a file
    if (args[1].startsWith('--file=')) {
        const filePath = args[1].substring(7);
        skus = loadSkusFromFile(filePath);
    } else {
        skus = args[1].split(',');
    }
    
    // Parse options
    const options = {};
    if (args.includes('--dry-run')) {
        options.skipApi = true;
    }
    
    const batchSizeIndex = args.indexOf('--batch-size');
    if (batchSizeIndex !== -1 && batchSizeIndex + 1 < args.length) {
        options.batchSize = parseInt(args[batchSizeIndex + 1]);
    }
    
    const retryAttemptsIndex = args.indexOf('--retry-attempts');
    if (retryAttemptsIndex !== -1 && retryAttemptsIndex + 1 < args.length) {
        options.retryAttempts = parseInt(args[retryAttemptsIndex + 1]);
    }

    // Validate inputs
    if (!categoryId) {
        console.error('Error: Category ID is required');
        return;
    }

    if (!skus || skus.length === 0) {
        console.error('Error: At least one SKU is required');
        return;
    }

    // Get Magento token
    let token = null;
    const skipApi = process.env.SKIP_API === '1' || options.skipApi;
    
    if (!skipApi) {
        try {
            token = await getMagentoAdminToken();
            console.log('Magento token retrieved successfully.');
        } catch (error) {
            console.error('Failed to retrieve Magento token:', error.message);
            return;
        }
    } else {
        console.log('Running in dry-run mode (no Magento API calls).');
    }

    // Assign products to category
    try {
        await assignProductsToCategory(skus, categoryId, token, options);
        console.log('Category assignment process completed.');
    } catch (err) {
        console.error('Error during category assignment:', err.message);
    }
}

// Run the script if this file is executed directly
if (require.main === module) {
    main();
}

module.exports = {
    assignProductsToCategory,
    loadSkusFromFile
};