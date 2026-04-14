/**
 * Mab_CheckoutCustomization - Shipping Component Mixin
 * Integrates shipping method cards with default Magento checkout shipping
 * Now with region-aware shipping method filtering for Mageplaza TableRateShipping
 */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote'
], function ($, ko, quote) {
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

            // Watch for address changes (especially region/wilaya changes)
            if (quote.shippingAddress) {
                quote.shippingAddress.subscribe(function (address) {
                    if (address && address.regionId) {
                        // Debug removed for production
                        // Reset cards initialization flag to allow re-rendering
                        window.shippingCardsInitialized = false;
                        // Wait for new rates to be fetched, then reinitialize cards
                        setTimeout(function () {
                            if (self.rates() && self.rates().length > 0 && !self.isLoading()) {
                                self.initializeShippingCards();
                            }
                        }, 1500);
                    }
                });
            }

            // Also watch the rates observable directly for changes
            if (this.rates) {
                this.rates.subscribe(function (newRates) {
                    if (newRates && newRates.length > 0) {
                        // Debug removed for production
                        // Reset and reinitialize cards
                        window.shippingCardsInitialized = false;
                        setTimeout(function () {
                            self.initializeShippingCards();
                        }, 300);
                    }
                });
            }

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
                    window.shippingCardsInitialized = false;
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