var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping-address': {
                'Mab_CheckoutCustomization/js/view/shipping-address-mixin': true
            }
        }
    },
    map: {
        '*': {
            'Mab_CheckoutCustomization/js/model/set-default-region': 
                'Mab_CheckoutCustomization/js/model/set-default-region'
        }
    }
};
