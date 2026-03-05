var config = {
    map: {
        '*': {
            // Wilaya-Commune filter for Algeria checkout
            'wilayaCommuneFilter': 'Mab_CheckoutCustomization/js/wilaya-commune-filter',
            // Checkout region fix for Algeria wilayas
            'checkoutRegionFix': 'Mab_CheckoutCustomization/js/checkout-region-fix'
        }
    },
    config: {
        mixins: {
            // Override Magento region updater for Algeria wilaya support
            'Magento_Directory/js/region-updater': {
                'Mab_CheckoutCustomization/js/region-updater-mixin': true
            }
        }
    }
};
