/**
 * Mab_CheckoutCustomization - Shipping Component Mixin
 * Integrates shipping method cards with default Magento checkout shipping
 * Responds to region/wilaya changes and updates shipping methods dynamically
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

            // Watch for rates loading completion
            this.isLoading.subscribe(function (isLoading) {
                if (!isLoading && self.rates() && self.rates().length > 0) {
                    // Short delay to ensure DOM is ready
                    setTimeout(function () {
                        self.initializeShippingCards();
                    }, 300);
                }
            });

            // Watch for address changes (region/wilaya selection)
            if (quote.shippingAddress) {
                quote.shippingAddress.subscribe(function (address) {
                    if (address && address.regionId) {
                        console.log('🗺️ Region changed to:', address.regionId, address.region);
                        // Reset initialization flag to allow re-render
                        window.shippingCardsInitialized = false;
                        // Wait for new rates, then re-render cards
                        setTimeout(function () {
                            if (self.rates() && self.rates().length > 0 && !self.isLoading()) {
                                console.log('♻️ Re-initializing shipping cards for new region');
                                self.initializeShippingCards();
                            }
                        }, 1000);
                    }
                });
            }

            // Watch rates observable directly for any changes
            if (this.rates) {
                this.rates.subscribe(function (newRates) {
                    if (newRates && newRates.length > 0) {
                        console.log('📦 New shipping rates available:', newRates.length);
                        // Reset and re-render
                        window.shippingCardsInitialized = false;
                        setTimeout(function () {
                            self.initializeShippingCards();
                        }, 200);
                    }
                });
            }

            return this;
        },

        /**
         * Initialize shipping method cards
         */
        initializeShippingCards: function () {
            // Prevent duplicate initialization
            if (window.shippingCardsInitialized) {
                console.log('⏭️ Shipping cards already initialized, skipping');
                return;
            }

            console.log('🎨 Initializing shipping cards...');
            
            require(['shippingMethodCards'], function (ShippingCards) {
                // Create instance and render cards
                var cardsComponent = new ShippingCards();
                if (typeof cardsComponent.replaceShippingStep === 'function') {
                    cardsComponent.replaceShippingStep();
                    window.shippingCardsInitialized = true;
                    console.log('✅ Shipping cards initialized successfully');
                } else {
                    console.error('❌ replaceShippingStep method not found');
                }
            });
        },

        /**
         * Override setShippingInformation to maintain cards after submission
         */
        setShippingInformation: function () {
            var result = this._super();
            var self = this;

            // Reinitialize cards after shipping info is set
            setTimeout(function () {
                if (self.rates() && self.rates().length > 0) {
                    console.log('🔄 Refreshing shipping cards after submission');
                    window.shippingCardsInitialized = false;
                    self.initializeShippingCards();
                }
            }, 800);

            return result;
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});