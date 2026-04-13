/**
 * Discount configuration model
 * Controls discount field visibility in checkout
 */
define([], function () {
    'use strict';

    return {
        /**
         * Check if discount is disabled in checkout
         * Discount is disabled in theme layout (checkout_index_index.xml)
         * @returns {boolean}
         */
        isDiscountDisabled: function () {
            return true;
        }
    };
});
