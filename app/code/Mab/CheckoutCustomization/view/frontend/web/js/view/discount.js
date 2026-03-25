/**
 * Discount view component (placeholder)
 * Discount functionality is disabled for this store
 */
define([
    'uiComponent'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/summary/discount-disabled'
        },

        isDisplayed: function () {
            return false;
        }
    });
});
