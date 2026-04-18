/**
 * Checkout configuration model
 * Provides access to checkout settings from window.checkoutConfig
 */
define([], function () {
    'use strict';

    return {
        /**
         * Get checkout config value
         * @param {string} key
         * @returns {*}
         */
        getValue: function (key) {
            if (window.checkoutConfig && window.checkoutConfig[key] !== undefined) {
                return window.checkoutConfig[key];
            }
            return null;
        },

        /**
         * Get default country ID
         * @returns {string}
         */
        getDefaultCountryId: function () {
            return this.getValue('defaultCountryId') || 'DZ';
        }
    };
});
