# 🚀 TechnoStationery Server Management Dashboard

## 📊 Live Dashboard

**Main Dashboard:** https://dashboard.technostationery.com/  
**Queue Monitor:** https://dashboard.technostationery.com/queue-monitor.html  
**API Endpoint:** https://dashboard.technostationery.com/api/queue-monitor.php

---

## 🎯 Overview

Comprehensive server management and monitoring system for TechnoStationery infrastructure. Provides real-time queue monitoring, automated cleanup, emergency recovery, and visual analytics across all environments (Production, Beta, Dev).

---

## 📦 Components

### 1. **Main Dashboard** (`index.html`)
- Beautiful landing page with gradient design
- Quick access to all tools
- Live system statistics
- Feature overview
- Quick command reference

### 2. **Queue Monitor Dashboard** (`queue-monitor.html`)
- Real-time queue status visualization
- Color-coded metric cards
- Interactive status bar chart
- Environment switcher (Production/Beta/Dev)
- Intelligent alert system
- Auto-refresh every 60 seconds
- One-click cleanup actions
- Mobile-responsive design

### 3. **Monitoring API** (`api/queue-monitor.php`)
- RESTful API for real-time data
- Queue statistics and health assessment
- CPU usage monitoring
- Active consumer tracking
- Indexer status reporting
- JSON responses

### 4. **Queue Cleanup Scripts** (`webapp/scripts/`)
- `queue-cleanup-optimizer.sh` - Intelligent automated cleanup
- `emergency-queue-cleanup.sh` - Emergency recovery tool

---

## 🎨 Features

### Real-Time Monitoring
- ✅ Live queue status across all environments
- ✅ CPU usage tracking
- ✅ Active consumer counting
- ✅ System health assessment

### Visual Analytics
- ✅ Color-coded metric cards
- ✅ Interactive status distribution chart
- ✅ Trend analysis
- ✅ Alert banners

### Automated Cleanup
- ✅ Scheduled cleanup every 6 hours
- ✅ Configurable thresholds
- ✅ Database optimization
- ✅ Consumer process management

### Emergency Recovery
- ✅ One-click emergency cleanup
- ✅ Kill stuck processes
- ✅ Massive backlog deletion
- ✅ System restoration

### Multi-Environment
- ✅ Production support
- ✅ Beta environment
- ✅ Dev environment
- ✅ Easy switching

---

## 🚀 Quick Start

### View Dashboard
```bash
# Open in browser
https://dashboard.technostationery.com/
```

### Check Queue Status
```bash
mysql -uroot -p'YourNewStrongPassword' -h127.0.0.1 -P3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*), status FROM queue GROUP BY status;"
```

### Run Cleanup
```bash
bash /home/technadminy7/public_html/scripts/queue-cleanup-optimizer.sh production --force
```

### Emergency Cleanup
```bash
bash /home/technadminy7/public_html/scripts/emergency-queue-cleanup.sh production
```

---

## 📊 API Usage

### Get Queue Status
```bash
curl "https://dashboard.technostationery.com/api/queue-monitor.php?env=production&action=status"
```

### Get Queue History
```bash
curl "https://dashboard.technostationery.com/api/queue-monitor.php?env=production&action=history"
```

### Trigger Cleanup
```bash
curl "https://dashboard.technostationery.com/api/queue-monitor.php?env=production&action=cleanup"
```

### Emergency Cleanup
```bash
curl "https://dashboard.technostationery.com/api/queue-monitor.php?env=production&action=emergency"
```

---

## ⚙️ Configuration

### Thresholds

**Queue Cleanup:**
- Completed jobs: >24 hours → DELETE
- Failed jobs: >48 hours → DELETE
- Stuck jobs: >2 hours → RESET
- Very old jobs: >7 days → DELETE
- Cron entries: >7 days → DELETE

**Health Alerts:**
- Queue size >10,000 → CRITICAL
- Queue size >5,000 → WARNING
- CPU usage >80% → CRITICAL
- CPU usage >60% → WARNING

### Environments

**Production:**
- Path: `/home/technadminy7/public_html`
- Database: `technadminy7_dBT8x12y22`
- User: `technadminy7`

**Beta:**
- Path: `/home/beta/public_html`
- Database: `beta_dBT8x12y22`
- User: `beta`

**Dev:**
- Path: `/home/dev/public_html`
- Database: `dev_dBT8x12y22`
- User: `dev`

---

## 🔄 Cron Jobs

### Recommended Schedule

```cron
# Queue cleanup every 6 hours
0 */6 * * * /home/technadminy7/public_html/scripts/queue-cleanup-optimizer.sh production --force >> /home/dashboard/public_html/webapp/logs/cron_cleanup_production.log 2>&1

# Emergency cleanup trigger (if queue >10K)
*/30 * * * * QUEUE_COUNT=$(mysql -uroot -p'YourNewStrongPassword' -h127.0.0.1 -P3307 technadminy7_dBT8x12y22 -sNe 'SELECT COUNT(*) FROM queue;' 2>/dev/null); if [ "$QUEUE_COUNT" -gt 10000 ]; then /home/technadminy7/public_html/scripts/emergency-queue-cleanup.sh production; fi
```

### Setup Cron Jobs
```bash
# Edit crontab
crontab -e

# Add the recommended schedule above
# Save and exit
```

---

## 📁 File Structure

```
/home/dashboard/public_html/
├── index.html                          # Main dashboard landing page
├── queue-monitor.html                  # Queue monitoring dashboard
├── api/
│   └── queue-monitor.php              # Monitoring API
├── webapp/
│   ├── scripts/
│   │   ├── queue-cleanup-optimizer.sh # Main cleanup script
│   │   └── emergency-queue-cleanup.sh # Emergency script
│   ├── logs/                          # Log files
│   └── deploy-session-20.sh           # Deployment script
├── scripts/                            # Centralized scripts repository
└── README.md                          # This file
```

---

## 🎨 Dashboard Features

### Main Landing Page
- **Beautiful Design:** Gradient purple theme with smooth animations
- **Quick Stats:** Live metrics for environments, tools, uptime
- **Card Navigation:** Easy access to all tools
- **Feature Grid:** Overview of all capabilities
- **Quick Commands:** Copy-paste ready commands
- **Mobile Responsive:** Works on all devices

### Queue Monitor
- **Real-Time Data:** Updates every 60 seconds
- **Environment Switcher:** Toggle between Production/Beta/Dev
- **Metric Cards:** Color-coded health indicators
- **Status Chart:** Visual distribution bar
- **Alert System:** Critical/Warning/Healthy badges
- **Action Buttons:** One-click refresh and cleanup
- **Detailed Table:** Threshold comparison view

---

## 🔧 Technical Details

### Technologies
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Scripts:** Bash
- **API:** RESTful JSON

### Browser Support
- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers

---

## 📈 Performance

### Before Session 20
❌ No automated queue cleanup  
❌ Queue bloat causes CPU overload  
❌ No visibility into queue status  
❌ Manual intervention required  
❌ Production breakdowns frequent  

### After Session 20
✅ Automated cleanup every 6 hours  
✅ Emergency triggers for critical states  
✅ Real-time monitoring dashboard  
✅ Proactive alerting system  
✅ 99.9% uptime achievable  

---

## 🎯 Use Cases

### Daily Operations
1. Check dashboard for system health
2. Review queue status across environments
3. Monitor CPU usage trends
4. Review cleanup logs

### Troubleshooting
1. Access queue monitor dashboard
2. Identify problematic environment
3. Review alert messages
4. Run appropriate cleanup action

### Emergency Response
1. Dashboard shows critical alert
2. Click emergency cleanup button
3. OR run emergency script via SSH
4. Monitor recovery progress
5. Verify system stability

---

## 📝 Logs

### Log Locations
- Deployment logs: `/home/dashboard/public_html/webapp/logs/deploy_*.log`
- Cleanup logs: `/home/dashboard/public_html/webapp/logs/queue_cleanup_*.log`
- Emergency logs: `/home/dashboard/public_html/webapp/logs/emergency_*.log`
- Cron logs: `/home/dashboard/public_html/webapp/logs/cron_*.log`

### View Logs
```bash
# View latest deployment log
tail -f /home/dashboard/public_html/webapp/logs/deploy_*.log

# View cleanup logs
tail -f /home/dashboard/public_html/webapp/logs/queue_cleanup_*.log

# View all logs
ls -lah /home/dashboard/public_html/webapp/logs/
```

---

## 🔒 Security

- ✅ Database credentials secured
- ✅ Script permissions properly set
- ✅ API CORS configuration
- ✅ Input validation
- ✅ SQL injection prevention
- ✅ Logging of all actions

---

## 🚨 Troubleshooting

### Dashboard Not Loading
```bash
# Check file permissions
ls -lah /home/dashboard/public_html/*.html

# Should be: -rw-r--r-- dashboard:dashboard
chmod 644 /home/dashboard/public_html/*.html
```

### API Not Responding
```bash
# Check PHP file permissions
ls -lah /home/dashboard/public_html/api/

# Test API directly
curl https://dashboard.technostationery.com/api/queue-monitor.php?env=production&action=status
```

### Queue Cleanup Not Working
```bash
# Check script permissions
ls -lah /home/technadminy7/public_html/scripts/*.sh

# Should be: -rwxr-xr-x
chmod +x /home/technadminy7/public_html/scripts/*.sh

# Test manually
bash /home/technadminy7/public_html/scripts/queue-cleanup-optimizer.sh production
```

---

## 📞 Support

For issues or questions:
- **Dashboard:** https://dashboard.technostationery.com/
- **GitHub:** https://github.com/mounirtms/TECHNO-ETL
- **Logs:** `/home/dashboard/public_html/webapp/logs/`

---

## 🎉 Version History

### Version 1.0.0 (Session 20) - March 31, 2026
- ✨ Initial release
- ✅ Queue monitoring dashboard
- ✅ Cleanup automation scripts
- ✅ Emergency recovery tools
- ✅ Visual analytics
- ✅ Multi-environment support

---

## 📄 License

TechnoStationery Internal Use Only &copy; 2026

---

## 🙏 Acknowledgments

Built with ❤️ for TechnoStationery infrastructure management and monitoring.

**Session 20 Complete** - March 31, 2026
