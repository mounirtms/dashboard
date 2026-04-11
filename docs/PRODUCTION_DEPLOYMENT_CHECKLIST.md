# 🚀 Production Deployment Checklist
**Date:** 2026-04-11  
**Project:** Beta Magento E-commerce Platform  
**Session:** 35 - Production Deployment

---

## 📋 Pre-Deployment Checklist

### Code Readiness (Day -7)
- [ ] All features completed and tested
- [ ] Code reviewed by team
- [ ] No known critical bugs
- [ ] Documentation up to date
- [ ] Pull request approved and merged
- [ ] Git tag created for release (e.g., `v1.5.0`)

### Testing Validation (Day -5)
- [ ] Unit tests passing (if applicable)
- [ ] Integration tests passing
- [ ] Manual testing completed
- [ ] Cross-browser testing done (Chrome, Firefox, Safari, Edge)
- [ ] Mobile responsive testing
- [ ] Performance testing acceptable
- [ ] Security scan completed
- [ ] Load testing performed (if major changes)

### Security Review (Day -3)
- [ ] Composer audit clean (or documented exceptions)
- [ ] No exposed credentials in code
- [ ] Security headers configured
- [ ] SSL certificate valid
- [ ] WAF rules in place (if applicable)
- [ ] Firewall rules reviewed
- [ ] Access controls verified

### Backup Verification (Day -1)
- [ ] Full backup completed within 24 hours
- [ ] Backup integrity verified
- [ ] Backup restoration tested in staging
- [ ] Rollback procedure documented
- [ ] Emergency contact list updated

### Communication (Day -1)
- [ ] Stakeholders notified of deployment window
- [ ] Support team briefed on changes
- [ ] Maintenance page prepared (if needed)
- [ ] Rollback plan communicated
- [ ] On-call schedule confirmed

---

## 🎯 Deployment Day Checklist

### Morning Preparation (T-4 hours)
```
Time: 08:00 AM
```

- [ ] **Team Assembly**
  - [ ] Deployment lead present
  - [ ] Technical lead available
  - [ ] System administrator on standby
  - [ ] QA tester ready

- [ ] **Environment Check**
  - [ ] Server health verified
  - [ ] Disk space adequate (>20% free)
  - [ ] Database connections stable
  - [ ] No ongoing incidents
  - [ ] Traffic levels normal

- [ ] **Tool Verification**
  - [ ] SSH access working
  - [ ] Git access confirmed
  - [ ] Composer operational
  - [ ] Magento CLI functional

### Pre-Deployment Backup (T-2 hours)
```
Time: 10:00 AM
Duration: 30 minutes
```

- [ ] **Execute Pre-Deployment Backup**
  ```bash
  cd /home/beta/public_html
  ./webapp/pre_deploy_backup.sh
  ```

- [ ] **Verify Backup**
  ```bash
  ls -lh /home/beta/backups/pre-deploy/latest/
  cat /home/beta/backups/pre-deploy/latest/metadata.txt
  ```

- [ ] **Test Backup Integrity**
  ```bash
  gunzip -t /home/beta/backups/pre-deploy/latest/database.sql.gz
  tar -tzf /home/beta/backups/pre-deploy/latest/custom_code.tar.gz | head
  ```

### Deployment Window (T-0)
```
Time: 12:00 PM (Noon)
Duration: 60 minutes
Expected Downtime: 5-10 minutes
```

#### Step 1: Enable Maintenance Mode (12:00 PM)
- [ ] **Enable maintenance**
  ```bash
  cd /home/beta/public_html
  php bin/magento maintenance:enable
  ```

- [ ] **Verify maintenance page**
  ```bash
  curl -I https://yourdomain.com/ | grep "503"
  ```

- [ ] **Post maintenance notice** (if customer-facing)
  - Twitter/X
  - Facebook
  - Status page

#### Step 2: Code Deployment (12:02 PM)
- [ ] **Pull latest code**
  ```bash
  git fetch origin
  git checkout oldbetbranch-working-change
  git pull origin oldbetbranch-working-change
  ```

- [ ] **Verify commit hash**
  ```bash
  git log -1 --oneline
  # Should show: eee5a1a10 docs(MAB): Add comprehensive finalization plans
  ```

- [ ] **Update dependencies** (if composer changed)
  ```bash
  composer install --no-dev --optimize-autoloader
  ```

#### Step 3: Database Migration (12:10 PM)
- [ ] **Run setup upgrade**
  ```bash
  php bin/magento setup:upgrade
  ```

- [ ] **Check for errors**
  ```bash
  tail -50 var/log/system.log
  ```

- [ ] **Verify module status**
  ```bash
  php bin/magento module:status | grep Mab_
  ```

#### Step 4: Compilation & Static Content (12:15 PM)
- [ ] **Clear old generated files**
  ```bash
  rm -rf generated/code/*
  rm -rf generated/metadata/*
  rm -rf pub/static/frontend/*
  rm -rf pub/static/adminhtml/*
  ```

- [ ] **Compile DI**
  ```bash
  php bin/magento setup:di:compile
  ```

- [ ] **Deploy static content**
  ```bash
  php bin/magento setup:static-content:deploy fr_FR en_US -f
  ```

- [ ] **Verify deployment**
  ```bash
  ls -la pub/static/frontend/Smartwave/market/fr_FR/Mab_*
  ```

#### Step 5: Cache Management (12:35 PM)
- [ ] **Clear all caches**
  ```bash
  php bin/magento cache:clean
  php bin/magento cache:flush
  ```

- [ ] **Enable all caches**
  ```bash
  php bin/magento cache:enable
  ```

- [ ] **Verify cache status**
  ```bash
  php bin/magento cache:status
  ```

#### Step 6: Permissions & Ownership (12:40 PM)
- [ ] **Set correct permissions**
  ```bash
  find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} \;
  find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} \;
  ```

- [ ] **Set ownership** (if needed)
  ```bash
  chown -R beta:beta /home/beta/public_html/
  ```

#### Step 7: Reindex (12:42 PM)
- [ ] **Reindex all**
  ```bash
  php bin/magento indexer:reindex
  ```

- [ ] **Verify indexer status**
  ```bash
  php bin/magento indexer:status
  ```

#### Step 8: Smoke Tests (Pre-Launch) (12:45 PM)
- [ ] **Test Magento CLI**
  ```bash
  php bin/magento --version
  ```

- [ ] **Test critical endpoints** (with maintenance mode on)
  ```bash
  # Admin login page (should show maintenance for frontend only)
  curl -s https://yourdomain.com/admin | grep -q "admin" && echo "Admin OK"
  ```

- [ ] **Check logs for errors**
  ```bash
  tail -100 var/log/system.log | grep -i error
  tail -100 var/log/exception.log
  ```

#### Step 9: Disable Maintenance Mode (12:50 PM)
- [ ] **Disable maintenance**
  ```bash
  php bin/magento maintenance:disable
  ```

- [ ] **Verify site is live**
  ```bash
  curl -I https://yourdomain.com/ | grep "200 OK"
  ```

- [ ] **Announce deployment complete**
  - Slack/Teams notification
  - Update status page

---

## ✅ Post-Deployment Verification (12:55 PM - 1:30 PM)

### Immediate Tests (Within 5 minutes)
- [ ] **Homepage**
  - [ ] Loads without errors
  - [ ] CSS/JS loading correctly
  - [ ] No console errors in browser
  - [ ] Images displaying

- [ ] **Admin Panel**
  - [ ] Login successful
  - [ ] Dashboard loads
  - [ ] MAB Extensions menu visible
  - [ ] Push Notifications under MAB Extensions (with emojis)
  - [ ] No duplicate Webpushr entries

- [ ] **Cart Functionality**
  - [ ] Add product to cart
  - [ ] Source selector visible
  - [ ] Select source from dropdown
  - [ ] Stock alerts appear (if applicable)
  - [ ] Coupon block visible (purple gradient 🎫)
  - [ ] Gift card block visible (pink gradient 🎁)

- [ ] **Checkout Process**
  - [ ] Proceed to checkout
  - [ ] Address form loads
  - [ ] Yalidine delivery options visible
  - [ ] Pickup validation working (if sources differ)
  - [ ] Payment methods available
  - [ ] Place order (test order)

- [ ] **Order Success Page**
  - [ ] Success page displays
  - [ ] Order number visible
  - [ ] Social login block present (single, not duplicate)
  - [ ] Firebase authentication loads
  - [ ] No console errors

### Extended Tests (Within 30 minutes)
- [ ] **French Translations**
  - [ ] Gift card UI in French
  - [ ] Cart messages in French
  - [ ] Checkout labels in French
  - [ ] Success page in French

- [ ] **Stock Alerts**
  - [ ] Select source with low stock
  - [ ] Yellow warning badge appears
  - [ ] Dealer contact message visible
  - [ ] "Veuillez contacter le revendeur" text present

- [ ] **Pickup Validation**
  - [ ] Select different source and pickup location
  - [ ] Distance calculation shows
  - [ ] Transfer time estimate appears
  - [ ] Cost warning (if > 300km)
  - [ ] Dealer contact prompt visible

- [ ] **Yalidine Integration**
  - [ ] Select Yalidine delivery
  - [ ] Choose commune
  - [ ] Prices display (home + stop-desk)
  - [ ] Home address field appears
  - [ ] Stop-desk centers list with GPS

- [ ] **Firebase Authentication**
  - [ ] Google sign-in button present
  - [ ] Facebook sign-in button present
  - [ ] No "Firebase already defined" errors
  - [ ] Language set to French
  - [ ] Console shows success messages

### Performance Checks
- [ ] **Page Load Times**
  - [ ] Homepage < 3 seconds
  - [ ] Product page < 3 seconds
  - [ ] Cart page < 2 seconds
  - [ ] Checkout < 3 seconds

- [ ] **Server Metrics**
  - [ ] CPU usage normal (< 70%)
  - [ ] Memory usage acceptable (< 80%)
  - [ ] Database connections stable
  - [ ] No error spikes in logs

### Security Verification
- [ ] **Headers Present**
  ```bash
  curl -I https://yourdomain.com/ | grep -E "X-Frame-Options|X-Content-Type-Options|X-XSS-Protection"
  ```

- [ ] **SSL Certificate**
  ```bash
  echo | openssl s_client -connect yourdomain.com:443 2>/dev/null | openssl x509 -noout -dates
  ```

- [ ] **No Exposed Secrets**
  - [ ] No API keys in HTML source
  - [ ] No database credentials visible
  - [ ] Firebase config properly secured

---

## 📊 Monitoring (First 24 Hours)

### Hour 1-2 (Active Monitoring)
- [ ] Monitor error logs every 15 minutes
- [ ] Check server load and response times
- [ ] Review customer support tickets
- [ ] Watch for unusual traffic patterns
- [ ] Verify critical transactions completing

### Hour 3-24 (Passive Monitoring)
- [ ] Set up alerts for:
  - [ ] 500 errors > 10/minute
  - [ ] 404 errors > 50/minute
  - [ ] Response time > 5 seconds
  - [ ] CPU usage > 80%
  - [ ] Memory usage > 90%
  - [ ] Failed orders > 5/hour

### Metrics to Track:
```
Dashboard URL: [Your monitoring URL]

Key Metrics:
- Total requests/minute
- Error rate (%)
- Average response time (ms)
- Conversion rate (%)
- Cart abandonment rate (%)
- Checkout completion rate (%)
```

---

## 🚨 Rollback Criteria

**Initiate rollback if:**
- [ ] Error rate > 5%
- [ ] Site completely inaccessible
- [ ] Database corruption detected
- [ ] Payment processing failing
- [ ] Customer data security breach
- [ ] Performance degradation > 50%
- [ ] Critical feature broken (checkout, cart, login)

**Rollback Procedure:**
```bash
# Level determined by issue severity
cd /home/beta/public_html

# L1: Cache only (UI issues)
./webapp/rollback_cache.sh

# L2: Code rollback (PHP errors)
./webapp/rollback_code.sh

# L3: Database rollback (data issues)
./webapp/rollback_database.sh

# L4: Full system (complete failure)
./webapp/rollback_full.sh
```

---

## 📝 Deployment Log Template

```markdown
# Deployment Log - Session 35

**Date:** 2026-04-11  
**Deployment Window:** 12:00 PM - 1:00 PM  
**Lead:** [Name]  
**Participants:** [Names]

## Pre-Deployment
- [ ] Backup completed: [Time] - [Size]
- [ ] Team assembled: [Time]
- [ ] Go/No-Go decision: [GO/NO-GO]

## Deployment Timeline
| Time | Action | Status | Notes |
|------|--------|--------|-------|
| 12:00 | Maintenance enabled | ✅ | |
| 12:02 | Code pulled | ✅ | Commit: eee5a1a10 |
| 12:10 | Database migrated | ✅ | |
| 12:15 | DI compiled | ✅ | |
| 12:25 | Static content deployed | ✅ | |
| 12:35 | Cache cleared | ✅ | |
| 12:40 | Permissions set | ✅ | |
| 12:42 | Reindex completed | ✅ | |
| 12:45 | Smoke tests passed | ✅ | |
| 12:50 | Maintenance disabled | ✅ | |

## Post-Deployment
- [ ] Immediate tests: [PASS/FAIL]
- [ ] Extended tests: [PASS/FAIL]
- [ ] Performance acceptable: [YES/NO]
- [ ] Monitoring setup: [YES/NO]

## Issues Encountered
1. [Issue description] - [Resolution]
2. [Issue description] - [Resolution]

## Rollback
- [ ] Rollback required: [YES/NO]
- [ ] Rollback level: [N/A or L1/L2/L3/L4]
- [ ] Rollback time: [N/A or time]

## Metrics
- **Downtime:** [X minutes]
- **Error rate:** [X%]
- **Response time:** [X ms]
- **Transactions:** [X completed]

## Sign-Off
- Deployment Lead: [Name] - [Date/Time]
- Technical Lead: [Name] - [Date/Time]
- QA: [Name] - [Date/Time]

## Post-Deployment Actions
- [ ] Monitor for 24 hours
- [ ] Update documentation
- [ ] Close deployment ticket
- [ ] Schedule post-mortem (if issues)
```

---

## 🎯 Success Criteria

### Deployment Success:
- ✅ Downtime < 10 minutes
- ✅ Zero data loss
- ✅ All tests passing
- ✅ Error rate < 1%
- ✅ Performance within acceptable range
- ✅ No security issues
- ✅ Customer experience unchanged or improved

### Production Readiness Score: 95%+
- Code quality: 95%
- Test coverage: 90%
- Documentation: 100%
- Security: 92% (pending Magento upgrade)
- Performance: 85%
- Monitoring: 90%

---

## 📞 Emergency Contacts

### On-Call Rotation
- **Primary:** [Name] - [Phone] - [Email]
- **Secondary:** [Name] - [Phone] - [Email]
- **Escalation:** [Manager] - [Phone] - [Email]

### Vendor Support
- **Hosting:** [Provider] - [Support Number]
- **CDN:** [Provider] - [Support Number]
- **Payment Gateway:** [Provider] - [Support Number]

---

## ✅ Sign-Off

### Pre-Deployment Approval
- [ ] **Code Review:** [Reviewer] - [Date]
- [ ] **Security Review:** [Reviewer] - [Date]
- [ ] **QA Approval:** [Tester] - [Date]
- [ ] **Business Approval:** [Manager] - [Date]

### Deployment Authorization
```
I authorize the deployment of Session 35 changes to production.

Signature: ____________________
Name: ____________________
Title: ____________________
Date: ____________________
```

---

**Document Status:** Ready for Use  
**Last Updated:** 2026-04-11  
**Next Review:** After deployment  
**Owner:** Deployment Team
