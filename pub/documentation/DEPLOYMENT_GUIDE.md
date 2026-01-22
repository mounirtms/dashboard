# Documentation System - Deployment Guide

## 📋 Overview

This is the **Technical Documentation & Real-Time Statistics System** for Techno Stationery's Magento installation. It provides:

- **Real-time database statistics** from Magento database
- **Yalidine integration metrics** (Wilayas, Communes, Orders)
- **System health monitoring**
- **Performance metrics**
- **Secure, read-only API access**

## 🌐 Live URL

**Main Documentation Portal:**  
https://technostationery.com/documentation/main.html

## 📁 Directory Structure

```
/home/system_user/public_html/pub/documentation/
├── main.html              # Main dashboard (primary interface)
├── index.html             # Legacy documentation index
├── api.php                # REST API endpoint
├── config.php             # System configuration (protected)
├── .htaccess             # Security & routing rules
│
├── includes/              # PHP libraries (protected)
│   ├── db.php            # Database connection handler
│   └── stats.php         # Statistics collector
│
├── assets/               # Public assets
│   ├── css/             # Stylesheets
│   ├── js/              # JavaScript files
│   └── images/          # Image assets
│
├── logs/                 # System logs (protected, writable)
│   ├── error_*.log      # Error logs
│   ├── api_error_*.log  # API error logs
│   └── cache_*.json     # Statistics cache files
│
├── data/                 # Data storage (protected, writable)
├── pages/                # Additional documentation pages
├── guides/               # User guides
├── analysis/             # System analysis reports
└── audit/                # Audit documentation
```

## 🔒 Security Features

### File Protection
- **`.htaccess`** prevents direct access to:
  - `config.php` (database credentials)
  - `includes/` directory
  - `logs/` directory
  - `data/` directory
  - `.backup` files

### Database Security
- **Read-only access** - Only SELECT queries allowed
- **Parameterized queries** - SQL injection prevention
- **Connection pooling** - Single instance pattern
- **Error logging** - No sensitive data in errors

### HTTP Security Headers
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection: Enabled
- X-Content-Type-Options: nosniff
- Content-Security-Policy: Configured
- Referrer-Policy: strict-origin-when-cross-origin

## 🗄️ Database Configuration

The system connects to the Magento database with **read-only** credentials:

```php
// From config.php
DB_HOST: database_host:port
DB_NAME: magento_user
DB_USER: beta_ntdbusr24  (read-only)
DB_CHARSET: utf8mb4
```

### Monitored Tables

**Core Magento:**
- `sales_order` - Order statistics
- `catalog_product_entity` - Product data
- `customer_entity` - Customer information
- `quote` - Cart/quote data

**Yalidine Integration:**
- `mab_yalidine_wilayas` - Algerian provinces (58 active)
- `mab_yalidine_communes` - Algerian communes (1,100 active)
- `mab_yalidine_source_mapping` - Warehouse mappings (21)
- `mab_yalidine_dealers` - Dealer network
- `quote_address` - Synced quote addresses (9)
- `sales_order_address` - Synced order addresses (11,422)

## 📊 API Endpoints

### Base URL
```
https://technostationery.com/documentation/api.php
```

### Available Actions

#### 1. Health Check
```
GET /documentation/api.php?action=health
```
Response:
```json
{
  "success": true,
  "timestamp": "2026-01-22 15:30:00",
  "response_time_ms": 0.51,
  "data": {
    "status": "online",
    "database": "connected",
    "version": "4.9.4"
  }
}
```

#### 2. General Statistics
```
GET /documentation/api.php?action=general
```
Returns:
- Total orders
- Total customers
- Total products
- Orders today
- 30-day revenue
- Pending orders

#### 3. Yalidine Integration
```
GET /documentation/api.php?action=yalidine
```
Returns:
- Wilayas (total/active)
- Communes (total/active)
- Synced addresses
- Recent parcels
- Dealer statistics

#### 4. Database Stats
```
GET /documentation/api.php?action=database
```
Returns:
- Database size (MB)
- Table count
- Key table row counts

#### 5. All Statistics
```
GET /documentation/api.php?action=all
```
Returns complete system statistics.

#### 6. Clear Cache
```
GET /documentation/api.php?action=clear_cache
```
Clears statistics cache files.

## ⚡ Performance

### Caching
- **Cache Duration:** 5 minutes (300 seconds)
- **Cache Location:** `logs/cache_*.json`
- **Cache Strategy:** File-based, automatic invalidation

### Response Times
- **API Health Check:** ~0.5ms
- **General Stats:** ~50-100ms (cached)
- **Yalidine Stats:** ~80ms (cached)
- **Full Stats:** ~200ms (cached)

### Database Optimization
- **Connection Pooling:** Singleton pattern
- **Query Optimization:** Indexed queries
- **Selective Loading:** Only required data
- **Prepared Statements:** SQL injection prevention

## 🧪 Testing & Verification

### 1. Test Database Connection
```bash
cd /home/system_user/public_html/pub/documentation
php -r "
define('DOC_ACCESS', true);
\$config = require './config.php';
require './includes/db.php';
\$db = DatabaseConnection::getInstance(\$config);
echo \$db->queryValue('SELECT COUNT(*) FROM sales_order') . \" orders\n\";
"
```

Expected output: `5724 orders` (or current count)

### 2. Test Yalidine Stats
```bash
cd /home/system_user/public_html/pub/documentation
php -r "
define('DOC_ACCESS', true);
\$config = require './config.php';
require './includes/db.php';
require './includes/stats.php';
\$db = DatabaseConnection::getInstance(\$config);
\$stats = new StatsCollector(\$db, \$config);
print_r(\$stats->getYalidineStats());
"
```

### 3. Test API Endpoint
```bash
curl -s https://technostationery.com/documentation/api.php?action=health | python3 -m json.tool
```

### 4. Browser Test
Open in browser:
- https://technostationery.com/documentation/main.html
- Check that all statistics load
- Verify no JavaScript errors in console

## 📈 Current Statistics (as of deployment)

```
📊 Magento Database
   Total Orders: 5,724
   Total Customers: [loaded from DB]
   Total Products: [loaded from DB]
   Database Size: [calculated]

🚚 Yalidine Integration
   Wilayas: 58 active / 58 total
   Communes: 1,100 active / 1,100 total
   Source Mappings: 21
   Synced Quote Addresses: 9
   Synced Order Addresses: 11,422

⚡ System Health
   Status: Online
   Database: Connected
   Response Time: <100ms
   Cache: Enabled (5min)
```

## 🔧 Maintenance

### Clear Cache Manually
```bash
cd /home/system_user/public_html/pub/documentation/logs
rm -f cache_*.json
```

### View Error Logs
```bash
cd /home/system_user/public_html/pub/documentation/logs
tail -f error_$(date +%Y-%m-%d).log
tail -f api_error_$(date +%Y-%m-% d).log
```

### Check Permissions
```bash
cd /home/system_user/public_html/pub/documentation
ls -la
# logs/ and data/ should be 777 (writable)
```

### Update Configuration
Edit `config.php` if needed (requires server access):
```bash
nano /home/system_user/public_html/pub/documentation/config.php
```

## 🚨 Troubleshooting

### Issue: "Database connection failed"
**Solution:**
1. Check database credentials in `config.php`
2. Verify database server is running
3. Check MySQL/MariaDB port (3307)
4. Review error logs in `logs/error_*.log`

### Issue: "Permission denied" errors
**Solution:**
```bash
cd /home/system_user/public_html/pub/documentation
chmod 777 logs data
chmod 755 includes pages api assets
chmod 644 config.php api.php main.html
```

### Issue: Stale data displayed
**Solution:**
```bash
# Clear cache via API
curl https://technostationery.com/documentation/api.php?action=clear_cache

# Or manually
rm -f /home/system_user/public_html/pub/documentation/logs/cache_*.json
```

### Issue: Slow response times
**Solution:**
1. Check database performance
2. Verify cache is enabled in `config.php`
3. Increase cache duration if needed
4. Monitor database query logs

## 📝 Version History

- **v1.0** (2026-01-22) - Initial deployment
  - Real-time statistics dashboard
  - Yalidine integration metrics
  - Secure API endpoints
  - Caching system
  - Comprehensive documentation

## 🔗 Related Documentation

- **Yalidine Integration Guide:** `YALIDINE_CHECKOUT_TEST_GUIDE_v4.9.4.md`
- **Deployment Summary:** `DEPLOYMENT_SUMMARY_v4.9.4.md`
- **Testing Commands:** `TESTING_COMMANDS.sh`
- **Address Sync Guide:** `YALIDINE_ADDRESS_SYNC_GUIDE_v4.9.3.md`

## 🎯 Future Enhancements

- [ ] Real-time WebSocket updates
- [ ] Historical trend charts
- [ ] Export to CSV/PDF
- [ ] Advanced filtering options
- [ ] Mobile-responsive design enhancements
- [ ] Multi-language support (French/Arabic)
- [ ] User authentication system
- [ ] Alerting for threshold breaches
- [ ] Integration with Netdata/monitoring
- [ ] Custom report generation

## 👥 Support

For technical support or questions:
- **GitHub:** https://github.com/mounirtms/techno-magento
- **Documentation:** https://technostationery.com/documentation/
- **System Status:** https://technostationery.com/documentation/api.php?action=health

---

**Last Updated:** 2026-01-22  
**Version:** 1.0  
**Status:** ✅ Production Ready
