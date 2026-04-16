/**
 * Mab_CheckoutCustomization - Shipping Component Mixin
 * Integrates shipping method cards with default Magento checkout shipping
 * Responds to region/wilaya changes and updates shipping methods dynamically
 * OPTIMIZED: Debouncing, caching, requestAnimationFrame
 */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote'
], function ($, ko, quote) {
    'use strict';

    // Debounce utility function
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    var mixin = {
        // Cache initialization state
        _initTimer: null,
        _regionTimer: null,
        
        /**
         * Initialize mixin
         */
        initialize: function () {
            this._super();
            var self = this;

            // Watch for rates loading completion (debounced)
            var debouncedInit = debounce(function() {
                self.initializeShippingCards();
            }, 250);
            
            this.isLoading.subscribe(function (isLoading) {
                if (!isLoading && self.rates() && self.rates().length > 0) {
                    // Use requestAnimationFrame for better performance
                    window.requestAnimationFrame(debouncedInit);
                }
            });

            // Watch for address changes (region/wilaya selection) - OPTIMIZED
            if (quote.shippingAddress) {
                var handleRegionChange = debounce(function (address) {
                    if (address && address.regionId) {
                        console.log('🗺️ Region changed to:', address.regionId, address.region);
                        // Reset initialization flag
                        window.shippingCardsInitialized = false;
                        
                        // Wait for new rates, then re-render
                        clearTimeout(self._regionTimer);
                        self._regionTimer = setTimeout(function () {
                            if (self.rates() && self.rates().length > 0 && !self.isLoading()) {
                                console.log('♻️ Re-initializing shipping cards for new region');
                                window.requestAnimationFrame(function() {
                                    self.initializeShippingCards();
                                });
                            }
                        }, 800); // Reduced from 1000ms
                    }
                }, 300); // Debounce region changes
                
                quote.shippingAddress.subscribe(handleRegionChange);
            }

            // Watch rates observable directly for any changes - OPTIMIZED
            if (this.rates) {
                var handleRatesChange = debounce(function (newRates) {
                    if (newRates && newRates.length > 0) {
                        console.log('📦 New shipping rates available:', newRates.length);
                        // Reset and re-render
                        window.shippingCardsInitialized = false;
                        window.requestAnimationFrame(function() {
                            self.initializeShippingCards();
                        });
                    }
                }, 150); // Debounce rate changes
                
                this.rates.subscribe(handleRatesChange);
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