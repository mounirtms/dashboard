# Comprehensive Project Audit & Task Plan
## Date: 2026-05-02

---

## 1. CURRENT STATE SUMMARY

### 1.1 Production (technostationery.com)
- **Status**: RUNNING ✅
- **Disk**: 191.5 GB used
- **cPanel**: Active
- **Branch**: main

### 1.2 Beta (beta.technostationery.com)
- **Status**: SUSPENDED ⚠️
- **Disk**: 28.6 GB used
- **cPanel**: Suspended (suspended reason: sd)
- **Current Branch**: `genspark_ai_developer`
- **Main Branch**: `main`
- **Changes**: 10,504 files changed, +55,943 / -346,697 lines

### 1.3 Akeneo PIM (pim.technostationery.com)
- **Status**: Running ⚠️ (UI issues)
- **Current Branch**: `pimAkeno`
- **Main Branch**: `main`
- **Changes**: 463 files changed, +63,715 / -35,808 lines
- **Elasticsearch**: Yellow status (2 unassigned shards)

---

## 2. GITHUB BRANCHES

### Dashboard (this project)
```
main                      (6682c97e)
oldchanges (HEAD)        (40850d26) ✅ Working
feature/server-management (7a37e297)
release                 (f876e6d5)
```

### Beta Project (/home/beta/public_html)
```
main                      - Old code
genspark_ai_developer    - Current working branch ✅
betabranch              - Old branch
dev                     - Development
oldbetbranch-working    - Backup
```

### PIM Project (/home/pim/public_html)
```
main                      - Old code
pimAkeno (HEAD)         - Current working branch
pimAkeno-clean         - Clean backup
feature/system-improvements - Future features
oldbranch               - Backup
```

---

## 3. ISSUES FOUND

### 3.1 Elasticsearch - CRITICAL ⚠️
```
Status: YELLOW (not green!)
Unassigned shards: 2
Indices:
- techno_stationery_product_1_v92: YELLOW (rep=1, needs replica)
- beta_techno_stationery_product_1_v20: YELLOW (rep=1)
```

### 3.2 Beta Project Issues
- 10,504 files in diff - massive changes
- Large deletion count (-346,697 lines)
- Branch conflicts likely with main
- cPanel suspended - needs unsuspend

### 3.3 PIM Project Issues
- UI loading screen issues
- CSS 404 errors (partially fixed)
- Authentication issues
- 463 files changed

---

## 4. STEP-BY-STEP FIX PLAN

### PHASE 1: PREPARE & BACKUP (Before ANY changes)
- [ ] 1.1 Full backup of all three projects
- [ ] 1.2 Create backup tags in git
- [ ] 1.3 Document current state
- [ ] 1.4 Create rollback plan

### PHASE 2: ELASTICSEARCH FIX (CRITICAL)
- [ ] 2.1 Fix yellow status
- [ ] 2.2 Address unassigned shards
- [ ] 2.3 Reindex if needed
- [ ] 2.4 Verify green status

### PHASE 3: BETA PROJECT CLEANUP
- [ ] 3.1 Review branch changes on local
- [ ] 3.2 Identify conflicts with main
- [ ] 3.3 Create clean feature branch
- [ ] 3.4 Merge/cherry-pick essential changes
- [ ] 3.5 Test without breaking main
- [ ] 3.6 Unsuspend via WHM after testing

### PHASE 4: PIM PROJECT CLEANUP  
- [ ] 4.1 Review branch changes on local
- [ ] 4.2 Identify conflicts with main
- [ ] 4.3 Fix UI loading issues
- [ ] 4.4 Fix authentication
- [ ] 4.5 Verify Elasticsearch connection

### PHASE 5: DASHBOARD ENHANCEMENTS
- [ ] 5.1 Add beta site monitoring
- [ ] 5.2 Add PIM site monitoring
- [ ] 5.3 Create connector script

### PHASE 6: GITHUB CLEANUP
- [ ] 6.1 Clean up branches
- [ ] 6.2 Push working code
- [ ] 6.3 Document commits

---

## 5. COMMANDS REFERENCE

### Database Access
```bash
# Production
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22

# Beta
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22

# PIM
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 akeneo_pim
```

### Elasticsearch
```bash
# Health check
curl -s localhost:9200/_cluster/health

# List indices
curl -s localhost:9200/_cat/indices?v

# Fix yellow: set replicas to 0 for single node
curl -X PUT "localhost:9200/techno_stationery_product_1_v92/_settings" -H 'Content-Type: application/json' -d '{"index":{"number_of_replicas":0}}'
```

### Beta Project
```bash
cd /home/beta/public_html
git checkout main
git pull origin main
git checkout -b clean-feature
# Then cherry-pick essential commits
git cherry-pick <commit-hash>
```

### PIM Project
```bash
cd /home/pim/public_html
git checkout main
git pull origin main
# Test UI
curl -s http://localhost/pim/ | head -20
```

### Unsuspend Beta
```bash
# Via WHM API
whmapi1 unsuspendaccount user=beta

# Or via cPanel
uapi --user=beta Beta unsuspend
```

---

## 6. RULES FOR NO DOWNTIME

1. **NEVER touch production directly**
2. **Always test on beta first**
3. **Keep backup of working code**
4. **Rollback plan before any change**
5. **Monitor during changes**
6. **Have emergency contacts ready**

---

## 7. PRIORITY ORDER

| Priority | Task | Reason |
|----------|------|--------|
| 1 | Fix Elasticsearch | Data loss risk |
| 2 | Backup all projects | Safety first |
| 3 | Document current state | Reference |
| 4 | Clean beta branch | Reduce conflicts |
| 5 | Fix PIM UI | Working PIM |
| 6 | Unsuspend beta | Go live |
| 7 | Push to github | Cleanup |

---

*Created: 2026-05-02*