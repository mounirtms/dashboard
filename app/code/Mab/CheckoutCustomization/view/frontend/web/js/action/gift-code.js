/**
 * Gift Code Actions - Wrapper for Amasty Gift Card API
 * Provides check and apply functionality
 */
define([
    'jquery',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url'
], function ($, storage, urlBuilder, errorProcessor, fullScreenLoader, urlFormatter) {
    'use strict';

    return {
        /**
         * Check gift card balance
         * @param {String} giftCode
         * @returns {Deferred}
         */
        check: function (giftCode) {
            var serviceUrl = urlFormatter.build('amgcard/cart/check');
            
            fullScreenLoader.startLoader();
            
            return storage.post(
                serviceUrl,
                JSON.stringify({
                    giftcard_code: giftCode
                }),
                false
            ).fail(function (response) {
                errorProcessor.process(response);
            }).always(function () {
                fullScreenLoader.stopLoader();
            });
        },

        /**
         * Apply gift card to cart
         * @param {String} giftCode
         * @returns {Deferred}
         */
        apply: function (giftCode) {
            var serviceUrl = urlFormatter.build('mabgiftcard/giftcard/apply');
            
            fullScreenLoader.startLoader();
            
            return storage.post(
                serviceUrl,
                JSON.stringify({
                    giftcard_code: giftCode
                }),
                false
            ).fail(function (response) {
                errorProcessor.process(response);
            }).always(function () {
                fullScreenLoader.stopLoader();
            });
        },

        /**
         * Remove gift card from cart
         * @param {String} giftCode
         * @returns {Deferred}
         */
        remove: function (giftCode) {
            var serviceUrl = urlFormatter.build('amgcard/cart/remove');
            
            fullScreenLoader.startLoader();
            
            return storage.post(
                serviceUrl,
                JSON.stringify({
                    giftcard_code: giftCode
                }),
                false
            ).fail(function (response) {
                errorProcessor.process(response);
            }).always(function () {
                fullScreenLoader.stopLoader();
            });
        }
    };
});
