/**
 * Shipping Method Cards - OPTIMIZED VERSION
 * Performance improvements:
 * - Conditional logging (debug mode only)
 * - Debounced address changes
 * - Reduced DOM queries
 * - Better error handling
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'mage/translate',
    'Mab_CheckoutCustomization/js/performance-optimizer-advanced'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData, $t, PerformanceOptimizer) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards',
            debugMode: window.location.href.indexOf('debug=checkout') !== -1
        },

        /**
         * Conditional logging - production safe
         */
        log: function() {
            if (this.debugMode && typeof console !== 'undefined') {
                console.log.apply(console, ['[Shipping]'].concat(Array.prototype.slice.call(arguments)));
            }
        },

        warn: function() {
            if (this.debugMode && typeof console !== 'undefined') {
                console.warn.apply(console, ['[Shipping]'].concat(Array.prototype.slice.call(arguments)));
            }
        },

        error: function() {
            if (typeof console !== 'undefined') {
                console.error.apply(console, ['[Shipping]'].concat(Array.prototype.slice.call(arguments)));
            }
        },

        /**
         * Debounce utility
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

        initialize: function () {
            var self = this;
            
            self._super();
            
            self.log('Component initializing...');
            
            // Observable properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(true);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');
            
            // Subscribe to shipping rates
            shippingService.getShippingRates().subscribe(function (rates) {
                self.log('Rates received:', rates ? rates.length : 0);
                
                if (rates && rates.length > 0) {
                    var hasValidRates = rates.some(function(rate) {
                        return rate.method_code && rate.method_code !== null && rate.method_code !== 'null' && rate.available !== false;
                    });
                    
                    if (hasValidRates) {
                        self.processShippingRates(rates);
                        self.isLoading(false);
                        
                        // Force visibility efficiently
                        requestAnimationFrame(function() {
                            var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                            if (wrapper) {
                                wrapper.style.display = 'block';
                                wrapper.style.visibility = 'visible';
                                wrapper.style.opacity = '1';
                            }
                        });
                    } else {
                        self.error('No valid rates - all have null method_code or available:false');
                        self.shippingMethods([]);
                        self.isVisible(false);
                        self.isLoading(false);
                        self.errorMessage('Configuration de livraison requise. Veuillez vérifier les tarifs dans l\'administration.');
                    }
                } else {
                    self.warn('No rates available');
                    self.shippingMethods([]);
                    self.isVisible(false);
                    self.isLoading(false);
                    self.errorMessage($t('Aucune méthode de livraison disponible pour cette région'));
                }
            });
            
            // Subscribe to address changes WITH DEBOUNCE
            quote.shippingAddress.subscribe(
                self.debounce(function (address) {
                    if (address) {
                        var regionId = address.regionId;
                        var region = address.region || '';
                        var regionCode = address.regionCode || '';
                        
                        if (regionId || region) {
                            var previousRegion = self.currentRegion();
                            var newRegion = region || regionCode || 'Region ' + regionId;
                            
                            if (previousRegion !== newRegion) {
                                self.log('Region changed:', newRegion);
                                self.selectedMethod(null);
                                self.shippingMethods([]);
                            }
                            
                            self.currentRegion(newRegion);
                            self.isLoading(true);
                        }
                    }
                }, 300) // 300ms debounce
            );
            
            // Subscribe to selected shipping method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                }
            });
            
            // Check initial state
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                self.processShippingRates(initialRates);
                self.isVisible(true);
            }
            
            var initialAddress = quote.shippingAddress();
            if (initialAddress && (initialAddress.regionId || initialAddress.region)) {
                var region = initialAddress.region || initialAddress.regionCode || 'Region ' + initialAddress.regionId;
                self.currentRegion(region);
            }
            
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                var methodCode = currentMethod.carrier_code + '_' + currentMethod.method_code;
                self.selectedMethod(methodCode);
            }
            
            self.log('Component initialized');
            
            return self;
        },

        processShippingRates: function (rates) {
            var self = this;
            var currentRegion = this.currentRegion();
            
            // Check cache first
            var cached = PerformanceOptimizer.getCachedRates(currentRegion);
            if (cached) {
                self.log('Using cached rates');
                self.shippingMethods(cached);
                return;
            }
            
            var startTime = performance.now();
            var methods = [];
            
            $.each(rates, function (index, rate) {
                // Skip invalid rates
                if (!rate.method_code || rate.method_code === null || rate.method_code === 'null') {
                    self.warn('Skipping invalid rate - method_code is null');
                    return;
                }
                
                if (rate.available === false) {
                    return;
                }
                
                methods.push({
                    method_code: rate.carrier_code + '_' + rate.method_code,
                    carrier_code: rate.carrier_code,
                    method_id: rate.method_code,
                    method_title: rate.method_title || rate.carrier_title,
                    carrier_title: rate.carrier_title,
                    amount: parseFloat(rate.amount) || 0,
                    price_formatted: self.formatPrice(rate.amount),
                    carrier_logo: self.getCarrierLogo(rate),
                    delivery_time: self.getDeliveryTime(rate),
                    description: self.getMethodDescription(rate),
                    is_free: parseFloat(rate.amount) === 0,
                    available: rate.available !== false,
                    error_message: rate.error_message || ''
                });
            });
            
            if (methods.length === 0) {
                self.error('No valid shipping methods found!');
                self.isVisible(false);
                self.errorMessage('Aucune méthode de livraison disponible pour cette région. Veuillez contacter le support.');
                return;
            }
            
            self.shippingMethods(methods);
            self.isVisible(true);
            self.errorMessage('');
            
            // Cache the processed methods
            if (currentRegion) {
                PerformanceOptimizer.cacheRates(currentRegion, methods);
            }
            
            var duration = performance.now() - startTime;
            self.log('Processed', methods.length, 'methods in', duration.toFixed(2) + 'ms');
        },

        getCarrierLogo: function (rate) {
            var baseUrl = 'https://dev.technostationery.com/media/mageplaza/tablerate/';
            var methodCode = rate.method_code;
            var title = (rate.method_title || rate.carrier_title || '').toLowerCase();
            
            var logoMap = {
                '17': 'techno.png',
                '20': 'techno.png',
                '24': 'yalidine-logo.jpg',
                '2': 'yalidine-logo.jpg'
            };
            
            if (rate.image) return rate.image;
            if (logoMap[methodCode]) return baseUrl + logoMap[methodCode];
            
            if (title.includes('techno') || title.includes('retrait techno')) {
                return baseUrl + 'techno.png';
            } else if (title.includes('yalidine') || title.includes('agence') || title.includes('domicile')) {
                return baseUrl + 'yalidine-logo.jpg';
            }
            
            // SVG placeholder (avoids 404)
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIGZpbGw9IiNlMGUwZTAiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1zaXplPSIxMCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+Q2FycmllcjwvdGV4dD48L3N2Zz4=';
        },

        getDeliveryTime: function (rate) {
            var methodCode = rate.method_code;
            var title = (rate.method_title || '').toLowerCase();
            
            if (methodCode === '17' || methodCode === '20' || title.includes('retrait techno')) {
                return $t('Retrait immédiat');
            } else if (methodCode === '24' || title.includes('retrait en agence')) {
                return $t('2-3 jours');
            } else if (methodCode === '2' || title.includes('livraison')) {
                return $t('3-5 jours');
            }
            
            return $t('Délai standard');
        },

        getMethodDescription: function (rate) {
            var methodCode = rate.method_code;
            var title = (rate.method_title || '').toLowerCase();
            var region = this.currentRegion();
            
            if (methodCode === '17' || methodCode === '20' || title.includes('retrait techno')) {
                if (region) {
                    return $t('Retirez votre commande à notre magasin de %1').replace('%1', region);
                }
                return $t('Retirez votre commande à notre magasin');
            } else if (methodCode === '24' || title.includes('retrait en agence')) {
                return $t('Retrait à l\'agence Yalidine la plus proche');
            } else if (methodCode === '2' || title.includes('livraison')) {
                return $t('Livraison directement à votre domicile');
            }
            
            return rate.carrier_title || '';
        },

        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            
            if (price === 0) {
                return $t('Gratuit');
            }
            
            return price.toFixed(2).replace('.', ',') + ' DZD';
        },

        getShippingMethods: function () {
            return this.shippingMethods();
        },

        selectMethod: function (method) {
            var self = this;
            
            self.log('User clicked method:', method.method_code);
            
            if (!method.available) {
                self.warn('Method not available');
                return;
            }
            
            self.selectedMethod(method.method_code);
            
            var actualMethodCode = method.method_code.split('_')[1] || method.method_code;
            
            var shippingMethod = {
                carrier_code: method.carrier_code,
                method_code: actualMethodCode,
                carrier_title: method.carrier_title,
                method_title: method.method_title,
                amount: method.amount,
                base_amount: method.amount,
                available: true,
                error_message: '',
                price_excl_tax: method.amount,
                price_incl_tax: method.amount
            };
            
            try {
                selectShippingMethodAction(shippingMethod);
                checkoutData.setSelectedShippingRate(method.carrier_code + '_' + actualMethodCode);
                self.log('Method selected successfully');
            } catch (error) {
                self.error('Error selecting method:', error.message);
            }
        },

        isSelected: function (method) {
            return this.selectedMethod() === method.method_code;
        },

        getCardClasses: function (method) {
            var classes = ['shipping-card'];
            
            if (this.isSelected(method)) {
                classes.push('selected');
            }
            
            if (method.is_free) {
                classes.push('free-shipping');
            }
            
            if (!method.available) {
                classes.push('unavailable');
            }
            
            return classes.join(' ');
        },

        getRegionName: function () {
            return this.currentRegion() || $t('votre région');
        },
        
        validateShippingSelection: function() {
            var hasMethod = this.selectedMethod() !== null;
            var quoteMethod = quote.shippingMethod();
            
            return hasMethod && quoteMethod !== null;
        },

        hasMethods: function () {
            return this.shippingMethods().length > 0;
        }
    });
});
