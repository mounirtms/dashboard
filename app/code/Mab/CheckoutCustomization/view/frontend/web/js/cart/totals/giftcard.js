/**
 * Mab_CheckoutCustomization - Gift Card Totals Component
 * Only renders for logged-in customers
 */
define([
    'Amasty_GiftCardAccount/js/cart/totals/giftcard',
    'Magento_Customer/js/model/customer'
], function (Component, customer) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/cart/totals/giftcard'
        },

        isLoggedIn: function () {
            return customer.isLoggedIn();
        },

        isVisible: function () {
            if (!this.isLoggedIn()) {
                return false;
            }
            return this._super();
        }
    });
});
