/**
 * Shipping Method Cards - Production Version
 * Optimized for performance with minimal logging
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

        initialize: function () {
            var self = this;
            self._super();
            
            // Observable properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(false);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');
            
            // Subscribe to shipping rates
            shippingService.getShippingRates().subscribe(function (rates) {
                if (rates && rates.length > 0) {
                    self.processShippingRates(rates);
                    self.isVisible(true);
                    self.isLoading(false);
                    self.errorMessage('');
                    
                    setTimeout(function() {
                        var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                        if (wrapper) {
                            wrapper.style.display = 'block';
                            wrapper.style.visibility = 'visible';
                            wrapper.style.opacity = '1';
                        }
                    }, 100);
                } else {
                    self.shippingMethods([]);
                    self.errorMessage($t('Aucune méthode de livraison disponible pour cette région'));
                }
            });
            
            // Subscribe to address changes
            quote.shippingAddress.subscribe(function (address) {
                if (address && (address.regionId || address.region)) {
                    self.currentRegion(address.region || address.regionCode || 'Region ' + address.regionId);
                    self.isLoading(true);
                }
            });
            
            // Subscribe to method selection
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    self.selectedMethod(method.carrier_code + '_' + method.method_code);
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
                self.currentRegion(initialAddress.region || initialAddress.regionCode || 'Region ' + initialAddress.regionId);
            }
            
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                self.selectedMethod(currentMethod.carrier_code + '_' + currentMethod.method_code);
            }
            
            return self;
        },

        processShippingRates: function (rates) {
            var self = this;
            var currentRegion = this.currentRegion();
            
            // Check cache
            var cached = PerformanceOptimizer.getCachedRates(currentRegion);
            if (cached) {
                self.shippingMethods(cached);
                return;
            }
            
            var methods = [];
            
            $.each(rates, function (index, rate) {
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
            
            self.shippingMethods(methods);
            
            // Cache results
            if (currentRegion) {
                PerformanceOptimizer.cacheRates(currentRegion, methods);
            }
        },

        getCarrierLogo: function (rate) {
            var baseUrl = 'https://dev.technostationery.com/media/mageplaza/tablerate/';
            var methodCode = rate.method_code;
            var logoMap = {
                '17': 'techno.png',
                '20': 'techno.png',
                '24': 'yalidine-logo.jpg',
                '2': 'yalidine-logo.jpg'
            };
            
            if (rate.image) return rate.image;
            if (logoMap[methodCode]) return baseUrl + logoMap[methodCode];
            
            var title = (rate.method_title || rate.carrier_title || '').toLowerCase();
            if (title.includes('techno')) return baseUrl + 'techno.png';
            if (title.includes('yalidine') || title.includes('agence') || title.includes('domicile')) {
                return baseUrl + 'yalidine-logo.jpg';
            }
            
            return baseUrl + 'default-carrier.png';
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
                return region ? 
                    $t('Retirez votre commande à notre magasin de %1').replace('%1', region) :
                    $t('Retirez votre commande à notre magasin');
            } else if (methodCode === '24' || title.includes('retrait en agence')) {
                return $t('Retrait à l\'agence Yalidine la plus proche');
            } else if (methodCode === '2' || title.includes('livraison')) {
                return $t('Livraison directement à votre domicile');
            }
            
            return rate.carrier_title || '';
        },

        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            return price === 0 ? $t('Gratuit') : price.toFixed(2).replace('.', ',') + ' DZD';
        },

        getShippingMethods: function () {
            return this.shippingMethods();
        },

        selectMethod: function (method) {
            var self = this;
            
            if (!method.available) return;
            
            self.selectedMethod(method.method_code);
            
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
            
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(method.carrier_code + '_' + method.method_id);
        },

        isSelected: function (method) {
            return this.selectedMethod() === method.method_code;
        },

        getCardClasses: function (method) {
            var classes = ['shipping-card'];
            if (this.isSelected(method)) classes.push('selected');
            if (method.is_free) classes.push('free-shipping');
            if (!method.available) classes.push('unavailable');
            return classes.join(' ');
        },

        getRegionName: function () {
            return this.currentRegion() || $t('votre région');
        },

        hasMethods: function () {
            return this.shippingMethods().length > 0;
        }
    });
});
