/**
 * Mab_CheckoutCustomization - Discount View (disabled placeholder)
 * Discount code entry is disabled for this store via layout XML.
 */
define([
    'uiComponent'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/summary/discount-disabled'
        },

        /** @returns {Boolean} */
        isDisplayed: function () {
            return false;
        }
    });
});
