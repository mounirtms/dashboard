/**
 * Enhanced Shipping Method Cards with Advanced Features
 * - Auto-selection logic
 * - Error recovery
 * - Performance optimizations
 * - Analytics tracking
 * - Progressive enhancement
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'Mab_CheckoutCustomization/js/performance-optimizer',
    'mage/storage',
    'mage/translate'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData, perfOptimizer, storage, $t) {
    'use strict';

    // Initialize performance optimizer
    perfOptimizer.init();

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards',
            cacheKey: 'mab_shipping_methods_batna',
            autoSelectPreferred: true,
            preferredMethodPriority: ['mptablerate_17', 'mptablerate_24', 'mptablerate_2'], // Free first
            enableAnalytics: true,
            retryAttempts: 3,
            retryDelay: 1000
        },

        /**
         * Shipping methods data with enhanced metadata
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
                description: 'Retirez votre commande à notre magasin de Batna',
                priority: 1,
                recommended: true,
                tags: ['fast', 'free', 'store-pickup']
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
                description: 'Retrait à l\'agence Yalidine la plus proche',
                priority: 2,
                recommended: false,
                tags: ['agency-pickup', 'yalidine']
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
                description: 'Livraison directement à votre domicile',
                priority: 3,
                recommended: false,
                tags: ['home-delivery', 'yalidine']
            }
        ],

        /**
         * Initialize component with enhanced features
         */
        initialize: function () {
            var self = this;
            
            return perfOptimizer.measure('shipping-cards-init-enhanced', function () {
                self._super();
                
                // Observables
                self.selectedMethod = ko.observable(null);
                self.isVisible = ko.observable(false);
                self.currentRegion = ko.observable(null);
                self.isLoading = ko.observable(false);
                self.errorMessage = ko.observable(null);
                self.retryCount = ko.observable(0);
                
                // Try to restore from cache
                self.restoreFromCache();
                
                // Subscribe to quote changes
                self.setupSubscriptions();
                
                // Preload images for faster display
                self.preloadImages();
                
                // Check initial address state
                var initialAddress = quote.shippingAddress();
                if (initialAddress && initialAddress.regionId) {
                    self.currentRegion(initialAddress.regionId);
                    self.isVisible(true);
                    
                    // Auto-select preferred method if enabled
                    if (self.autoSelectPreferred && !self.selectedMethod()) {
                        self.autoSelectMethod();
                    }
                }
                
                // Track initialization
                self.trackEvent('shipping_cards_initialized', {
                    region: self.currentRegion(),
                    methods_count: self.shippingMethods.length
                });
                
                return self;
            });
        },

        /**
         * Setup subscriptions to quote observables
         */
        setupSubscriptions: function () {
            var self = this;
            
            // Subscribe to shipping method changes
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    self.selectedMethod(method.carrier_code + '_' + method.method_code);
                    self.saveToCache();
                    self.trackEvent('shipping_method_changed', {
                        method_code: method.method_code,
                        carrier_code: method.carrier_code,
                        amount: method.amount
                    });
                }
            }, self);

            // Subscribe to shipping address changes
            quote.shippingAddress.subscribe(function (address) {
                self.handleAddressChange(address);
            }, self);
            
            // Set initial selection
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                self.selectedMethod(currentMethod.carrier_code + '_' + currentMethod.method_code);
            }
        },

        /**
         * Handle address change with error recovery
         */
        handleAddressChange: function (address) {
            var self = this;
            
            if (address && address.regionId) {
                console.log('Region changed:', address.regionId, address.region);
                self.currentRegion(address.regionId);
                self.isVisible(true);
                self.errorMessage(null);
                
                // Update wrapper visibility with error handling
                try {
                    self.updateWrapperVisibility(true);
                    self.reloadShippingMethods();
                    
                    // Auto-select if no method selected
                    if (self.autoSelectPreferred && !self.selectedMethod()) {
                        setTimeout(function() {
                            self.autoSelectMethod();
                        }, 300);
                    }
                } catch (error) {
                    console.error('Error handling address change:', error);
                    self.handleError(error);
                }
            } else {
                self.isVisible(false);
                self.updateWrapperVisibility(false);
            }
        },

        /**
         * Update wrapper visibility safely
         */
        updateWrapperVisibility: function (visible) {
            var self = this;
            
            // Use requestIdleCallback for non-critical DOM updates
            if (window.requestIdleCallback) {
                requestIdleCallback(function() {
                    self._updateWrapperDOM(visible);
                }, { timeout: 100 });
            } else {
                // Fallback for browsers without requestIdleCallback
                setTimeout(function() {
                    self._updateWrapperDOM(visible);
                }, 50);
            }
        },

        /**
         * Internal DOM update method
         */
        _updateWrapperDOM: function (visible) {
            var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            if (wrapper) {
                wrapper.setAttribute('data-region-selected', visible ? 'true' : 'false');
                if (visible) {
                    wrapper.style.display = 'block';
                    wrapper.style.visibility = 'visible';
                    wrapper.style.opacity = '1';
                }
            }
        },

        /**
         * Preload carrier logo images with error handling
         */
        preloadImages: function () {
            var self = this;
            var loaded = 0;
            var total = self.shippingMethods.length;
            
            self.shippingMethods.forEach(function (method) {
                if (method.carrier_logo) {
                    var img = new Image();
                    
                    img.onload = function() {
                        loaded++;
                        if (loaded === total) {
                            console.log('All carrier logos preloaded');
                            self.trackEvent('carrier_logos_loaded', { count: total });
                        }
                    };
                    
                    img.onerror = function() {
                        console.warn('Failed to preload logo:', method.carrier_logo);
                        loaded++;
                    };
                    
                    img.src = method.carrier_logo;
                }
            });
        },

        /**
         * Get shipping methods
         */
        getShippingMethods: function () {
            return this.shippingMethods;
        },

        /**
         * Auto-select preferred method based on priority
         */
        autoSelectMethod: function () {
            var self = this;
            
            console.log('Auto-selecting preferred shipping method');
            
            // Find preferred method by priority
            var preferredMethod = null;
            
            for (var i = 0; i < self.preferredMethodPriority.length; i++) {
                var methodCode = self.preferredMethodPriority[i];
                preferredMethod = self.shippingMethods.find(function(m) {
                    return m.method_code === methodCode;
                });
                
                if (preferredMethod) {
                    break;
                }
            }
            
            // Fallback to first method or recommended
            if (!preferredMethod) {
                preferredMethod = self.shippingMethods.find(function(m) {
                    return m.recommended;
                }) || self.shippingMethods[0];
            }
            
            if (preferredMethod) {
                console.log('Auto-selected method:', preferredMethod.method_code);
                self.selectMethod(preferredMethod);
                self.trackEvent('shipping_method_auto_selected', {
                    method_code: preferredMethod.method_code,
                    is_free: preferredMethod.is_free
                });
            }
        },

        /**
         * Select shipping method with enhanced error handling
         */
        selectMethod: function (method) {
            var self = this;
            
            return perfOptimizer.measure('shipping-method-select-enhanced', function () {
                console.log('Selecting shipping method:', method);
                
                try {
                    self.selectedMethod(method.method_code);
                    self.errorMessage(null);
                    
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
                    
                    // Save to cache
                    self.saveToCache();
                    
                    // Track selection
                    self.trackEvent('shipping_method_selected', {
                        method_code: method.method_code,
                        amount: method.amount,
                        is_free: method.is_free,
                        manual: true
                    });
                    
                } catch (error) {
                    console.error('Error selecting shipping method:', error);
                    self.handleError(error);
                }
            });
        },

        /**
         * Check if method is selected
         */
        isSelected: function (method) {
            return this.selectedMethod() === method.method_code;
        },

        /**
         * Get card CSS classes
         */
        getCardClasses: function (method) {
            var classes = 'shipping-card';
            if (this.isSelected(method)) {
                classes += ' selected';
            }
            if (method.is_free) {
                classes += ' free-shipping';
            }
            if (method.recommended) {
                classes += ' recommended';
            }
            return classes;
        },

        /**
         * Reload shipping methods with retry logic
         */
        reloadShippingMethods: function () {
            var self = this;
            
            console.log('Reloading shipping methods for region:', self.currentRegion());
            self.isLoading(true);
            
            try {
                // Force re-render
                var currentMethods = self.shippingMethods.slice();
                self.shippingMethods = [];
                
                setTimeout(function() {
                    self.shippingMethods = currentMethods;
                    self.isLoading(false);
                    self._updateWrapperDOM(true);
                    self.retryCount(0);
                }, 50);
                
            } catch (error) {
                console.error('Error reloading shipping methods:', error);
                self.handleError(error);
            }
        },

        /**
         * Handle errors with retry logic
         */
        handleError: function (error) {
            var self = this;
            
            self.isLoading(false);
            
            if (self.retryCount() < self.retryAttempts) {
                self.retryCount(self.retryCount() + 1);
                console.log('Retrying... Attempt', self.retryCount());
                
                setTimeout(function() {
                    self.reloadShippingMethods();
                }, self.retryDelay * self.retryCount());
                
                self.errorMessage($t('Chargement des méthodes de livraison... Tentative %1/%2')
                    .replace('%1', self.retryCount())
                    .replace('%2', self.retryAttempts));
            } else {
                self.errorMessage($t('Erreur lors du chargement des méthodes de livraison. Veuillez rafraîchir la page.'));
                self.trackEvent('shipping_methods_error', {
                    error: error.message || error.toString(),
                    retry_count: self.retryCount()
                });
            }
        },

        /**
         * Save state to cache
         */
        saveToCache: function () {
            if (window.MabCache) {
                window.MabCache.set(this.cacheKey, this.shippingMethods, 3600000);
                window.MabCache.set('mab_selected_shipping', this.selectedMethod(), 3600000);
                window.MabCache.set('mab_current_region', this.currentRegion(), 3600000);
            }
        },

        /**
         * Restore state from cache
         */
        restoreFromCache: function () {
            if (window.MabCache) {
                var cached = window.MabCache.get(this.cacheKey);
                if (cached) {
                    console.log('Restored shipping methods from cache');
                    this.shippingMethods = cached;
                }
                
                var selectedMethod = window.MabCache.get('mab_selected_shipping');
                if (selectedMethod) {
                    this.selectedMethod(selectedMethod);
                }
                
                var region = window.MabCache.get('mab_current_region');
                if (region) {
                    this.currentRegion(region);
                }
            }
        },

        /**
         * Track analytics events
         */
        trackEvent: function (eventName, data) {
            if (!this.enableAnalytics) {
                return;
            }
            
            try {
                // Google Analytics
                if (window.gtag) {
                    window.gtag('event', eventName, data);
                }
                
                // Facebook Pixel
                if (window.fbq) {
                    window.fbq('trackCustom', eventName, data);
                }
                
                // Custom analytics
                if (window.MabAnalytics) {
                    window.MabAnalytics.track(eventName, data);
                }
                
                console.log('Analytics event:', eventName, data);
            } catch (error) {
                console.warn('Analytics tracking error:', error);
            }
        }
    });
});
