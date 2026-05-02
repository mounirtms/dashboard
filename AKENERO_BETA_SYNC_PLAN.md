# Akeneo to Beta Catalog Sync Plan
## Date: 2026-05-02

---

## 1. CURRENT STATE

### 1.1 Akeneo PIM (pim.technostationery.com)
- **URL**: https://pim.technostationery.com
- **Database**: `akeneo_pim` (87.8 MB)
- **Elasticsearch Index**: `akeneo_pim_product_and_product_model` (9,956 products)
- **API Available**: Yes (REST API configured)
- **Status**: Working with some UI issues

### 1.2 Beta Magento (beta.technostationery.com)
- **URL**: https://beta.technostationery.com  
- **Database**: `beta_dBT8x12y22` (1,207 MB)
- **Products**: ~1.6M table rows in search index
- **Status**: SUSPENDED - needs unsuspend
- **Current Branch**: `genspark_ai_developer`

### 1.3 Production (technostationery.com)
- **URL**: https://technostationery.com
- **Database**: `technadminy7_dBT8x12y22` (1,054.4 MB)
- **Status**: RUNNING ✅

---

## 2. SYNC STRATEGY

### Option A: API-Based Sync (Recommended)
```
Akeneo PIM (API) → Beta Magento (Import)
- Use Akeneo REST API to get products
- Create Magento 2 import CSV/script
- Schedule via cron
```

### Option B: Database Direct Sync
```
Akeneo DB → Beta DB
- Direct SQL copy of product tables
- Risk: Schema mismatch
- NOT RECOMMENDED
```

### Option C: Elasticsearch Reindex
```
Akeneo ES Index → Beta ES Index
- Reindex products to shared ES
- Complex setup
- NOT RECOMMENDED
```

---

## 3. STEP-BY-STEP PLAN

### PHASE 1: PREPARE (Before any sync)
- [ ] 3.1 Unsuspend beta account
- [ ] 3.2 Test beta site accessibility
- [ ] 3.3 Test Akeneo API access
- [ ] 3.4 Create backup of beta database
- [ ] 3.5 Document current beta product count

### PHASE 2: TEST SYNC (Small test)
- [ ] 2.1 Export 10 products from Akeneo
- [ ] 2.2 Create import format for Magento
- [ ] 2.3 Test import on beta (staging)
- [ ] 2.4 Verify products appear in beta
- [ ] 2.5 Test product page works

### PHASE 3: FULL SYNC (After test success)
- [ ] 3.1 Export all products from Akeneo
- [ ] 3.2 Run import on beta
- [ ] 3.3 Rebuild search index
- [ ] 3.4 Clear cache
- [ ] 3.5 Test checkout flow

### PHASE 4: VERIFY
- [ ] 4.1 Check product count matches
- [ ] 4.2 Test search works
- [ ] 4.3 Test category navigation
- [ ] 4.4 Test add to cart
- [ ] 4.5 Test checkout

---

## 4. COMMANDS REFERENCE

### Test Akeneo API
```bash
# Test PIM API
curl -s -u "akeneo_pim:akeneo_pim" \
  "http://localhost:9200/api/rest/v1/products?limit=10" \
  | head -50

# Or via nginx
curl -s -u "akeneo_pim:akeneo_pim" \
  "https://pim.technostationery.com/api/rest/v1/products?limit=10"
```

### Count Products
```bash
# Akeneo products count
curl -s -u "akeneo_pim:akeneo_pim" \
  "http://localhost:9200/api/rest/v1/products" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['hits']['total'])"

# Beta products count
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22 \
  -e "SELECT COUNT(*) FROM catalog_product_entity;"
```

### Magento Import Command
```bash
# Import products via CSV
cd /home/beta/public_html
php bin/magento import:products:main \
  --source=akeneo \
  --batch-size=1000 \
  --dry-run=1
```

---

## 5. REMOVE OLD CATALOG (Optional)

### Before removing old data, BACKUP FIRST!

```bash
# Backup old products table
mysqldump -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22 \
  catalog_product_entity > /home/backups/beta_products_$(date +%Y%m%d).sql

# Then truncate (WARNING: Data loss!)
# Only after verifying new products work
# TRUNCATE TABLE catalog_product_entity;
```

---

## 6. RULES FOR SAFE SYNC

1. **NEVER touch production directly** - Use beta first
2. **Always backup before changes**
3. **Test with small batch first (10 products)**
4. **Verify each step before proceeding**
5. **Keep rollback plan ready**
6. **Monitor during import**

---

## 7. CLEANUP OLD DATA

After successful sync, remove old catalog data:

```bash
# Remove old indexed data
cd /home/beta/public_html
php bin/magento indexer:reset catalogsearch_fulltext
php bin/magento indexer:reindex catalogsearch_fulltext

# Clear caches
php bin/magento cache:flush

# Verify
php bin/magento indexer:status
```

---

*Created: 2026-05-02*