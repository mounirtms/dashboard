/**
 * RequireJS configuration for Mab_CheckoutCustomization enhancements
 */
var config = {
    config: {
        mixins: {
            'Magento_Ui/js/form/element/abstract': {
                'Mab_CheckoutCustomization/js/mixin/validation-enhanced-mixin': true
            },
            'Magento_Checkout/js/view/form/element/email': {
                'Mab_CheckoutCustomization/js/mixin/validation-enhanced-mixin': true
            }
        }
    },
    map: {
        '*': {
            'checkoutAnalytics': 'Mab_CheckoutCustomization/js/checkout-analytics',
            'imageLoader': 'Mab_CheckoutCustomization/js/image-loader',
            'shippingMethodCardsEnhanced': 'Mab_CheckoutCustomization/js/view/shipping-method-cards-enhanced'
        }
    },
    shim: {
        'Mab_CheckoutCustomization/js/checkout-analytics': {
            deps: ['jquery', 'Magento_Checkout/js/model/quote']
        },
        'Mab_CheckoutCustomization/js/image-loader': {
            deps: ['jquery']
        }
    }
};
