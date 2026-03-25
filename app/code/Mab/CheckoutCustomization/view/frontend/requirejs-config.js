var config = {
    map: {
        '*': {
            'wilayaCommuneFilter': 'Mab_CheckoutCustomization/js/wilaya-commune-filter',
            'checkoutRegionFix': 'Mab_CheckoutCustomization/js/checkout-region-fix',
            'checkoutDefaultRegion': 'Mab_CheckoutCustomization/js/checkout-default-region'
        }
    },
    config: {
        mixins: {
            'Magento_Directory/js/region-updater': {
                'Mab_CheckoutCustomization/js/region-updater-mixin': true
            }
        }
    }
};
