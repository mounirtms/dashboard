# TEST SESSIONS PLAN
## Date: 2026-05-02 | Status: Ready for Testing

---

## 1. CREDENTIALS REFERENCE

### 1.1 Akeneo PIM API Connector
| Field | Value |
|-------|-------|
| **Username** | `apiconnector` |
| **Password** | `ApiConnector@2026!Secure` |
| **OAuth Client ID** | `2_ml5f52erhggg0s484gckwgs4kg8gwc4c48ksgko4gkgos4k48` |
| **OAuth Secret** | `1zniz3jfcmcgg0wckskw8k4c80ccwc4o0cokcwk80cs8cs0cs4` |

### 1.2 Magento Beta Admin
| Field | Value |
|-------|-------|
| **Username** | `bot` |
| **Password** | `@dM1n$#@2o25B0T` |
| **URL** | `https://beta.technostationery.com/sysadminy/` |

### 1.3 Database Access
| Service | Command |
|---------|---------|
| **Production** | `/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22` |
| **Beta** | `/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22` |
| **PIM** | `/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 akeneo_pim` |

---

## 2. SYSTEM URLs

| Service | URL | Status |
|---------|-----|--------|
| **Akeneo PIM** | https://pim.technostationery.com | ⚠️ 500 Error |
| **Magento Beta** | https://beta.technostationery.com | ✅ Working |
| **Production** | https://technostationery.com | ✅ Working |

---

## 3. TEST SESSION CHECKLIST

### 3.1 Beta Magento Tests ✅ Ready
- [ ] Test homepage loads: `https://beta.technostationery.com/`
- [ ] Test admin login: `https://beta.technostationery.com/sysadminy/` with `bot` / `@dM1n$#@2o25B0T`
- [ ] Test catalog search
- [ ] Test product page
- [ ] Test add to cart
- [ ] Test checkout flow

### 3.2 Akeneo PIM Tests ⚠️ Need Fix First
- [ ] Test PIM homepage: `https://pim.technostationery.com`
- [ ] Test API: `curl -s -u "apiconnector:ApiConnector@2026!Secure" https://pim.technostationery.com/api/rest/v1/products?limit=1`
- [ ] Test authentication
- [ ] Verify products count

### 3.3 Elasticsearch Tests ✅
- [ ] Test cluster health: `curl -s localhost:9200/_cluster/health`
- [ ] Test indices: `curl -s localhost:9200/_cat/indices?v`

### 3.4 Dashboard Tests ✅
- [ ] Test processes: `curl "https://dashboard.technostationery.com/api/monitor.php?action=processes&sort=mem&limit=20"`
- [ ] Test services: `curl "https://dashboard.technostationery.com/api/monitor.php?action=services"`
- [ ] Test Cloudflare: `curl "https://dashboard.technostationery.com/api/monitor.php?action=cloudflare"`
- [ ] Test dbhealth: `curl "https://dashboard.technostationery.com/api/monitor.php?action=dbhealth"`

---

## 4. CATALOG SYNC TESTS

### 4.1 Before Sync
```bash
# Count products in Akeneo
curl -s -u "apiconnector:ApiConnector@2026!Secure" \
  "https://pim.technostationery.com/api/rest/v1/products" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['hits']['total'])"

# Count products in Beta
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 beta_dBT8x12y22 \
  -e "SELECT COUNT(*) FROM catalog_product_entity"
```

### 4.2 After Sync (Plan)
```bash
# Rebuild search index
cd /home/beta/public_html
php bin/magento indexer:reindex catalogsearch_fulltext

# Clear cache
php bin/magento cache:flush
```

---

## 5. BRANCH CLEANUP PLAN

### 5.1 Beta Branches
- `main` - Old code (backup)
- `genspark_ai_developer` - Current working
- `betabranch` - Old branch
- `dev` - Development

### 5.2 PIM Branches  
- `main` - Old code
- `pimAkeno` - Current working
- `pimAkeno-clean` - Backup
- `feature/system-improvements` - Future

### 5.3 Dashboard Branches
- `main` - Production ready
- `oldchanges` - Current working
- `feature/monitor-updates` - New features

---

## 6. COMMANDS CHEAT SHEET

### Test Beta API
```bash
# Test connection
curl -s -o /dev/null -w "%{http_code}" "https://beta.technostationery.com/"

# Test admin
curl -s -o /dev/null -w "%{http_code}" "https://beta.technostationery.com/sysadminy/"

# Test database
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 beta_dBT8x12y22 \
  -e "SELECT COUNT(*) as products FROM catalog_product_entity"
```

### Test PIM API
```bash
# Test homepage (broken)
curl -s -o /dev/null -w "%{http_code}" "https://pim.technostationery.com/"

# Test API (broken)
curl -s -u "apiconnector:ApiConnector@2026!Secure" \
  "https://pim.technostationery.com/api/rest/v1/products?limit=1"
```

### Test Elasticsearch
```bash
# Cluster health
curl -s localhost:9200/_cluster/health | python3 -m json.tool

# List indices
curl -s localhost:9200/_cat/indices?v
```

### Test Dashboard
```bash
# All services
curl "https://dashboard.technostationery.com/api/monitor.php?action=services" | python3 -m json.tool

# Database health
curl "https://dashboard.technostationery.com/api/monitor.php?action=dbhealth" | python3 -m json.tool
```

---

## 7. RULES FOR TESTING

1. **NEVER test on production** - Always use beta
2. **Test small batch first** - Don't import all products at once
3. **Verify each step** - Check results before proceeding  
4. **Keep rollback plan** - Have backup ready
5. **Document results** - Write down what works and what doesn't

---

*Created: 2026-05-02*