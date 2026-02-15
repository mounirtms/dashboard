# Mab_ElasticsearchFix Module

## Overview
This module provides a fixed XSD schema for Elasticsearch configuration to resolve the non-deterministic content model error that occurs in Magento's core Elasticsearch module.

## Problem Statement
The original XSD schema in `vendor/magento/module-elasticsearch/etc/esconfig.xsd` contains a non-deterministic content model that causes validation errors:

```
Magento\Framework\Config\Dom\ValidationSchemaException: 
Processed schema file: /vendor/magento/module-elasticsearch/etc/esconfig.xsd
complex type 'mixedDataType': The content model is not determinist.
Line: 18
```

## Solution
This module overrides the problematic XSD file with a fixed version that:

1. **Changes `xs:choice` to `xs:sequence`** in `configType` - Makes the content model deterministic
2. **Simplifies `mixedDataType`** - Uses only `xs:any` with `processContents="skip"`
3. **Adds `mixed="true"`** - Allows text content in elements

## Installation
The module is automatically loaded by Magento's component registration system.

### Enable the module:
```bash
php bin/magento module:enable Mab_ElasticsearchFix
php bin/magento setup:upgrade
php bin/magento cache:flush
```

### Verify installation:
```bash
php bin/magento module:status Mab_ElasticsearchFix
```

## Files
- `registration.php` - Module registration
- `etc/module.xml` - Module configuration
- `etc/esconfig.xsd` - Fixed XSD schema (overrides vendor file)

## Technical Details

### Original XSD (Problematic):
```xml
<xs:complexType name="mixedDataType">
    <xs:choice>
        <xs:element type="xs:string" name="default" minOccurs="0" maxOccurs="1" />
        <xs:any processContents="lax" minOccurs="0" maxOccurs="unbounded" />
    </xs:choice>
</xs:complexType>
```

### Fixed XSD:
```xml
<xs:complexType name="mixedDataType" mixed="true">
    <xs:sequence>
        <xs:any processContents="skip" minOccurs="0" maxOccurs="unbounded" />
    </xs:sequence>
</xs:complexType>
```

## Why This Works
- **`xs:choice`** creates non-determinism when combined with wildcards because the validator can't decide whether an element matches the specific `<default>` element or the `<xs:any>` wildcard
- **`xs:sequence`** with only `<xs:any>` is deterministic because there's only one path
- **`processContents="skip"`** tells the validator to not validate wildcard elements, avoiding conflicts
- **`mixed="true"`** allows text content between elements, supporting the actual XML structure

## Compatibility
- Magento 2.4.x
- Requires Magento_Elasticsearch module to be installed

## Maintenance
This module provides a permanent fix that persists across `composer update` operations, unlike direct vendor file modifications.

## Author
Mab Development Team

## Version
1.0.0 - Initial release (February 15, 2026)
