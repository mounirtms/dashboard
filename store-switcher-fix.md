


# Store Switcher Fix Implementation

## Issues Fixed

1. **File Permissions**: Set proper permissions for all static content and media files
2. **Static Content Deployment**: Redeployed static content for fr_FR locale only
3. **Cache Clearing**: Cleared all cache types to ensure changes take effect
4. **Store Switcher**: Confirmed store switcher is enabled

## Problems Identified

1. Missing media files for SILA store (logo, wysiwyg images)
2. Incorrect MIME types due to missing files
3. JavaScript errors due to missing RequireJS
4. Static content permissions issues

## Immediate Fixes Applied

1. Set proper permissions on all files:
   ```bash
   find pub/static/ -type d -exec chmod 755 {} \;
   find pub/static/ -type f -exec chmod 644 {} \;
   find pub/media/ -type d -exec chmod 755 {} \;
   find pub/media/ -type f -exec chmod 644 {} \;
   ```

2. Redeployed static content:
   ```bash
   php bin/magento setup:static-content:deploy fr_FR --area frontend --no-interaction -f
   ```

3. Cleared cache:
   ```bash
   php bin/magento cache:clean
   ```

## Additional Recommendations

1. **Add Store Switcher to Header/Footer**:
   Create a custom template file for the store switcher and add it to your theme's header or footer.

2. **Fix Missing Media Files**:
   Upload the missing media files to the appropriate directories:
   - pub/media/logo/stores/6/
   - pub/media/wysiwyg/slidershow/techno/
   - pub/media/wysiwyg/menuicons/
   - pub/media/logomobile/default/

3. **Verify Base URLs**:
   Make sure the base URLs are correctly configured for both stores:
   ```sql
   SELECT * FROM core_config_data WHERE path LIKE '%base%url%' AND scope = 'websites';
   ```

4. **Check Store Configuration**:
   Verify that the store configuration is correct:
   ```bash
   php bin/magento store:list
   php bin/magento store:website:list
   ```

## Store Switcher Implementation

To add a store switcher to your header or footer:

1. Create a template file in your theme:
   `app/design/frontend/Sm/market/Magento_Theme/templates/html/store-switcher.phtml`

2. Add the following code to the template file:
   ```php
   <?php
   /** @var $block \Magento\Store\Block\Switcher */
   ?>
   <?php if (count($block->getStores()) > 1): ?>
   <div class="switcher store switcher-store" id="store-switcher">
       <strong class="label switcher-label" role="heading" aria-level="p"><span><?= $block->escapeHtml(__('Select Store:')) ?></span></strong>
       <div class="actions dropdown options switcher-options">
           <ul class="dropdown switcher-dropdown" data-mage-init='{"dropdownDialog":{
               "appendTo":"#store-switcher",
               "triggerTarget":".switcher-label",
               "closeOnMouseLeave": false,
               "triggerClass":"active",
               "parentClass":"active",
               "buttons":null}}'>
               <?php foreach ($block->getStores() as $_store): ?>
                   <?php if ($_store->getId() != $block->getCurrentStoreId()): ?>
                       <li class="view-<?= $block->escapeHtml($_store->getCode()) ?> option">
                           <a href="<?= $block->escapeUrl($block->getTargetStorePostData($_store)['action']) ?>" 
                              data-post='<?= /* @noEscape */ json_encode($block->getTargetStorePostData($_store)) ?>'
                              title="<?= $block->escapeHtmlAttr($_store->getName()) ?>">
                               <?= $block->escapeHtml($_store->getName()) ?>
                           </a>
                       </li>
                   <?php endif; ?>
               <?php endforeach; ?>
           </ul>
       </div>
   </div>
   <?php endif; ?>
   ```

3. Add the block to your layout in:
   `app/design/frontend/Sm/market/Magento_Theme/layout/default.xml`
   
   ```xml
   <referenceContainer name="header.panel">
       <block class="Magento\Store\Block\Switcher" name="store_switcher" as="store_switcher" template="Magento_Theme::html/store-switcher.phtml"/>
   </referenceContainer>
   ```

## Verification Steps

1. Check that all 404 errors are resolved
2. Verify that the store switcher appears on the frontend
3. Test switching between stores
4. Confirm that all images and styles load correctly

This fix should resolve the immediate issues with the SILA store while keeping the default store unaffected.