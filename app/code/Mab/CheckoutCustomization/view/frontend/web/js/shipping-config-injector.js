/**
 * Shipping Config Injector
 * Merges shipping method config into window.checkoutConfig
 */
define([], function () {
    'use strict';

    return function (config) {
        if (!window.checkoutConfig) {
            window.checkoutConfig = {};
        }

        // Merge shipping method config
        if (config.shippingMethodCards) {
            window.checkoutConfig.shippingMethodCards = config.shippingMethodCards;
        }
    };
});
