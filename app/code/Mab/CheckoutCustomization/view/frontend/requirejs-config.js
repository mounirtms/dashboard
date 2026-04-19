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
        // jQuery UI dependencies - complete set to avoid compat fallback
        'jquery/ui': 'jquery/jquery-ui',
        'jquery-ui-modules/widget': 'jquery/ui-modules/widget',
        'jquery-ui-modules/accordion': 'jquery/ui-modules/widgets/accordion',
        'jquery-ui-modules/menu': 'jquery/ui-modules/widgets/menu',
        'jquery-ui-modules/slider': 'jquery/ui-modules/widgets/slider',
        'jquery-ui-modules/datepicker': 'jquery/ui-modules/widgets/datepicker',
        'jquery-ui-modules/draggable': 'jquery/ui-modules/widgets/draggable',
        'jquery-ui-modules/droppable': 'jquery/ui-modules/widgets/droppable',
        'jquery-ui-modules/sortable': 'jquery/ui-modules/widgets/sortable',
        'jquery-ui-modules/resizable': 'jquery/ui-modules/widgets/resizable',
        'jquery-ui-modules/dialog': 'jquery/ui-modules/widgets/dialog',
        'jquery-ui-modules/tabs': 'jquery/ui-modules/widgets/tabs'
    },
    shim: {
        'Mab_CheckoutCustomization/js/checkout-analytics': {
            deps: ['jquery', 'Magento_Checkout/js/model/quote']
        },
        'Mab_CheckoutCustomization/js/image-loader': {
            deps: ['jquery']
        },
        // jQuery UI core dependency
        'jquery/ui': {
            deps: ['jquery']
        },
        // jQuery UI widget factory
        'jquery-ui-modules/widget': {
            deps: ['jquery']
        },
        // All jQuery UI widgets depend on widget factory
        'jquery-ui-modules/accordion': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/menu': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/slider': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/datepicker': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/draggable': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/droppable': {
            deps: ['jquery', 'jquery-ui-modules/widget', 'jquery-ui-modules/draggable']
        },
        'jquery-ui-modules/sortable': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/resizable': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/dialog': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        },
        'jquery-ui-modules/tabs': {
            deps: ['jquery', 'jquery-ui-modules/widget']
        }
    },
    deps: [
        // Preload jQuery and jQuery UI to avoid compat fallback
        'jquery',
        'jquery/ui',
        'jquery-ui-modules/widget'
    ]
};
