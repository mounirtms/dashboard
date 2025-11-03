# MAB Source Selector

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="../Core/view/adminhtml/web/images/mab-signature.svg" alt="MAB Source Selector" width="300" />
  </a>
  
  [![Magento 2](https://img.shields.io/badge/Magento-2.4+-orange?style=for-the-badge)](https://magento.com)
  [![Professional](https://img.shields.io/badge/Developer-Professional-blue?style=for-the-badge)](https://mounir1.github.io)
</div>

## 📍 Advanced Multi-Source Inventory Management

Professional Magento 2 module that provides intelligent source selection algorithms, advanced inventory management, and optimized stock allocation for multi-warehouse operations.

---

## ✨ Key Features

### 🏢 Multi-Source Management
- **Intelligent Source Selection** - Smart algorithms for optimal fulfillment
- **Warehouse Priority** - Configurable source priority rules
- **Distance-based Selection** - Location-aware inventory allocation
- **Stock Optimization** - Efficient inventory distribution

### 📊 Inventory Intelligence
- **Real-time Stock Tracking** - Live inventory synchronization
- **Stock Allocation Rules** - Custom allocation algorithms
- **Backorder Management** - Smart backorder handling
- **Safety Stock Levels** - Automated reorder points

### 🚚 Fulfillment Optimization
- **Split Shipment Logic** - Optimal order splitting
- **Shipping Cost Optimization** - Minimize shipping costs
- **Delivery Time Prediction** - Accurate delivery estimates
- **Carrier Integration** - Multi-carrier support

### 🗺️ Geographic Intelligence
- **Location-based Logic** - GPS and address-based selection
- **Regional Preferences** - Regional source preferences
- **Delivery Zone Mapping** - Optimized delivery zones
- **Customer Proximity** - Nearest warehouse selection

---

## 🛠️ Installation

### Prerequisites
- Magento 2.4+
- MAB Core module
- Magento Multi-Source Inventory (MSI)
- PHP 8.1+

### Installation Steps
```bash
# Enable the module
php bin/magento module:enable Mab_SourceSelector

# Run setup upgrade
php bin/magento setup:upgrade

# Compile DI
php bin/magento setup:di:compile

# Reindex inventory
php bin/magento indexer:reindex inventory

# Clear cache
php bin/magento cache:flush
```

---

## ⚙️ Configuration

### 1. Basic Setup
Navigate to: **Admin Panel → Stores → Configuration → MAB Extensions → MAB Source Selector**

### 2. Source Selection Rules

#### General Settings
```
✅ Enable Advanced Source Selection: Yes/No
🏢 Default Source: Main Warehouse
📊 Selection Algorithm: Priority/Distance/Stock Level
⚡ Enable Real-time Updates: Yes/No
```

#### Priority Configuration
```
🥇 Primary Sources: Warehouse priorities (1-10)
🗺️ Regional Mapping: Source to region assignment
🚚 Shipping Integration: Carrier-specific sources
🕰️ Time-based Rules: Peak hours source selection
```

### 3. Algorithm Configuration

#### Distance-based Selection
```
🗺️ Enable GPS Calculation: Yes/No
📏 Max Distance: 500 km
🚗 Delivery Cost Factor: Weight in selection
🕰️ Delivery Time Factor: Priority for speed
```

#### Stock-level Selection
```
📊 Minimum Stock Threshold: 10 units
🔄 Stock Buffer Percentage: 15%
⚠️ Low Stock Warning: 5 units
🚀 Overstock Preference: Use high-stock sources first
```

---

## 🔧 Advanced Configuration

### Custom Selection Algorithms
```php
// Custom source selection algorithm
class CustomSourceSelectionAlgorithm implements SourceSelectionAlgorithmInterface
{
    public function execute(InventoryRequestInterface $inventoryRequest): SourceSelectionResultInterface
    {
        $sourceSelectionItems = [];
        
        foreach ($inventoryRequest->getItems() as $item) {
            $selectedSources = $this->selectOptimalSources(
                $item->getSku(),
                $item->getQty(),
                $inventoryRequest->getStockId()
            );
            
            $sourceSelectionItems[] = $this->createSourceSelectionItem(
                $item,
                $selectedSources
            );
        }
        
        return $this->sourceSelectionResultFactory->create([
            'sourceSelectionItems' => $sourceSelectionItems,
            'isShippable' => $this->validateShippability($sourceSelectionItems)
        ]);
    }
}
```

### Geographic Configuration
```php
// Regional source mapping
$regionalMapping = [
    'DZ-01' => ['alger_warehouse', 'blida_warehouse'],     // Adrar
    'DZ-02' => ['chlef_warehouse'],                        // Chlef
    'DZ-03' => ['laghouat_warehouse'],                     // Laghouat
    'DZ-04' => ['batna_warehouse', 'setif_warehouse'],     // Batna
    'DZ-05' => ['bejaia_warehouse'],                       // Béjaïa
    'DZ-16' => ['alger_warehouse'],                        // Alger
    'DZ-31' => ['oran_warehouse'],                         // Oran
    'DZ-25' => ['constantine_warehouse']                   // Constantine
];
```

### Business Rules Engine
```php
// Business rules for source selection
$businessRules = [
    'peak_hours' => [
        'time_range' => ['09:00', '17:00'],
        'preferred_sources' => ['main_warehouse'],
        'avoid_sources' => ['remote_warehouse']
    ],
    'express_delivery' => [
        'required_sources' => ['express_hub'],
        'max_distance' => 50 // km
    ],
    'bulk_orders' => [
        'min_quantity' => 100,
        'preferred_sources' => ['bulk_warehouse'],
        'split_threshold' => 500
    ]
];
```

---

## 🚀 Performance Optimization

### Caching Strategy
```php
// Cache source selection results
public function getCachedSourceSelection($sku, $qty, $address)
{
    $cacheKey = 'mab_source_selection_' . md5($sku . $qty . serialize($address));
    $result = $this->cache->load($cacheKey);
    
    if (!$result) {
        $result = $this->performSourceSelection($sku, $qty, $address);
        $this->cache->save(
            serialize($result),
            $cacheKey,
            ['mab_source_selection'],
            1800 // 30 minutes
        );
    }
    
    return unserialize($result);
}
```

### Database Optimization
```sql
-- Optimize inventory source queries
CREATE INDEX idx_inventory_source_sku_qty 
ON inventory_source_item (sku, quantity, status);

-- Optimize source location queries
CREATE INDEX idx_source_location 
ON inventory_source (latitude, longitude, enabled);

-- Optimize reservation queries
CREATE INDEX idx_reservation_sku_stock 
ON inventory_reservation (sku, stock_id, quantity);
```

### Performance Metrics
- **Source Selection Time**: <100ms
- **Cache Hit Ratio**: 85%+
- **Database Queries**: Optimized for large catalogs
- **Memory Usage**: <5MB per selection

---

## 🔧 Developer Guide

### Custom Source Selection Plugin
```php
<?php
namespace Your\Module\Plugin;

use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;

class SourceSelectionPlugin
{
    public function beforeExecute(
        SourceSelectionServiceInterface $subject,
        $inventoryRequest
    ) {
        // Pre-process inventory request
        $this->preprocessRequest($inventoryRequest);
        return [$inventoryRequest];
    }
    
    public function afterExecute(
        SourceSelectionServiceInterface $subject,
        $result,
        $inventoryRequest
    ) {
        // Post-process selection result
        return $this->optimizeSelection($result, $inventoryRequest);
    }
}
```

### Custom Distance Calculator
```php
<?php
namespace Your\Module\Model;

class DistanceCalculator
{
    public function calculateDistance($source, $destination)
    {
        // Haversine formula for geographic distance
        $lat1 = deg2rad($source['latitude']);
        $lon1 = deg2rad($source['longitude']);
        $lat2 = deg2rad($destination['latitude']);
        $lon2 = deg2rad($destination['longitude']);
        
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        
        $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return 6371 * $c; // Distance in kilometers
    }
}
```

### Event System
```xml
<!-- events.xml -->
<event name="mab_source_selection_before">
    <observer name="custom_source_observer" 
              instance="Your\Module\Observer\SourceSelectionObserver" />
</event>

<event name="mab_source_allocation_complete">
    <observer name="allocation_observer" 
              instance="Your\Module\Observer\AllocationObserver" />
</event>
```

---

## 🗺️ Geographic Features

### GPS Integration
```javascript
// Frontend location detection
navigator.geolocation.getCurrentPosition(function(position) {
    const customerLocation = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude
    };
    
    // Send to source selection service
    sourceSelector.updateCustomerLocation(customerLocation);
});
```

### Address Geocoding
```php
// Convert address to coordinates
public function geocodeAddress($address)
{
    $geocodingService = $this->geocodingServiceFactory->create();
    $coordinates = $geocodingService->geocode([
        'street' => $address->getStreet(),
        'city' => $address->getCity(),
        'region' => $address->getRegion(),
        'postcode' => $address->getPostcode(),
        'country' => $address->getCountryId()
    ]);
    
    return $coordinates;
}
```

---

## 📊 Analytics & Reporting

### Source Performance Analytics
```php
// Track source performance metrics
public function trackSourcePerformance($sourceCode, $metrics)
{
    $performanceData = [
        'source_code' => $sourceCode,
        'fulfillment_rate' => $metrics['fulfilled'] / $metrics['requested'],
        'average_fulfillment_time' => $metrics['avg_time'],
        'shipping_cost_average' => $metrics['avg_shipping_cost'],
        'customer_satisfaction' => $metrics['satisfaction_score']
    ];
    
    $this->analytics->track('source_performance', $performanceData);
}
```

### Inventory Optimization Reports
```sql
-- Source utilization report
SELECT 
    s.source_code,
    s.name,
    COUNT(DISTINCT oi.order_id) as orders_fulfilled,
    SUM(oi.qty_ordered) as total_qty_shipped,
    AVG(o.shipping_amount) as avg_shipping_cost
FROM inventory_source s
LEFT JOIN inventory_source_item isi ON s.source_code = isi.source_code
LEFT JOIN sales_order_item oi ON isi.sku = oi.sku
LEFT JOIN sales_order o ON oi.order_id = o.entity_id
WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY s.source_code
ORDER BY orders_fulfilled DESC;
```

---

## 🐛 Troubleshooting

### Common Issues

#### 1. Sources Not Selected
```bash
# Check source configuration
php bin/magento inventory:source:list

# Verify source items
php bin/magento inventory:source-item:list --source-code=main_warehouse

# Reindex inventory
php bin/magento indexer:reindex inventory
```

#### 2. Incorrect Distance Calculations
```bash
# Test distance calculation
php bin/magento mab:source-selector:test-distance \
    --source="main_warehouse" \
    --lat="36.7538" \
    --lng="3.0588"

# Check geocoding service
php bin/magento mab:source-selector:test-geocoding \
    --address="123 Main St, Algiers, Algeria"
```

#### 3. Performance Issues
```bash
# Analyze source selection performance
php bin/magento mab:source-selector:performance-report

# Check cache hit ratio
php bin/magento cache:status | grep mab_source

# Monitor database queries
SET GLOBAL general_log = 'ON';
```

### Debug Configuration
```
Stores → Configuration → MAB Source Selector → Debug Settings
✅ Enable Debug Logging: Yes
📏 Log Selection Details: All/Critical only
🕰️ Performance Monitoring: Enable
```

---

## 🔒 Security Features

- **Source Access Control** - Role-based source permissions
- **Inventory Data Protection** - Secure stock level access
- **Location Privacy** - Encrypted GPS coordinates
- **API Security** - Secure geocoding API calls
- **Audit Logging** - Complete selection audit trail

---

## 🎆 Best Practices

### Inventory Management
1. **Regular Stock Audits** - Maintain accurate inventory levels
2. **Safety Stock Buffers** - Prevent stockouts with buffers
3. **Seasonal Adjustments** - Adjust rules for peak seasons
4. **Performance Monitoring** - Track source performance metrics
5. **Backup Sources** - Configure fallback sources

### Geographic Optimization
1. **Accurate Location Data** - Maintain precise source coordinates
2. **Regional Strategies** - Optimize for regional preferences
3. **Delivery Zone Mapping** - Map optimal delivery zones
4. **Cost vs Speed Balance** - Balance shipping cost and speed
5. **Customer Preferences** - Consider customer delivery preferences

---

## 🌟 Professional Support

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="../Core/view/adminhtml/web/images/mab-icon.svg" alt="Professional Developer" width="80" />
  </a>
  
  **Expert Inventory Management Solutions**
  
  [![Portfolio](https://img.shields.io/badge/Portfolio-mounir1.github.io-blue)](https://mounir1.github.io)
  [![Email](https://img.shields.io/badge/Email-mounir1%40gmail.com-red)](mailto:mounir.webdev@gmail.com)
</div>

---

## 📝 License

MIT License - Professional development with enterprise support.

---

<div align="center">
  <p><strong>Intelligent inventory management by professional developers</strong></p>
  <a href="https://mounir1.github.io" target="_blank">
    <img src="../Core/view/adminhtml/web/images/mab-signature.svg" alt="MAB Professional" width="200" />
  </a>
</div>
