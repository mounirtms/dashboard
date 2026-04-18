/**
 * Mab_CheckoutCustomization - Disabled Discount Component
 * Replaces the discount component with a hidden placeholder
 * that keeps the UI component tree intact without displaying anything.
 */
define([
    'uiComponent'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/summary/discount-disabled',
            visible: false
        },

        /** @returns {Boolean} */
        isDisplayed: function () {
            return false;
        },

        /** @returns {Boolean} */
        isVisible: function () {
            return false;
        }
    });
});
