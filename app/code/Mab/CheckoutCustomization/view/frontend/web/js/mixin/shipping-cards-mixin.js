/**
 * Mab_CheckoutCustomization - Shipping Component Mixin
 * Integrates shipping method cards with default Magento checkout shipping
 * Responds to region/wilaya changes and updates shipping methods dynamically
 * OPTIMIZED: Debouncing, caching, requestAnimationFrame, better wilaya handling
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
        _initialLoadDone: false,
        
        /**
         * Initialize mixin
         */
        initialize: function () {
            this._super();
            var self = this;

            console.log('📋 Shipping cards mixin initializing...');

            // Watch for rates loading completion (debounced)
            var debouncedInit = debounce(function() {
                if (!self._initialLoadDone || !window.shippingCardsInitialized) {
                    self.initializeShippingCards();
                    self._initialLoadDone = true;
                }
            }, 300);
            
            this.isLoading.subscribe(function (isLoading) {
                console.log('📊 Loading state changed:', isLoading);
                if (!isLoading && self.rates() && self.rates().length > 0) {
                    window.requestAnimationFrame(debouncedInit);
                }
            });

            // Watch for address changes (region/wilaya selection) - OPTIMIZED
            if (quote.shippingAddress) {
                var handleRegionChange = debounce(function (address) {
                    if (address && address.regionId) {
                        console.log('🗺️ Region changed to:', address.regionId, address.region);
                        // Clear caches to force re-render
                        window.shippingCardsInitialized = false;
                        window.shippingCardsLastRegion = address.regionId;
                        
                        // Wait for new rates, then re-render
                        clearTimeout(self._regionTimer);
                        self._regionTimer = setTimeout(function () {
                            if (self.rates() && self.rates().length > 0 && !self.isLoading()) {
                                console.log('♻️ Re-initializing shipping cards for new region');
                                window.requestAnimationFrame(function() {
                                    self.initializeShippingCards();
                                });
                            }
                        }, 800);
                    }
                }, 300);
                
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
                }, 200);
                
                this.rates.subscribe(handleRatesChange);
            }

            // Listen for wilaya:changed event from wilaya-commune-filter
            $(document).on('wilaya:changed', function (e, wilayaId) {
                console.log('📍 Wilaya changed to:', wilayaId);
                window.shippingCardsInitialized = false;
                window.shippingCardsLastRegion = null;
                
                // Wait for rates to update
                clearTimeout(self._regionTimer);
                self._regionTimer = setTimeout(function () {
                    window.requestAnimationFrame(function() {
                        self.initializeShippingCards();
                    });
                }, 1000);
            });

            // Fallback: Initial load after a delay
            setTimeout(function () {
                if (!self._initialLoadDone || !window.shippingCardsInitialized) {
                    console.log('🔄 Fallback: Initializing shipping cards...');
                    self.initializeShippingCards();
                    self._initialLoadDone = true;
                }
            }, 1500);

            return this;
        },

        /**
         * Initialize shipping method cards
         */
        initializeShippingCards: function () {
            console.log('🎨 Attempting to initialize shipping cards...');
            
            // Always force re-render when called from mixin (wilaya change)
            // This ensures fresh data is read from the updated table
            $('.shipping-methods-cards-wrapper').remove();
            
            console.log('🎨 Initializing shipping cards...');
            
            require(['shippingMethodCards'], function (ShippingCards) {
                // Create instance and render cards
                var cardsComponent = new ShippingCards();
                if (typeof cardsComponent.replaceShippingStep === 'function') {
                    cardsComponent.replaceShippingStep();
                    window.shippingCardsInitialized = true;
                    var currentRegion = quote.shippingAddress() ? quote.shippingAddress().regionId : null;
                    window.shippingCardsLastRegion = currentRegion;
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