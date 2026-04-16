/**
 * Dynamic Shipping Method Cards Component
 * Works with ANY region by reading actual Magento shipping rates
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            self._super();
            
            console.log('[Shipping Cards] Initializing...');
            
            // Observables
            self.selectedMethod = ko.observable(null);
            self.shippingMethods = ko.observableArray([]);
            self.isVisible = ko.observable(true);
            self.isLoading = ko.observable(false);
            
            // Subscribe to shipping service rates
            shippingService.getShippingRates().subscribe(function(rates) {
                console.log('[Shipping Cards] Rates changed:', rates);
                if (rates && rates.length > 0) {
                    self.updateShippingMethods(rates);
                    self.isVisible(true);
                } else {
                    self.shippingMethods([]);
                    self.isVisible(false);
                }
            });
            
            // Subscribe to selected method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    console.log('[Shipping Cards] Method selected:', method);
                    self.selectedMethod(method.carrier_code + '_' + method.method_code);
                }
            });

            // Set initial selection
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                self.selectedMethod(currentMethod.carrier_code + '_' + currentMethod.method_code);
            }

            // Check initial rates
            var initialRates = shippingService.getShippingRates()();
            console.log('[Shipping Cards] Initial rates:', initialRates);
            if (initialRates && initialRates.length > 0) {
                self.updateShippingMethods(initialRates);
                self.isVisible(true);
            }
            
            return self;
        },

        /**
         * Update shipping methods from Magento rates
         */
        updateShippingMethods: function(rates) {
            var self = this;
            console.log('[Shipping Cards] Updating methods from rates:', rates);
            
            var methods = rates.map(function(rate) {
                // Get image from extension attributes if available
                var logo = 'https://dev.technostationery.com/media/mageplaza/tablerate/techno.png'; // Default
                if (rate.extension_attributes && rate.extension_attributes.mptablerate_image) {
                    logo = rate.extension_attributes.mptablerate_image;
                }
                
                // Get delivery info from extension attributes
                var deliveryInfo = '';
                if (rate.extension_attributes && rate.extension_attributes.mptablerate_comment) {
                    deliveryInfo = rate.extension_attributes.mptablerate_comment;
                }
                
                // Determine delivery time based on method title
                var deliveryTime = 'Disponible';
                if (rate.method_title.toLowerCase().indexOf('retrait') >= 0 && rate.method_title.toLowerCase().indexOf('techno') >= 0) {
                    deliveryTime = 'Retrait immédiat';
                } else if (rate.method_title.toLowerCase().indexOf('retrait') >= 0) {
                    deliveryTime = '2-3 jours';
                } else if (rate.method_title.toLowerCase().indexOf('domicile') >= 0) {
                    deliveryTime = '3-5 jours';
                }
                
                return {
                    method_code: rate.carrier_code + '_' + rate.method_code,
                    carrier_code: rate.carrier_code,
                    method_id: rate.method_code,
                    method_title: rate.method_title,
                    amount: rate.amount,
                    price_formatted: rate.amount === 0 ? 'Gratuit' : rate.price_incl_tax.toFixed(2) + ' DZD',
                    carrier_logo: logo,
                    delivery_time: deliveryTime,
                    is_free: rate.amount === 0,
                    description: deliveryInfo || rate.method_title
                };
            });
            
            console.log('[Shipping Cards] Converted methods:', methods);
            self.shippingMethods(methods);
            
            // Force DOM update
            setTimeout(function() {
                var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                if (wrapper) {
                    wrapper.style.display = 'block';
                    wrapper.style.visibility = 'visible';
                    wrapper.style.opacity = '1';
                }
            }, 100);
        },

        /**
         * Get shipping methods
         */
        getShippingMethods: function () {
            return this.shippingMethods();
        },

        /**
         * Select shipping method
         */
        selectMethod: function (method) {
            var self = this;
            console.log('[Shipping Cards] Selecting method:', method);
            
            self.selectedMethod(method.method_code);
            
            // Find the original rate to get all data
            var rates = shippingService.getShippingRates()();
            var selectedRate = rates.find(function(rate) {
                return (rate.carrier_code + '_' + rate.method_code) === method.method_code;
            });
            
            if (selectedRate) {
                selectShippingMethodAction(selectedRate);
                checkoutData.setSelectedShippingRate(method.method_code);
            }
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
            return classes;
        }
    });
});
