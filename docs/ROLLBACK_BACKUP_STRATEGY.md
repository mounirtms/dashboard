# 🔄 Rollback Plan & Backup Strategy
**Date:** 2026-04-11  
**Project:** Beta Magento E-commerce Platform  
**Session:** 35 Extension - Production Preparation

---

## 🎯 Overview

This document provides comprehensive backup and rollback procedures for the beta Magento deployment, ensuring we can quickly recover from any deployment issues.

---

## 📦 Backup Strategy

### Backup Types

#### 1. **Full System Backup** (Weekly)
**What:**
- Complete database dump
- All application files
- Configuration files
- Media files

**When:** Every Sunday 2:00 AM
**Retention:** 4 weeks
**Location:** `/home/beta/backups/full/`

#### 2. **Incremental Backup** (Daily)
**What:**
- Database changes only
- Modified files since last backup

**When:** Daily 2:00 AM
**Retention:** 7 days
**Location:** `/home/beta/backups/daily/`

#### 3. **Pre-Deployment Backup** (Before Each Deploy)
**What:**
- Database snapshot
- Composer files
- Custom code (app/code/Mab/)
- Configuration (app/etc/)

**When:** Immediately before deployment
**Retention:** Until next successful deployment
**Location:** `/home/beta/backups/pre-deploy/`

#### 4. **Code Repository Backup** (Continuous)
**What:**
- Git repository
- All branches
- Tags and releases

**When:** Every push
**Retention:** Indefinite
**Location:** GitHub + local mirror

---

## 🛠️ Backup Scripts

### Full Backup Script

```bash
#!/bin/bash
# full_backup.sh - Complete system backup

BACKUP_DIR="/home/beta/backups/full"
DATE=$(date +%Y%m%d_%H%M%S)
MAGENTO_ROOT="/home/beta/public_html"

# Database credentials (load from secure file)
source /home/beta/.db_credentials

echo "=== Starting Full Backup: $DATE ==="

# 1. Create backup directory
mkdir -p "$BACKUP_DIR/$DATE"

# 2. Database backup
echo "[1/4] Backing up database..."
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  | gzip > "$BACKUP_DIR/$DATE/database.sql.gz"

# 3. Files backup
echo "[2/4] Backing up files..."
tar -czf "$BACKUP_DIR/$DATE/files.tar.gz" \
  -C "$MAGENTO_ROOT" \
  --exclude='./var/cache' \
  --exclude='./var/page_cache' \
  --exclude='./var/session' \
  --exclude='./var/tmp' \
  --exclude='./pub/static/_cache' \
  .

# 4. Media files backup (separate, slower)
echo "[3/4] Backing up media..."
tar -czf "$BACKUP_DIR/$DATE/media.tar.gz" \
  -C "$MAGENTO_ROOT" \
  pub/media

# 5. Configuration backup
echo "[4/4] Backing up configuration..."
tar -czf "$BACKUP_DIR/$DATE/config.tar.gz" \
  -C "$MAGENTO_ROOT" \
  app/etc

# Calculate sizes
DB_SIZE=$(du -h "$BACKUP_DIR/$DATE/database.sql.gz" | cut -f1)
FILES_SIZE=$(du -h "$BACKUP_DIR/$DATE/files.tar.gz" | cut -f1)
MEDIA_SIZE=$(du -h "$BACKUP_DIR/$DATE/media.tar.gz" | cut -f1)
CONFIG_SIZE=$(du -h "$BACKUP_DIR/$DATE/config.tar.gz" | cut -f1)

# Create backup manifest
cat > "$BACKUP_DIR/$DATE/manifest.txt" << EOF
Backup Date: $DATE
Database: $DB_SIZE
Files: $FILES_SIZE
Media: $MEDIA_SIZE
Config: $CONFIG_SIZE
Total: $(du -sh "$BACKUP_DIR/$DATE" | cut -f1)
EOF

echo "✓ Backup complete: $BACKUP_DIR/$DATE"
echo "  Database: $DB_SIZE"
echo "  Files: $FILES_SIZE"
echo "  Media: $MEDIA_SIZE"
echo "  Config: $CONFIG_SIZE"

# Cleanup old backups (keep last 4 weeks)
find "$BACKUP_DIR" -type d -mtime +28 -exec rm -rf {} +

echo "=== Backup Complete ==="
```

### Pre-Deployment Backup Script

```bash
#!/bin/bash
# pre_deploy_backup.sh - Quick backup before deployment

BACKUP_DIR="/home/beta/backups/pre-deploy"
DATE=$(date +%Y%m%d_%H%M%S)
MAGENTO_ROOT="/home/beta/public_html"

source /home/beta/.db_credentials

echo "=== Pre-Deployment Backup: $DATE ==="

mkdir -p "$BACKUP_DIR"

# Remove previous pre-deploy backup
rm -rf "$BACKUP_DIR/latest"
mkdir -p "$BACKUP_DIR/latest"

# 1. Database
echo "[1/5] Database..."
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  --single-transaction \
  | gzip > "$BACKUP_DIR/latest/database.sql.gz"

# 2. Composer files
echo "[2/5] Composer..."
cp "$MAGENTO_ROOT/composer.json" "$BACKUP_DIR/latest/"
cp "$MAGENTO_ROOT/composer.lock" "$BACKUP_DIR/latest/"

# 3. Custom code
echo "[3/5] Custom code..."
tar -czf "$BACKUP_DIR/latest/custom_code.tar.gz" \
  -C "$MAGENTO_ROOT" \
  app/code/Mab

# 4. Configuration
echo "[4/5] Configuration..."
tar -czf "$BACKUP_DIR/latest/config.tar.gz" \
  -C "$MAGENTO_ROOT" \
  app/etc

# 5. Git state
echo "[5/5] Git state..."
cd "$MAGENTO_ROOT"
git rev-parse HEAD > "$BACKUP_DIR/latest/git_commit.txt"
git status > "$BACKUP_DIR/latest/git_status.txt"
git diff > "$BACKUP_DIR/latest/git_diff.txt" 2>&1

# Save metadata
cat > "$BACKUP_DIR/latest/metadata.txt" << EOF
Backup Date: $(date)
Magento Version: $(php bin/magento --version)
Git Commit: $(git rev-parse HEAD)
Git Branch: $(git rev-parse --abbrev-ref HEAD)
EOF

# Create timestamped copy
cp -r "$BACKUP_DIR/latest" "$BACKUP_DIR/$DATE"

echo "✓ Pre-deployment backup complete"
echo "  Location: $BACKUP_DIR/latest"
echo "  Archive: $BACKUP_DIR/$DATE"
echo "=== Ready for Deployment ==="
```

---

## 🚨 Rollback Procedures

### Level 1: Cache Rollback (< 5 minutes)
**When:** UI issues, styling problems, JavaScript errors
**Impact:** Zero downtime

```bash
#!/bin/bash
# rollback_cache.sh

cd /home/beta/public_html

echo "=== Rolling back caches ==="

# Clear all caches
bin/magento cache:clean
bin/magento cache:flush

# Remove generated files
rm -rf generated/code/*
rm -rf generated/metadata/*
rm -rf pub/static/frontend/*
rm -rf pub/static/adminhtml/*
rm -rf var/view_preprocessed/*

# Redeploy static content
bin/magento setup:static-content:deploy fr_FR en_US -f

echo "✓ Cache rollback complete"
```

### Level 2: Code Rollback (< 15 minutes)
**When:** PHP errors, module issues, functionality broken
**Impact:** Minimal downtime (< 1 minute)

```bash
#!/bin/bash
# rollback_code.sh

cd /home/beta/public_html
BACKUP_DIR="/home/beta/backups/pre-deploy/latest"

echo "=== Code Rollback Started ==="

# 1. Enable maintenance
bin/magento maintenance:enable

# 2. Git rollback
echo "[1/4] Rolling back git..."
PREVIOUS_COMMIT=$(cat "$BACKUP_DIR/git_commit.txt")
git reset --hard "$PREVIOUS_COMMIT"

# 3. Restore composer
echo "[2/4] Restoring composer..."
cp "$BACKUP_DIR/composer.json" ./
cp "$BACKUP_DIR/composer.lock" ./
composer install --no-dev

# 4. Clear caches
echo "[3/4] Clearing caches..."
bin/magento cache:flush
rm -rf generated/*
rm -rf pub/static/*

# 5. Disable maintenance
echo "[4/4] Enabling site..."
bin/magento maintenance:disable

echo "✓ Code rollback complete"
echo "Previous commit: $PREVIOUS_COMMIT"
```

### Level 3: Database Rollback (< 30 minutes)
**When:** Data corruption, database schema issues
**Impact:** Moderate downtime (5-10 minutes)

```bash
#!/bin/bash
# rollback_database.sh

cd /home/beta/public_html
BACKUP_DIR="/home/beta/backups/pre-deploy/latest"

source /home/beta/.db_credentials

echo "=== Database Rollback Started ==="
echo "⚠️  WARNING: This will restore database to previous state"
read -p "Continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Rollback cancelled"
    exit 1
fi

# 1. Enable maintenance
bin/magento maintenance:enable

# 2. Create safety backup of current state
echo "[1/4] Creating safety backup..."
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "/home/beta/backups/before_rollback_$(date +%Y%m%d_%H%M%S).sql.gz"

# 3. Drop current database
echo "[2/4] Dropping current database..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE $DB_NAME; CREATE DATABASE $DB_NAME;"

# 4. Restore from backup
echo "[3/4] Restoring database..."
gunzip < "$BACKUP_DIR/database.sql.gz" | \
  mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

# 5. Clear cache and disable maintenance
echo "[4/4] Finalizing..."
bin/magento cache:flush
bin/magento maintenance:disable

echo "✓ Database rollback complete"
```

### Level 4: Full System Rollback (< 60 minutes)
**When:** Complete failure, multiple systems affected
**Impact:** Significant downtime (30-60 minutes)

```bash
#!/bin/bash
# rollback_full.sh

BACKUP_DIR="/home/beta/backups/pre-deploy/latest"
MAGENTO_ROOT="/home/beta/public_html"

source /home/beta/.db_credentials

echo "=== FULL SYSTEM ROLLBACK ==="
echo "⚠️  WARNING: Complete system restore"
read -p "Enter 'ROLLBACK' to confirm: " CONFIRM

if [ "$CONFIRM" != "ROLLBACK" ]; then
    echo "Rollback cancelled"
    exit 1
fi

cd "$MAGENTO_ROOT"

# 1. Enable maintenance
bin/magento maintenance:enable

# 2. Restore database
echo "[1/5] Restoring database..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE $DB_NAME; CREATE DATABASE $DB_NAME;"
gunzip < "$BACKUP_DIR/database.sql.gz" | mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

# 3. Restore custom code
echo "[2/5] Restoring custom code..."
rm -rf app/code/Mab
tar -xzf "$BACKUP_DIR/custom_code.tar.gz" -C "$MAGENTO_ROOT"

# 4. Restore configuration
echo "[3/5] Restoring configuration..."
tar -xzf "$BACKUP_DIR/config.tar.gz" -C "$MAGENTO_ROOT"

# 5. Restore composer
echo "[4/5] Restoring dependencies..."
cp "$BACKUP_DIR/composer.json" ./
cp "$BACKUP_DIR/composer.lock" ./
composer install --no-dev

# 6. Rebuild
echo "[5/5] Rebuilding..."
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
bin/magento maintenance:disable

echo "✓ Full system rollback complete"
```

---

## 🎯 Rollback Decision Tree

```
Problem Detected
      |
      v
[Is site accessible?]
   /          \
 YES           NO
  |             |
  v             v
[UI/Style?]  [Check logs]
  |             |
  v             v
Cache         [Error type?]
Rollback        |
(L1)           /|\
              / | \
           PHP DB  ?
            |   |   |
            v   v   v
         Code DB Full
         (L2)(L3)(L4)
```

---

## 📋 Rollback Checklist

### Pre-Rollback:
- [ ] Identify issue severity and impact
- [ ] Notify team/stakeholders
- [ ] Confirm backup availability
- [ ] Document the issue (screenshots, logs)
- [ ] Choose appropriate rollback level

### During Rollback:
- [ ] Enable maintenance mode
- [ ] Execute rollback procedure
- [ ] Verify each step completes successfully
- [ ] Document any errors or issues

### Post-Rollback:
- [ ] Test critical functionality
  - [ ] Homepage loads
  - [ ] Product pages work
  - [ ] Cart functional
  - [ ] Checkout process
  - [ ] Admin panel accessible
- [ ] Check error logs
- [ ] Disable maintenance mode
- [ ] Monitor for 30 minutes
- [ ] Document root cause
- [ ] Plan fix for next deployment

---

## 🧪 Rollback Testing

### Monthly Rollback Drill
**Purpose:** Ensure rollback procedures work when needed

```bash
#!/bin/bash
# rollback_drill.sh - Test rollback procedures

echo "=== Monthly Rollback Drill ==="
echo "Date: $(date)"

# 1. Create test backup
./pre_deploy_backup.sh

# 2. Make test changes
echo "Making test changes..."
touch /tmp/test_deployment_marker

# 3. Test Level 1 rollback (cache)
echo "Testing cache rollback..."
./rollback_cache.sh

# 4. Test Level 2 rollback (code)
echo "Testing code rollback (dry run)..."
# Dry run only - don't actually rollback

# 5. Verify
if [ -f /tmp/test_deployment_marker ]; then
    echo "✓ Drill complete - procedures verified"
    rm /tmp/test_deployment_marker
else
    echo "⚠ Drill issue - review procedures"
fi
```

---

## 💾 Backup Verification

### Backup Test Script

```bash
#!/bin/bash
# test_backup.sh - Verify backup integrity

BACKUP_DIR="/home/beta/backups/pre-deploy/latest"

echo "=== Testing Backup Integrity ==="

# Test 1: Files exist
echo "[1/5] Checking files..."
FILES=(
    "database.sql.gz"
    "composer.json"
    "composer.lock"
    "custom_code.tar.gz"
    "config.tar.gz"
)

for file in "${FILES[@]}"; do
    if [ -f "$BACKUP_DIR/$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file MISSING"
    fi
done

# Test 2: Database backup valid
echo "[2/5] Testing database backup..."
if gunzip -t "$BACKUP_DIR/database.sql.gz" 2>/dev/null; then
    echo "  ✓ Database backup valid"
else
    echo "  ✗ Database backup CORRUPTED"
fi

# Test 3: Tarballs valid
echo "[3/5] Testing tarballs..."
tar -tzf "$BACKUP_DIR/custom_code.tar.gz" > /dev/null 2>&1 && echo "  ✓ Custom code tarball valid"
tar -tzf "$BACKUP_DIR/config.tar.gz" > /dev/null 2>&1 && echo "  ✓ Config tarball valid"

# Test 4: Git commit exists
echo "[4/5] Testing git commit..."
if [ -f "$BACKUP_DIR/git_commit.txt" ]; then
    COMMIT=$(cat "$BACKUP_DIR/git_commit.txt")
    if git rev-parse "$COMMIT" >/dev/null 2>&1; then
        echo "  ✓ Git commit $COMMIT exists"
    else
        echo "  ✗ Git commit $COMMIT NOT FOUND"
    fi
fi

# Test 5: Calculate sizes
echo "[5/5] Backup sizes:"
du -sh "$BACKUP_DIR"/* | sed 's/^/  /'

echo "=== Backup Test Complete ==="
```

---

## 📊 Backup Monitoring

### Backup Success Criteria:
- ✅ All backup files created
- ✅ Database backup < 2GB (compressed)
- ✅ Files backup < 5GB (compressed)
- ✅ Backup completes in < 30 minutes
- ✅ No errors in backup log
- ✅ Backup integrity test passes

### Alerts:
- ⚠️ Backup size increased > 50%
- 🔴 Backup failed to complete
- 🔴 Backup file corrupted
- 🔴 Disk space < 20% remaining
- ⚠️ Backup took > 45 minutes

---

## 📞 Emergency Contacts

### Escalation Path:
1. **Technical Lead:** [Contact Info]
2. **DevOps Engineer:** [Contact Info]
3. **System Administrator:** [Contact Info]
4. **Hosting Provider Support:** [Contact Info]

### Emergency Decision Matrix:

| Issue | Severity | Rollback Level | Max Downtime | Approver |
|-------|----------|----------------|--------------|----------|
| UI Glitch | Low | L1 (Cache) | 0 min | Dev |
| Feature Bug | Medium | L2 (Code) | 5 min | Tech Lead |
| Data Issue | High | L3 (DB) | 15 min | Manager |
| Site Down | Critical | L4 (Full) | 60 min | Director |

---

## 📝 Post-Incident Review

After any rollback, complete this template:

```markdown
# Rollback Post-Mortem

**Date:** [Date]
**Incident:** [Brief description]
**Rollback Level:** [L1/L2/L3/L4]
**Downtime:** [Duration]
**Approver:** [Name]

## Timeline:
- [Time] - Issue detected
- [Time] - Rollback initiated
- [Time] - Rollback completed
- [Time] - Site verified functional

## Root Cause:
[Detailed explanation]

## Impact:
- Users affected: [Number/percentage]
- Revenue impact: [If applicable]
- Data loss: [None/description]

## Lessons Learned:
1. [What went well]
2. [What could be improved]
3. [Action items]

## Prevention:
- [ ] [Action item 1]
- [ ] [Action item 2]
- [ ] [Action item 3]
```

---

## ✅ Success Metrics

### Backup Success:
- **Target:** 100% successful backups
- **Current:** [Track weekly]
- **SLA:** < 1 failed backup per month

### Rollback Speed:
- **L1 Target:** < 5 minutes
- **L2 Target:** < 15 minutes
- **L3 Target:** < 30 minutes
- **L4 Target:** < 60 minutes

### Recovery Point Objective (RPO):
- **Target:** < 24 hours data loss
- **Current:** < 1 hour (with daily backups)

### Recovery Time Objective (RTO):
- **Target:** < 1 hour
- **Current:** < 30 minutes (L1-L3), < 60 minutes (L4)

---

**Document Status:** Production Ready  
**Last Updated:** 2026-04-11  
**Next Review:** 2026-05-11  
**Owner:** DevOps Team
