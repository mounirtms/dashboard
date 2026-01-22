# Techno Stationery - Documentation Portal

## 🚀 Live at: https://technostationery.com/documentation/main.html

---

## What is This?

This is the **Technical Documentation & Real-Time Statistics Portal** for Techno Stationery's Magento e-commerce platform. It provides live metrics, system health monitoring, and comprehensive documentation in one place.

## Quick Links

- **📊 Main Dashboard:** [main.html](https://technostationery.com/documentation/main.html)
- **🏥 API Health:** [api.php?action=health](https://technostationery.com/documentation/api.php?action=health)
- **📖 Quick Start Guide:** [QUICK_START.md](QUICK_START.md)
- **📋 Full Documentation:** [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)

## Features

### Real-Time Statistics
- **Orders:** Live count (currently: 5,724)
- **Customers:** Total registered users
- **Products:** Catalog metrics
- **Yalidine Integration:** 58 Wilayas, 1,100 Communes
- **Database:** Size, table counts, health status

### REST API
Access data programmatically:
```bash
# Health check
curl https://technostationery.com/documentation/api.php?action=health

# All statistics
curl https://technostationery.com/documentation/api.php?action=all
```

Available endpoints:
- `?action=health` - System health check
- `?action=general` - General statistics
- `?action=yalidine` - Yalidine integration metrics
- `?action=database` - Database information
- `?action=orders` - Order statistics
- `?action=performance` - Performance metrics
- `?action=all` - All statistics combined

### Security
- ✅ Read-only database access
- ✅ Protected configuration files
- ✅ SQL injection prevention
- ✅ Comprehensive .htaccess rules
- ✅ No sensitive data exposed

## File Structure

```
/pub/documentation/
├── main.html                    # Main dashboard
├── api.php                      # REST API
├── config.php                   # Configuration (protected)
├── .htaccess                    # Security rules
├── includes/                    # PHP libraries (protected)
│   ├── db.php
│   ├── stats.php
│   └── index.php
├── logs/                        # Logs & cache (protected)
├── assets/                      # Public assets
├── QUICK_START.md               # Quick start guide
├── DEPLOYMENT_GUIDE.md          # Full documentation
├── FINAL_DEPLOYMENT_REPORT.md   # Deployment report
└── README.md                    # This file
```

## Quick Verification

### Browser Test
Open: https://technostationery.com/documentation/main.html

You should see:
- Live order count
- Customer statistics
- Product metrics
- Yalidine integration data
- Green "Online" status indicator

### Command Line Test
```bash
# Test database connection
cd /home/technadminy7/public_html/pub/documentation
php -r "
define('DOC_ACCESS', true);
\$config = require './config.php';
require './includes/db.php';
\$db = DatabaseConnection::getInstance(\$config);
echo \$db->queryValue('SELECT COUNT(*) FROM sales_order') . ' orders' . PHP_EOL;
"

# Run full verification
bash verify-deployment.sh
```

## Documentation

| Document | Description |
|----------|-------------|
| **QUICK_START.md** | Quick reference guide for immediate use |
| **DEPLOYMENT_GUIDE.md** | Complete technical documentation |
| **FINAL_DEPLOYMENT_REPORT.md** | Deployment summary with all details |
| **DEPLOYMENT_FILES_LIST.txt** | Inventory of deployed files |
| **verify-deployment.sh** | Automated verification script |

## Support

- **Repository:** https://github.com/mounirtms/techno-magento
- **Documentation:** Read the guides in this directory
- **API Status:** https://technostationery.com/documentation/api.php?action=health

## Version

- **Version:** 1.0
- **Date:** 2026-01-22
- **Status:** ✅ Production Ready
- **Testing:** ✅ Verified

## License

Internal use only - Techno Stationery

---

**Deployment Status:** ✅ **OPERATIONAL**  
**Last Updated:** 2026-01-22  
**Maintained By:** Development Team
