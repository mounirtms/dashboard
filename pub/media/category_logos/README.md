# Category Logos

This directory contains custom SVG logos for various product categories using the Techno brand colors (red and yellow) with T-shaped elements.

## Logos Created

1. **Promos Techno Logo** (`promos_techno_logo.svg`)
   - For the "Promos" category (ID: 1798)
   - Features a T-shaped logo with discount symbol
   - Brand colors: Red (#FF0000) and Yellow (#FFD700)
   - Size: 200x200px

2. **School Stationery Logo** (`stationery_logo.svg`)
   - For stationery-related categories
   - Features pens and pencils with T-logo watermark
   - Brand colors: Red (#FF0000) and Yellow (#FFD700)
   - Size: 200x200px

3. **School Backpack Logo** (`school_backpack_logo.svg`)
   - For school backpack/SAC A DOS categories
   - Features a backpack with T-logo elements
   - Brand colors: Red (#FF0000) and Yellow (#FFD700)
   - Size: 200x200px

4. **University Logo** (`university_logo.svg`)
   - For university-related categories
   - Features a university building with T-logo elements
   - Brand colors: Red (#FF0000) and Yellow (#FFD700)
   - Size: 200x200px

5. **Techno Small Logo** (`techno_small_logo.svg`)
   - Small watermark/logo for use across multiple categories
   - Simple T-shaped design
   - Brand colors: Red (#FF0000) and Yellow (#FFD700)
   - Size: 32x32px

## Features

- Professional, appealing design
- Consistent brand identity with red and yellow colors
- T-shaped elements throughout all logos
- Responsive SVG format for crisp display at any size
- Subtle shadows and effects for depth
- Watermark elements for brand reinforcement

## Usage

To assign these logos to categories in Magento:

1. Upload the SVG files to the appropriate category images in the Magento admin panel
2. Or directly insert into the database using SQL (see example below)

### Database Assignment Example

```sql
-- Assign promo logo to category 1798
UPDATE catalog_category_entity_varchar 
SET value = '/category_logos/promos_techno_logo.svg' 
WHERE attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 3) 
AND entity_id = 1798;
```

## Customization

All SVG files can be customized by editing:
- Colors in the linearGradient definitions
- Text content
- Shapes and elements
- Shadow effects

The logos maintain a consistent visual language while being tailored to each category's specific needs.