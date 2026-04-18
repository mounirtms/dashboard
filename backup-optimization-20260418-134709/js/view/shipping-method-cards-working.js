/**
 * Shipping Method Cards - Fully Integrated with Mageplaza
 * Displays shipping options as modern cards
 * Works with Mageplaza Table Rate Shipping
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
            template: 'Mab_CheckoutCustomization/shipping-method-cards-working'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            
            self._super();
            
            console.log('🚀 [Shipping Cards] Component initializing...');
            
            // Observable properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(false);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');
            
            // Subscribe to shipping rates from Magento/Mageplaza
            shippingService.getShippingRates().subscribe(function (rates) {
                console.log('📦 [Shipping Cards] Rates received from service:', rates);
                console.log('📦 [Shipping Cards] Number of rates:', rates.length);
                
                if (rates && rates.length > 0) {
                    self.processShippingRates(rates);
                    self.isVisible(true);
                    self.isLoading(false);
                    self.errorMessage('');
                    
                    // Force visibility on wrapper
                    setTimeout(function() {
                        var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                        if (wrapper) {
                            wrapper.style.display = 'block';
                            wrapper.style.visibility = 'visible';
                            wrapper.style.opacity = '1';
                            console.log('✅ [Shipping Cards] Wrapper forced visible');
                        }
                    }, 100);
                } else {
                    console.warn('⚠️ [Shipping Cards] No rates available');
                    self.shippingMethods([]);
                    self.errorMessage($t('Aucune méthode de livraison disponible pour cette région'));
                }
            });
            
            // Subscribe to quote shipping address changes
            quote.shippingAddress.subscribe(function (address) {
                console.log('📍 [Shipping Cards] Address changed:', address);
                
                if (address) {
                    var regionId = address.regionId;
                    var region = address.region || '';
                    var regionCode = address.regionCode || '';
                    
                    console.log('📍 [Shipping Cards] Region ID:', regionId);
                    console.log('📍 [Shipping Cards] Region:', region);
                    console.log('📍 [Shipping Cards] Region Code:', regionCode);
                    
                    if (regionId || region) {
                        self.currentRegion(region || regionCode || 'Region ' + regionId);
                        self.isLoading(true);
                        console.log('⏳ [Shipping Cards] Loading rates for region:', self.currentRegion());
                    }
                }
            });
            
            // Subscribe to selected shipping method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    console.log('✅ [Shipping Cards] Method selected:', method);
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                }
            });
            
            // Check if there are initial rates
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                console.log('📦 [Shipping Cards] Processing initial rates:', initialRates.length);
                self.processShippingRates(initialRates);
                self.isVisible(true);
            }
            
            // Check initial address
            var initialAddress = quote.shippingAddress();
            if (initialAddress && (initialAddress.regionId || initialAddress.region)) {
                var region = initialAddress.region || initialAddress.regionCode || 'Region ' + initialAddress.regionId;
                self.currentRegion(region);
                console.log('📍 [Shipping Cards] Initial region:', region);
            }
            
            // Check initial selected method
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                var methodCode = currentMethod.carrier_code + '_' + currentMethod.method_code;
                self.selectedMethod(methodCode);
                console.log('✅ [Shipping Cards] Initial method:', methodCode);
            }
            
            console.log('✅ [Shipping Cards] Component initialized successfully');
            
            return self;
        },

        /**
         * Process shipping rates
         */
        processShippingRates: function (rates) {
            var self = this;
            var currentRegion = this.currentRegion();
            
            // Check cache first
            var cached = PerformanceOptimizer.getCachedRates(currentRegion);
            if (cached) {
                console.log('⚡ [Shipping Cards] Using cached rates for:', currentRegion);
                self.shippingMethods(cached);
                return;
            }
            
            // Measure performance
            var startTime = performance.now();
            var methods = [];
            
            console.log('🔄 [Shipping Cards] Processing', rates.length, 'rates...');
            
            $.each(rates, function (index, rate) {
                console.log('📋 [Shipping Cards] Processing rate #' + index + ':', {
                    carrier: rate.carrier_code,
                    method: rate.method_code,
                    title: rate.method_title || rate.carrier_title,
                    amount: rate.amount
                });
                
                var method = {
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
                };
                
                methods.push(method);
                console.log('✅ [Shipping Cards] Method created:', method.method_code);
            });
            
            self.shippingMethods(methods);
            
            // Cache the processed methods
            if (currentRegion) {
                PerformanceOptimizer.cacheRates(currentRegion, methods);
            }
            
            var duration = performance.now() - startTime;
            console.log('⏱️ [Shipping Cards] Processing took:', duration.toFixed(2) + 'ms');
            console.log('✅ [Shipping Cards] Total methods set:', methods.length);
            
            // Log method details
            methods.forEach(function(method, idx) {
                console.log('   ' + (idx + 1) + '. ' + method.method_title + ' - ' + method.price_formatted);
            });
        },

        /**
         * Get carrier logo
         */
        getCarrierLogo: function (rate) {
            var baseUrl = 'https://dev.technostationery.com/media/mageplaza/tablerate/';
            var methodCode = rate.method_code;
            var title = (rate.method_title || rate.carrier_title || '').toLowerCase();
            
            // Logo mapping by method code
            var logoMap = {
                '17': 'techno.png',      // Retrait Techno Batna
                '20': 'techno.png',      // Retrait Techno Setif
                '24': 'yalidine-logo.jpg', // Retrait en agence
                '2': 'yalidine-logo.jpg'   // Livraison à domicile
            };
            
            // Check if rate has image
            if (rate.image) {
                return rate.image;
            }
            
            // Check by method code
            if (logoMap[methodCode]) {
                return baseUrl + logoMap[methodCode];
            }
            
            // Check by title
            if (title.includes('techno') || title.includes('retrait techno')) {
                return baseUrl + 'techno.png';
            } else if (title.includes('yalidine') || title.includes('agence') || title.includes('domicile')) {
                return baseUrl + 'yalidine-logo.jpg';
            }
            
            // Default
            return baseUrl + 'default-carrier.png';
        },

        /**
         * Get delivery time
         */
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

        /**
         * Get method description
         */
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

        /**
         * Format price
         */
        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            
            if (price === 0) {
                return $t('Gratuit');
            }
            
            // Format as "XXX,XX DZD"
            return price.toFixed(2).replace('.', ',') + ' DZD';
        },

        /**
         * Get shipping methods
         */
        getShippingMethods: function () {
            return this.shippingMethods();
        },

        /**
         * Select method
         */
        selectMethod: function (method) {
            var self = this;
            
            console.log('👆 [Shipping Cards] User clicked method:', method.method_code);
            
            if (!method.available) {
                console.warn('⚠️ [Shipping Cards] Method not available');
                return;
            }
            
            // Set as selected
            self.selectedMethod(method.method_code);
            
            // Create Magento shipping method object
            var shippingMethod = {
                carrier_code: method.carrier_code,
                method_code: method.method_id,
                carrier_title: method.carrier_title,
                method_title: method.method_title,
                amount: method.amount,
                base_amount: method.amount,
                available: true,
                error_message: '',
                price_excl_tax: method.amount,
                price_incl_tax: method.amount
            };
            
            console.log('📝 [Shipping Cards] Calling selectShippingMethodAction with:', shippingMethod);
            
            // Select in Magento
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(method.carrier_code + '_' + method.method_id);
            
            console.log('✅ [Shipping Cards] Method selected successfully');
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

        /**
         * Get region name for display
         */
        getRegionName: function () {
            return this.currentRegion() || $t('votre région');
        },

        /**
         * Check if has methods
         */
        hasMethods: function () {
            return this.shippingMethods().length > 0;
        }
    });
});
