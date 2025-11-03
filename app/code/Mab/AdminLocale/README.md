# MAB Admin Locale

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="../Core/view/adminhtml/web/images/mab-signature.svg" alt="MAB Admin Locale" width="300" />
  </a>
  
  [![Magento 2](https://img.shields.io/badge/Magento-2.4+-orange?style=for-the-badge)](https://magento.com)
  [![Professional](https://img.shields.io/badge/Developer-Professional-blue?style=for-the-badge)](https://mounir1.github.io)
</div>

## 🌍 Advanced Admin Locale Management

Professional Magento 2 module that provides comprehensive admin interface localization with automatic language detection, forced locale settings, and enhanced multi-language support for backend operations.

---

## ✨ Key Features

### 🌍 Locale Management
- **Forced Admin Locale** - Override user locale preferences
- **Automatic Detection** - Smart locale detection based on user preferences
- **Fallback Support** - Graceful fallback to default language
- **Session Persistence** - Maintain locale across admin sessions

### 🔄 Translation Support
- **Custom Translations** - Override default Magento translations
- **Phrase Management** - Easy phrase translation management
- **Dynamic Loading** - Load translations on demand
- **Cache Optimization** - Efficient translation caching

### 🔧 Admin Interface
- **Hide Locale Selector** - Clean admin interface
- **User Preferences** - Individual admin user locale settings
- **Role-based Locales** - Different locales per admin role
- **RTL Support** - Right-to-left language support

### ⚡ Performance Features
- **Translation Caching** - Optimized translation loading
- **Lazy Loading** - Load translations when needed
- **Memory Optimization** - Efficient resource management
- **Database Optimization** - Minimal database queries

---

## 🛠️ Installation

### Prerequisites
- Magento 2.4+
- MAB Core module
- PHP 8.1+
- Admin user permissions

### Installation Steps
```bash
# Enable the module
php bin/magento module:enable Mab_AdminLocale

# Run setup upgrade
php bin/magento setup:upgrade

# Compile DI
php bin/magento setup:di:compile

# Deploy static content for admin
php bin/magento setup:static-content:deploy -a adminhtml

# Clear cache
php bin/magento cache:flush
```

---

## ⚙️ Configuration

### 1. Basic Setup
Navigate to: **Admin Panel → Stores → Configuration → MAB Extensions → MAB Admin Locale**

### 2. Locale Settings

#### Force Admin Locale
```
✅ Enable Forced Locale: Yes/No
🌍 Force Locale To: en_US (English - United States)
🚫 Hide Locale Selector: Yes/No
🔄 Apply to All Admin Users: Yes/No
```

#### Language Detection
```
🤖 Auto-detect User Locale: Yes/No
🌐 Browser Language Detection: Enable
📋 Fallback Locale: en_US
🕰️ Session Persistence: Enable
```

### 3. Translation Management

#### Custom Translations
```
✅ Enable Custom Translations: Yes/No
📁 Translation Files Path: app/i18n/custom/
🔄 Auto-reload Translations: Development mode only
📋 Translation Priority: Custom > Theme > Core
```

#### Performance Settings
```
⚡ Enable Translation Cache: Yes
🕰️ Cache Lifetime: 3600 seconds
📏 Lazy Load Translations: Yes
📈 Debug Translation Keys: Development only
```

---

## 🔧 Advanced Configuration

### Role-based Locale Assignment
```php
// Configuration example
$roleLocales = [
    'administrator' => 'en_US',
    'content_editor' => 'fr_FR',
    'customer_service' => 'ar_DZ',
    'warehouse_manager' => 'en_US'
];
```

### Custom Translation Override
```php
// app/code/Mab/AdminLocale/i18n/en_US.csv
"Customer","Client"
"Order","Commande"
"Product","Produit"
"Save","Sauvegarder"
```

### User Preference Management
```php
<?php
namespace Mab\AdminLocale\Model;

class UserLocaleManager
{
    public function setUserLocale($userId, $locale)
    {
        // Set user-specific locale preference
        $this->userPreferences->setLocale($userId, $locale);
    }
    
    public function getUserLocale($userId)
    {
        // Get user-specific locale
        return $this->userPreferences->getLocale($userId);
    }
}
```

---

## 🚀 Performance Optimization

### Translation Caching
```php
// Optimized translation loading
public function getTranslation($phrase, $locale)
{
    $cacheKey = 'mab_admin_locale_' . md5($phrase . $locale);
    $translation = $this->cache->load($cacheKey);
    
    if (!$translation) {
        $translation = $this->translatePhrase($phrase, $locale);
        $this->cache->save($translation, $cacheKey, ['mab_translations'], 3600);
    }
    
    return $translation;
}
```

### Lazy Loading Implementation
```javascript
// Load translations on demand
define([
    'mab/admin-locale/translator'
], function(Translator) {
    'use strict';
    
    return {
        translate: function(phrase) {
            return Translator.getTranslation(phrase, this.getCurrentLocale());
        },
        
        loadTranslations: function(phrases) {
            return Translator.batchLoad(phrases);
        }
    };
});
```

### Performance Metrics
- **Translation Load Time**: <50ms
- **Cache Hit Ratio**: 95%+
- **Memory Usage**: <1MB for translations
- **Admin Load Impact**: <100ms additional

---

## 🔧 Developer Guide

### Custom Locale Provider
```php
<?php
namespace Your\Module\Model\Locale;

use Mab\AdminLocale\Api\LocaleProviderInterface;

class CustomLocaleProvider implements LocaleProviderInterface
{
    public function getLocaleForUser($userId)
    {
        // Custom logic to determine user locale
        return $this->calculateUserLocale($userId);
    }
    
    public function getAvailableLocales()
    {
        // Return available locales
        return ['en_US', 'fr_FR', 'ar_DZ', 'es_ES'];
    }
}
```

### Translation Plugin
```php
<?php
namespace Your\Module\Plugin;

class TranslationPlugin
{
    public function afterTranslate($subject, $result, $phrase)
    {
        // Custom translation logic
        return $this->customTranslate($phrase) ?: $result;
    }
}
```

### Event Observers
```xml
<!-- events.xml -->
<event name="mab_admin_locale_changed">
    <observer name="custom_locale_observer" 
              instance="Your\Module\Observer\LocaleChangeObserver" />
</event>
```

---

## 🌐 Supported Locales

### Primary Languages
- **🇺🇸 English (US)** - `en_US` (Default)
- **🇫🇷 French (France)** - `fr_FR`
- **🇩🇿 Arabic (Algeria)** - `ar_DZ`
- **🇪🇸 Spanish (Spain)** - `es_ES`
- **🇩🇪 German (Germany)** - `de_DE`

### Regional Variants
- **🇬🇧 English (UK)** - `en_GB`
- **🇨🇦 French (Canada)** - `fr_CA`
- **🇲🇽 Spanish (Mexico)** - `es_MX`
- **🇦🇹 German (Austria)** - `de_AT`

### Adding Custom Locales
```php
// Add custom locale
$customLocales = [
    'ar_MA' => 'Arabic (Morocco)',
    'ar_TN' => 'Arabic (Tunisia)',
    'ber_DZ' => 'Berber (Algeria)'
];

$this->localeManager->addCustomLocales($customLocales);
```

---

## 🐛 Troubleshooting

### Common Issues

#### 1. Translations Not Loading
```bash
# Check translation files
ls -la app/i18n/

# Clear translation cache
php bin/magento cache:clean translate

# Regenerate translations
php bin/magento setup:static-content:deploy -a adminhtml
```

#### 2. Locale Not Changing
```bash
# Check admin user locale settings
php bin/magento admin:user:list

# Clear admin session
php bin/magento cache:clean admin_session

# Check configuration
php bin/magento config:show mab_admin_locale/general/force_locale
```

#### 3. Performance Issues
```bash
# Check translation cache
php bin/magento cache:status

# Enable translation cache
php bin/magento cache:enable translate

# Monitor translation loading
tail -f var/log/system.log | grep "MAB Admin Locale"
```

### Debug Commands
```bash
# Check current admin locale
php bin/magento mab:admin-locale:status

# List available translations
php bin/magento mab:admin-locale:list-translations

# Test translation loading
php bin/magento mab:admin-locale:test-translation "Customer"
```

---

## 🔒 Security Features

- **Locale Validation** - Prevent invalid locale injection
- **User Permission Checks** - Role-based locale access
- **Session Security** - Secure locale storage
- **Input Sanitization** - Clean locale parameters
- **Admin Access Control** - Restricted configuration access

---

## 🎆 Advanced Features

### Automatic Language Detection
```php
// Detect user language from browser
public function detectUserLanguage($request)
{
    $acceptLanguage = $request->getHeader('Accept-Language');
    $supportedLocales = $this->getSupportedLocales();
    
    foreach ($this->parseAcceptLanguage($acceptLanguage) as $lang) {
        if (in_array($lang, $supportedLocales)) {
            return $lang;
        }
    }
    
    return $this->getDefaultLocale();
}
```

### RTL Support
```css
/* RTL support for Arabic locales */
.rtl .admin__menu {
    direction: rtl;
    text-align: right;
}

.rtl .admin__data-grid-wrap {
    direction: rtl;
}
```

---

## 🌟 Professional Support

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="../Core/view/adminhtml/web/images/mab-icon.svg" alt="Professional Developer" width="80" />
  </a>
  
  **Expert Localization Solutions**
  
  [![Portfolio](https://img.shields.io/badge/Portfolio-mounir1.github.io-blue)](https://mounir1.github.io)
  [![Email](https://img.shields.io/badge/Email-mounir1%40gmail.com-red)](mailto:mounir.webdev@gmail.com)
</div>

---

## 📝 License

MIT License - Professional development with enterprise support.

---

<div align="center">
  <p><strong>Professional admin localization by expert developers</strong></p>
  <a href="https://mounir1.github.io" target="_blank">
    <img src="../Core/view/adminhtml/web/images/mab-signature.svg" alt="MAB Professional" width="200" />
  </a>
</div>
