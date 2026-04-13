var config = {
    map: {
        '*': {
            'wilayaCommuneFilter': 'Mab_CheckoutCustomization/js/wilaya-commune-filter',
            'checkoutRegionFix': 'Mab_CheckoutCustomization/js/checkout-region-fix',
            'checkoutDefaultRegion': 'Mab_CheckoutCustomization/js/checkout-default-region',
            'shippingMethodCards': 'Mab_CheckoutCustomization/js/view/shipping-method-cards',
            'giftCardCart': 'Mab_CheckoutCustomization/js/view/gift-card-cart'
        }
    },
    config: {
        mixins: {
            'Magento_Directory/js/region-updater': {
                'Mab_CheckoutCustomization/js/region-updater-mixin': true
            },
            'Magento_Checkout/js/view/shipping': {
                'Mab_CheckoutCustomization/js/mixin/shipping-cards-mixin': true
            }
        }
    }
};
