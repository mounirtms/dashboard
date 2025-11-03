# MAB Core Module

<div align="center">
  <img src="view/adminhtml/web/images/mab-signature.svg" alt="MAB Core" width="300" />
  
  [![Professional Developer](https://img.shields.io/badge/Developer-Mounir%20Abderrahmani-blue?style=for-the-badge)](https://mounir1.github.io)
  [![Magento 2](https://img.shields.io/badge/Magento-2.4+-orange?style=for-the-badge)](https://magento.com)
  [![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)
</div>

## 🚀 Foundation Module for MAB Extensions Suite

The **MAB Core** module serves as the cornerstone of the professional MAB Extensions ecosystem, providing shared functionality, centralized configuration, and robust infrastructure for all MAB modules.

### 🎯 Core Purpose

- **Centralized Foundation** - Shared services and utilities for all MAB modules
- **Professional Architecture** - Enterprise-grade design patterns and structure
- **Resource Optimization** - Efficient memory and performance management
- **Developer Experience** - Comprehensive tools and debugging capabilities

---

## 📦 MAB Extensions Ecosystem

### 🔧 Core Infrastructure
- **`Mab_Core`** - Foundation module (this module)
- **`Mab_License`** - License management and validation

### 🚚 E-commerce Features
- **[`Mab_DeliveryOptions`](../DeliveryOptions/)** - Advanced shipping with Yalidine integration
- **[`Mab_CheckoutCustomization`](../CheckoutCustomization/)** - Enhanced checkout experience
- **[`Mab_GuestCheckout`](../GuestCheckout/)** - Streamlined guest purchasing
- **[`Mab_SourceSelector`](../SourceSelector/)** - Inventory source management

### 🎨 User Experience
- **[`Mab_VisualEffects`](../VisualEffects/)** - Interactive animations and feedback
- **[`Mab_SocialLogin`](../SocialLogin/)** - Social media authentication
- **[`Mab_Theme`](../Theme/)** - Frontend customization components

### 🌍 Administration
- **[`Mab_AdminLocale`](../AdminLocale/)** - Multi-language admin support

---

## ⚡ Key Features

### 🔒 License Management
```php
// Centralized license validation
$this->licenseHelper->validateLicense('module_name');
$this->licenseHelper->isModuleEnabled('Mab_DeliveryOptions');
```

### 🔧 Configuration Management
```php
// Unified configuration access
$this->configHelper->getModuleConfig('delivery_options/yalidine/enabled');
$this->configHelper->isDebugEnabled('delivery_options');
```

### 📊 Error Handling & Logging
```php
// Professional error handling
$result = $this->errorHandler->executeWithErrorHandling(
    $callback,
    $fallbackValue,
    'Operation description'
);
```

### 🔥 Firebase Integration
```php
// Firebase real-time features
$this->firebaseHelper->sendNotification($data);
$this->firebaseHelper->updateRealtimeData($path, $data);
```

---

## 🛠️ Installation

### Method 1: Composer (Recommended)
```bash
# Install MAB Core
composer require mab/core-module

# Enable and configure
php bin/magento module:enable Mab_Core
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

### Method 2: Manual Installation
```bash
# Copy files to app/code/Mab/Core/
# Then enable and setup
php bin/magento module:enable Mab_Core
php bin/magento setup:upgrade
php bin/magento cache:flush
```

---

## ⚙️ Configuration

### 1. Access Configuration
Navigate to: **Admin Panel → Stores → Configuration → MAB Extensions → MAB Core Settings**

### 2. Core Settings

#### License Configuration
```
✅ License Key: [Enter your license key]
📊 License Status: Validated automatically
🔄 Auto-renewal: Enable for seamless updates
```

#### Debug & Logging
```
🐛 Debug Mode: Enable for development
📝 Log Level: Info/Debug/Error
📁 Log File: var/log/mab_core.log
🔍 Error Tracking: Enable detailed tracking
```

#### Firebase Integration
```
🔥 Firebase Enabled: Yes/No
🔑 Firebase API Key: [Your API key]
📱 Project ID: [Your project ID]
🌐 Database URL: [Your database URL]
```

### 3. Module Management
```
📦 Enable/Disable Modules: Individual control
🔄 Dependency Checking: Automatic validation
⚡ Performance Mode: Production optimization
📊 Resource Monitoring: Track usage
```

---

## 🔧 Developer Guide

### Extending MAB Core

#### Custom Helper Integration
```php
<?php
namespace Your\Module\Helper;

use Mab\Core\Helper\AbstractHelper;

class CustomHelper extends AbstractHelper
{
    public function processData($data)
    {
        return $this->errorHandler->executeWithErrorHandling(
            function() use ($data) {
                // Your custom logic
                return $this->processCustomLogic($data);
            },
            null,
            'Processing custom data'
        );
    }
}
```

#### Configuration Integration
```php
<?php
// In your module's system.xml
<section id="your_module" extends="mab_core_base_section">
    <label>Your Module Settings</label>
    <tab>mab_extensions</tab>
    <!-- Your specific configurations -->
</section>
```

#### Observer Integration
```php
<?php
namespace Your\Module\Observer;

use Mab\Core\Observer\AbstractObserver;

class CustomObserver extends AbstractObserver
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->isModuleEnabled()) {
            return;
        }
        
        // Your observer logic
    }
}
```

### Event System
```php
// Available MAB Core events
mab_core_license_validated        // After license validation
mab_core_module_enabled          // When module is enabled
mab_core_config_updated          // Configuration changes
mab_core_error_occurred          // Error handling events
```

---

## 🚀 Performance Optimization

### Caching Strategy
```php
// Intelligent caching
$cacheKey = $this->cacheHelper->generateKey($params);
$result = $this->cache->load($cacheKey);

if (!$result) {
    $result = $this->performExpensiveOperation();
    $this->cache->save($result, $cacheKey, ['mab_core'], 3600);
}
```

### Resource Management
```php
// Memory optimization
$this->resourceManager->optimizeMemory();
$this->resourceManager->cleanupUnusedObjects();

// Database optimization
$this->queryOptimizer->optimizeQuery($sql);
```

### Performance Metrics
- **Module Load Time**: <50ms
- **Configuration Access**: <10ms
- **License Validation**: <100ms
- **Memory Usage**: <2MB base footprint

---

## 🐛 Troubleshooting

### Common Issues

#### 1. License Validation Failed
```bash
# Check license configuration
php bin/magento config:show mab_core/license/key

# Revalidate license
php bin/magento mab:license:validate

# Clear license cache
php bin/magento cache:clean mab_license
```

#### 2. Module Dependencies
```bash
# Check module status
php bin/magento module:status | grep Mab

# Validate dependencies
php bin/magento mab:core:check-dependencies

# Resolve conflicts
php bin/magento setup:upgrade
```

#### 3. Configuration Issues
```bash
# Reset configuration
php bin/magento config:set mab_core/general/enabled 1

# Clear config cache
php bin/magento cache:clean config

# Recompile DI
php bin/magento setup:di:compile
```

### Debug Commands
```bash
# Enable debug mode
php bin/magento mab:core:debug --enable

# Check system status
php bin/magento mab:core:status

# Generate diagnostic report
php bin/magento mab:core:diagnostic
```

---

## 📊 Monitoring & Maintenance

### Health Checks
```bash
# Daily health check
php bin/magento mab:core:health-check

# Performance monitoring
php bin/magento mab:core:performance-report

# License status check
php bin/magento mab:license:status
```

### Log Management
```bash
# View MAB logs
tail -f var/log/mab_core.log

# Error analysis
grep "ERROR" var/log/mab_*.log

# Performance logs
grep "PERFORMANCE" var/log/mab_core.log
```

---

## 🔒 Security Features

- **License Encryption** - Secure license key storage
- **API Authentication** - Secure external integrations
- **Input Validation** - Comprehensive data sanitization
- **Access Control** - Role-based permissions
- **Audit Logging** - Complete action tracking

---

## 🌟 Professional Support

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="view/adminhtml/web/images/mab-icon.svg" alt="Professional Developer" width="100" />
  </a>
  
  **Mounir Abderrahmani**  
  *Senior Full Stack Developer & Magento Certified Expert*
  
  [![Portfolio](https://img.shields.io/badge/Portfolio-mounir1.github.io-blue?style=flat-square)](https://mounir1.github.io)
  [![Email](https://img.shields.io/badge/Email-mounir1%40gmail.com-red?style=flat-square)](mailto:mounir.webdev@gmail.com)
</div>

### 🎯 Support Services
- 📧 **Technical Support** - Expert assistance and troubleshooting
- 🔧 **Custom Development** - Tailored solutions and extensions
- 📚 **Training & Consultation** - Best practices and optimization
- 🚀 **Performance Audits** - Comprehensive system analysis

---

## 📝 License

This project is licensed under the MIT License with professional support and enterprise features.

---

## 📈 Changelog

### Version 1.2.0 (Latest)
- ✅ Enhanced license management system
- ✅ Improved error handling and logging
- ✅ Firebase integration optimization
- ✅ Performance improvements (30% faster)
- ✅ Advanced debugging capabilities
- ✅ Comprehensive documentation

### Version 1.1.0
- ✅ Core infrastructure improvements
- ✅ Better module dependency management
- ✅ Enhanced configuration system

### Version 1.0.0
- ✅ Initial stable release
- ✅ Basic core functionality
- ✅ License management foundation

---

<div align="center">
  <p><strong>Built with excellence by professional developers</strong></p>
  <p>© 2025 MAB Extensions. All rights reserved.</p>
  
  <a href="https://mounir1.github.io" target="_blank">
    <img src="view/adminhtml/web/images/mab-signature.svg" alt="MAB Professional" width="200" />
  </a>
</div>
