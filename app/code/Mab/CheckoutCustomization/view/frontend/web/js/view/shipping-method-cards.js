/**
 * Shipping Method Cards Component - Optimized
 * Displays Mageplaza shipping options as cards for Batna region
 * Features: Caching, performance tracking, optimized rendering
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'Mab_CheckoutCustomization/js/performance-optimizer'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData, perfOptimizer) {
    'use strict';

    // Initialize performance optimizer
    perfOptimizer.init();

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards',
            cacheKey: 'mab_shipping_methods_batna'
        },

        /**
         * Shipping methods data
         */
        shippingMethods: [
            {
                method_code: 'mptablerate_17',
                carrier_code: 'mptablerate',
                method_id: '17',
                method_title: 'Retrait Techno Batna',
                amount: 0,
                price_formatted: 'Gratuit',
                carrier_logo: 'https://dev.technostationery.com/media/mageplaza/tablerate/techno.png',
                delivery_time: 'Retrait immédiat',
                is_free: true,
                description: 'Retirez votre commande à notre magasin de Batna'
            },
            {
                method_code: 'mptablerate_24',
                carrier_code: 'mptablerate',
                method_id: '24',
                method_title: 'Retrait en agence',
                amount: 400,
                price_formatted: '400 DA',
                carrier_logo: 'https://dev.technostationery.com/media/mageplaza/tablerate/yalidine-logo.jpg',
                delivery_time: '2-3 jours',
                is_free: false,
                description: 'Retrait à l\'agence Yalidine la plus proche'
            },
            {
                method_code: 'mptablerate_2',
                carrier_code: 'mptablerate',
                method_id: '2',
                method_title: 'Livraison à domicile',
                amount: 500,
                price_formatted: '500 DA',
                carrier_logo: 'https://dev.technostationery.com/media/mageplaza/tablerate/yalidine-logo.jpg',
                delivery_time: '3-5 jours',
                is_free: false,
                description: 'Livraison directement à votre domicile'
            }
        ],

        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            
            // Use performance measurement
            return perfOptimizer.measure('shipping-cards-init', function () {
                self._super();
                self.selectedMethod = ko.observable(null);
                self.isVisible = ko.observable(false);
                self.currentRegion = ko.observable(null);
                
                // Try to get from cache first
                if (window.MabCache) {
                    var cached = window.MabCache.get(self.cacheKey);
                    if (cached) {
                        console.log('Using cached shipping methods');
                        self.shippingMethods = cached;
                    }
                }
                
                // Subscribe to quote shipping method changes
                quote.shippingMethod.subscribe(function (method) {
                    if (method) {
                        self.selectedMethod(method.carrier_code + '_' + method.method_code);
                    }
                }, self);

                // Set initial selection
                var currentMethod = quote.shippingMethod();
                if (currentMethod) {
                    self.selectedMethod(currentMethod.carrier_code + '_' + currentMethod.method_code);
                }

                // Subscribe to shipping address changes to detect region selection
                quote.shippingAddress.subscribe(function (address) {
                    if (address && address.regionId) {
                        console.log('Region changed:', address.regionId, address.region);
                        self.currentRegion(address.regionId);
                        self.isVisible(true);
                        
                        // Update wrapper visibility
                        setTimeout(function() {
                            var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                            if (wrapper) {
                                wrapper.setAttribute('data-region-selected', 'true');
                            }
                        }, 100);
                        
                        // Trigger shipping rates reload
                        self.reloadShippingMethods();
                    } else {
                        self.isVisible(false);
                        var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                        if (wrapper) {
                            wrapper.setAttribute('data-region-selected', 'false');
                        }
                    }
                }, self);

                // Preload images for faster display
                self.preloadImages();
                
                // Check initial address state
                var initialAddress = quote.shippingAddress();
                if (initialAddress && initialAddress.regionId) {
                    self.currentRegion(initialAddress.regionId);
                    self.isVisible(true);
                }
                
                return self;
            });
        },

        /**
         * Preload carrier logo images
         */
        preloadImages: function () {
            this.shippingMethods.forEach(function (method) {
                if (method.carrier_logo) {
                    var img = new Image();
                    img.src = method.carrier_logo;
                }
            });
        },

        /**
         * Get shipping methods
         * @returns {Array}
         */
        getShippingMethods: function () {
            return this.shippingMethods;
        },

        /**
         * Select shipping method (optimized with performance tracking)
         * @param {Object} method
         */
        selectMethod: function (method) {
            var self = this;
            
            perfOptimizer.measure('shipping-method-select', function () {
                console.log('Selecting shipping method:', method);
                
                self.selectedMethod(method.method_code);
                
                // Create method object for Magento
                var shippingMethod = {
                    carrier_code: method.carrier_code,
                    method_code: method.method_id,
                    carrier_title: method.method_title,
                    method_title: method.method_title,
                    amount: method.amount,
                    base_amount: method.amount,
                    available: true,
                    error_message: '',
                    price_excl_tax: method.amount,
                    price_incl_tax: method.amount
                };

                selectShippingMethodAction(shippingMethod);
                checkoutData.setSelectedShippingRate(method.method_code);
                
                // Cache the selection
                if (window.MabCache) {
                    window.MabCache.set('mab_selected_shipping', method.method_code, 3600000);
                }
            });
        },

        /**
         * Check if method is selected
         * @param {Object} method
         * @returns {Boolean}
         */
        isSelected: function (method) {
            return this.selectedMethod() === method.method_code;
        },

        /**
         * Get card CSS classes
         * @param {Object} method
         * @returns {String}
         */
        getCardClasses: function (method) {
            var classes = 'shipping-card';
            if (this.isSelected(method)) {
                classes += ' selected';
            }
            if (method.is_free) {
                classes += ' free-shipping';
            }
            return classes;
        },

        /**
         * Reload shipping methods after region change
         */
        reloadShippingMethods: function () {
            var self = this;
            console.log('Reloading shipping methods for region:', self.currentRegion());
            
            // Force re-render by triggering observable change
            var currentMethods = self.shippingMethods.slice();
            self.shippingMethods = [];
            
            setTimeout(function() {
                self.shippingMethods = currentMethods;
                
                // Ensure wrapper is visible
                var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                if (wrapper) {
                    wrapper.style.display = 'block';
                    wrapper.style.visibility = 'visible';
                    wrapper.style.opacity = '1';
                }
            }, 50);
        }
    });
});
