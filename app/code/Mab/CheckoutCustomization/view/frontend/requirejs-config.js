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
                'Mab_CheckoutCustomization/js/mixin/safe-grand-total-mixin': true
            },
            // Fix shipping step validation to work with custom cards
            'Magento_Checkout/js/view/shipping': {
                'Mab_CheckoutCustomization/js/mixin/shipping-step-validator-mixin': true
            }
        }
    },
    map: {
        '*': {
            'checkoutAnalytics': 'Mab_CheckoutCustomization/js/checkout-analytics',
            'imageLoader': 'Mab_CheckoutCustomization/js/image-loader',
            'shippingMethodCardsEnhanced': 'Mab_CheckoutCustomization/js/view/shipping-method-cards-enhanced',
            // Map Amasty gift-code to our implementation
            'Amasty_GiftCardAccount/js/action/gift-code': 'Mab_CheckoutCustomization/js/action/gift-code'
        }
    },
    paths: {
        // Add jQuery UI widget factory dependency
        'jquery/ui': 'jquery/jquery-ui',
        // Ensure accordion is available
        'jquery-ui-modules/accordion': 'jquery/ui-modules/widgets/accordion',
        'jquery-ui-modules/widget': 'jquery/ui-modules/widget'
    },
    shim: {
        'Mab_CheckoutCustomization/js/checkout-analytics': {
            deps: ['jquery', 'Magento_Checkout/js/model/quote']
        },
        'Mab_CheckoutCustomization/js/image-loader': {
            deps: ['jquery']
        },
        // Ensure jQuery UI dependencies are loaded
        'jquery/ui': {
            deps: ['jquery']
        },
        'jquery-ui-modules/accordion': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        }
    },
    deps: [
        // Preload jQuery UI to avoid compat fallback
        'jquery/ui'
    ]
};
