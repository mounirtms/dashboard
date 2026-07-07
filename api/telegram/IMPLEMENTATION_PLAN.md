# Customer Bot & Dashboard Implementation Plan

## Current Status

### Customer Bot Audit Results
✅ **Completed Components:**
- CustomerBot.php - Main orchestrator (119 lines)
- CustomerRouter.php - Command/callback routing
- CustomerSessionManager.php - Cart persistence
- CustomerKeyboards.php - Inline keyboard layouts
- customer_poller.php - Cron-based polling
- EnvironmentHelper.php - ALL customer-facing DB methods (653 lines)
- CustomerSecurity.php - Rate limiting (just created)

❌ **Missing/Not Configured:**
- Bot token not set in config.php
- Bot disabled (`enabled: false`)
- Webhook/poller not active
- Yalidine shipping integration not tested

### Beta Environment Status
- Magento 2.4.6 in developer mode
- REST API available
- Yalidine Express shipping module (Mab_YalidineCarrier)
- Mab_Notifications module (extensible)
- Database: beta_dBT8x12y22

## Implementation Tasks

### Phase 1: Customer Bot Activation (CRITICAL)

#### 1.1 Get Bot Token from User
**Status:** PENDING - Requires user input
**Action:** Ask for TechnoStationeryShopBot token from BotFather

#### 1.2 Update config.php
**File:** `/home/dashboard/public_html/api/telegram/config.php`
**Changes:**
- Replace `CUSTOMER_BOT_TOKEN_HERE` with actual token
- Change `enabled` from `false` to `true`

#### 1.3 Test Bot Connection
**Command:**
```bash
curl -s "https://api.telegram.org/bot<TOKEN>/getMe" | jq
```

#### 1.4 Setup Webhook or Poller
**Option A - Webhook (Recommended):**
- Set webhook URL to customer_poller.php
- Faster, more reliable
- Requires HTTPS endpoint

**Option B - Cron Poller:**
- Add to crontab: `* * * * * php /home/dashboard/public_html/api/telegram/customer/customer_poller.php`
- Simpler setup
- 1-minute delay

**Recommendation:** Use webhook for production, poller for testing

### Phase 2: Customer Bot Testing

#### 2.1 Test Basic Commands
- `/start` - Welcome message and main menu
- `/browse` - Category browser
- `/search` - Product search
- `/cart` - View cart
- `/orders` - Order history
- `/account` - Account info

#### 2.2 Test Full Order Flow
1. Browse categories → Select category → View products
2. Add products to cart
3. Review cart → Checkout
4. Enter shipping address
5. Calculate shipping (Yalidine integration)
6. Confirm order
7. Receive order confirmation

#### 2.3 Test Edge Cases
- Rate limiting (30 req/min)
- Cart expiration (24 hours)
- Invalid inputs
- Out of stock products
- Payment failures

### Phase 3: Yalidine Shipping Integration

#### 3.1 Understand Yalidine Module
**Module:** Mab_YalidineCarrier
**Location:** `/home/beta/public_html/app/code/Mab/YalidineCarrier`
**API:** REST endpoint for rate calculation

#### 3.2 Integrate Shipping Calculation
**File to modify:** EnvironmentHelper.php or CustomerRouter.php
**Method:** Add `getYalidineShippingRate($address, $cartWeight)`
**Flow:**
1. Get customer address from Telegram
2. Calculate cart weight from products
3. Call Yalidine API for rate
4. Add shipping cost to order total

#### 3.3 Test Shipping Calculation
- Test with different wilayas (provinces)
- Test with different cart weights
- Handle API failures gracefully

### Phase 4: Dashboard Enhancements

#### 4.1 Orders Management Tab
**Features:**
- View orders across environments (prod, beta, dev)
- Filter by status, date, customer
- Order details and status updates
- Real-time updates via Telegram bot

**API Endpoint:** `/api/dashboard.php?action=orders`
**Database Query:** Join sales_order, customer_entity, sales_order_item

#### 4.2 Product Catalog Tab
**Features:**
- Browse products by category
- Search products
- Stock levels and alerts
- Quick edit (price, stock, status)

**API Endpoint:** `/api/dashboard.php?action=products`
**Integration:** Uses same queries as EnvironmentHelper.php

#### 4.3 Customer Management Tab
**Features:**
- Customer list and search
- Customer details and order history
- Account status (active, disabled)
- Customer groups

**API Endpoint:** `/api/dashboard.php?action=customers`

#### 4.4 Notifications Center Tab
**Features:**
- Real-time alerts feed
- Alert history and filtering
- Alert configuration
- Telegram bot status monitoring

**Integration:** Connect to AlertManager.php

#### 4.5 Redis Monitoring
**Metrics:**
- Memory usage
- Hit rate
- Connected clients
- Key count

**API Endpoint:** `/api/monitor.php?action=redis`

#### 4.6 Elasticsearch Monitoring
**Metrics:**
- Cluster health (green/yellow/red)
- Index sizes
- Query performance
- Node status

**API Endpoint:** `/api/monitor.php?action=elasticsearch`

#### 4.7 Varnish Monitoring
**Metrics:**
- Cache hit ratio
- Backend health
- Hit/miss rates
- Storage usage

**API Endpoint:** `/api/monitor.php?action=varnish`

#### 4.8 Historical Metrics & Charts
**Features:**
- Store metrics in JSON/SQLite
- Trending charts for CPU, memory, disk
- Order volume trends
- Customer growth trends

**Implementation:** Add cron job to collect metrics every 5 minutes

### Phase 5: Magento Module Integration

#### 5.1 Extend Mab_Notifications Module
**Goal:** Support Telegram channel for customer notifications
**Events to support:**
- Order confirmation
- Order status changes
- Shipping updates
- Low stock alerts

**Method:** Add Telegram notification observer to existing events

#### 5.2 Create Telegram Notification API
**Endpoint:** `/api/telegram/notify.php`
**Purpose:** Allow Magento to send notifications to customers via Telegram
**Flow:**
1. Customer places order → Magento saves Telegram chat_id
2. Order status changes → Trigger Telegram notification
3. Customer receives update in Telegram chat

### Phase 6: Testing & Deployment

#### 6.1 Beta Testing
- Enable bot for beta environment only
- Test with real products and orders
- Verify order creation in database
- Test all customer flows

#### 6.2 Production Preparation
- Test with production database (read-only queries)
- Ensure rate limiting works under load
- Test webhook reliability
- Monitor error logs

#### 6.3 Go Live
- Enable customer bot
- Set webhook
- Monitor for 24 hours
- Collect user feedback

## Critical Dependencies

1. **Bot Token** - Cannot proceed without it
2. **CustomerSecurity.php** - ✅ Created
3. **Beta Database Access** - ✅ Available
4. **Yalidine API Access** - Need to verify credentials
5. **Webhook HTTPS** - Requires SSL certificate

## Risk Assessment

**High Risk:**
- Order creation conflicts with Magento checkout
- Yalidine API rate limits
- Database performance with bot polling

**Medium Risk:**
- Rate limiting too strict/lenient
- Session persistence across devices
- Webhook reliability

**Low Risk:**
- Dashboard UI changes (no data modification)
- Monitoring additions (read-only)
- Alert deduplication improvements

## Next Immediate Actions

1. ✅ Create CustomerSecurity.php
2. ⏳ **WAITING FOR USER:** Provide customer bot token
3. Update config.php with bot token
4. Enable customer bot in config
5. Test bot connection
6. Setup webhook
7. Test browse → cart → checkout flow
