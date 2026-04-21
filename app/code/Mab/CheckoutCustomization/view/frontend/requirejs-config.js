/**
 * RequireJS configuration for Mab_CheckoutCustomization enhancements
 * Includes fixes for Amasty Gift Card errors and jQuery UI dependencies
 */
var config = {
    config: {
        mixins: {
            'Magento_Ui/js/form/element/abstract': {
                'Mab_CheckoutCustomization/js/mixin/validation-enhanced-mixin': true
            },
            'Magento_Checkout/js/view/form/element/email': {
                'Mab_CheckoutCustomization/js/mixin/validation-enhanced-mixin': true
            },
            // Mixin for Magento's directoryRegionUpdater jQuery widget (non-checkout pages)
            'Magento_Directory/js/region-updater': {
                'Mab_CheckoutCustomization/js/region-updater-mixin': true
            },
            // Mixin for Checkout's regionUpdater jQuery widget
            'Magento_Checkout/js/region-updater': {
                'Mab_CheckoutCustomization/js/region-updater-mixin': true
            },
            // Override Amasty grand-total mixin with safe version
            'Magento_Checkout/js/view/summary/abstract-total': {
                'Amasty_GiftCardAccount/js/mixins/grand-total-mixin': false,
                'Mab_CheckoutCustomization/js/mixin/safe-grand-total-mixin': true
            },
            'Magento_Tax/js/view/checkout/summary/grand-total': {
                'Amasty_GiftCardAccount/js/mixins/grand-total-mixin': false,
                'Mab_CheckoutCustomization/js/mixin/safe-grand-total-mixin': true
            },
            // Fix shipping step validation to work with custom cards AND initialize cards UI
            'Magento_Checkout/js/view/shipping': {
                'Mab_CheckoutCustomization/js/mixin/shipping-step-validator-mixin': true,
                'Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin': true
            }
        }
    },
    map: {
        '*': {
            // Map Amasty gift-code to our implementation
            'Amasty_GiftCardAccount/js/action/gift-code': 'Mab_CheckoutCustomization/js/action/gift-code'
        }
    },
    shim: {
        // jQuery UI core dependency
        'jquery/ui': {
            deps: ['jquery']
        }
    }
};
