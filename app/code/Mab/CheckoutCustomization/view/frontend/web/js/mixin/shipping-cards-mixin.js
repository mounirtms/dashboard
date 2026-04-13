/**
 * Mab_CheckoutCustomization - Shipping Component Mixin
 * Integrates shipping method cards with default Magento checkout shipping
 */
define([
    'jquery',
    'ko'
], function ($, ko) {
    'use strict';

    var mixin = {
        /**
         * Initialize mixin
         */
        initialize: function () {
            this._super();
            var self = this;

            // Convert to cards after rates are loaded
            this.isLoading.subscribe(function (isLoading) {
                if (!isLoading && self.rates() && self.rates().length > 0) {
                    setTimeout(function () {
                        self.initializeShippingCards();
                    }, 500);
                }
            });

            return this;
        },

        /**
         * Initialize shipping method cards
         */
        initializeShippingCards: function () {
            if (window.shippingCardsInitialized) {
                return;
            }

            require(['shippingMethodCards'], function (ShippingCards) {
                // Create instance and convert to cards
                var cardsComponent = new ShippingCards();
                cardsComponent.convertToCards();
                window.shippingCardsInitialized = true;
            });
        },

        /**
         * Override setShippingInformation to maintain cards
         */
        setShippingInformation: function () {
            var result = this._super();
            var self = this;

            // Reinitialize cards after shipping info is set
            setTimeout(function () {
                if (self.rates() && self.rates().length > 0) {
                    self.initializeShippingCards();
                }
            }, 1000);

            return result;
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});