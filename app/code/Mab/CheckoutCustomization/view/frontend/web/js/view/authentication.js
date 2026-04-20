/**
 * Custom Authentication Component
 * Uses default Magento authentication logic with custom template
 */
define([
    'Magento_Checkout/js/view/authentication'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/authentication.html'
        }
    });
});
