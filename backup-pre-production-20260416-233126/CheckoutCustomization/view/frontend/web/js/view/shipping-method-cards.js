/**
 * Shipping Method Cards Component - Dynamic Version
 * Displays shipping options as cards for ANY region
 * Reads methods dynamically from Magento shipping service
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'mage/translate'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData, $t) {
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
            
            // Observable arrays and properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(true); // Always visible
            self.currentRegion = ko.observable(null);
            self.isLoading = ko.observable(false);
            
            console.log('Shipping cards component initialized');
            
            // Subscribe to shipping rates from Magento
            shippingService.getShippingRates().subscribe(function (rates) {
                console.log('Shipping rates received:', rates);
                self.processShippingRates(rates);
            });
            
            // Subscribe to quote shipping method changes
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    console.log('Quote shipping method changed:', method);
                    self.selectedMethod(method.carrier_code + '_' + method.method_code);
                }
            });
            
            // Subscribe to shipping address changes to detect region
            quote.shippingAddress.subscribe(function (address) {
                console.log('Address changed:', address);
                if (address && (address.regionId || address.region)) {
                    var regionName = address.region || 'Region ' + address.regionId;
                    console.log('Region detected:', regionName);
                    self.currentRegion(regionName);
                    self.isVisible(true);
                }
            });
            
            // Check initial state
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                console.log('Processing initial rates:', initialRates);
                self.processShippingRates(initialRates);
            }
            
            var initialAddress = quote.shippingAddress();
            console.log('Initial address on init:', initialAddress);
            if (initialAddress && (initialAddress.regionId || initialAddress.region)) {
                var regionName = initialAddress.region || 'Region ' + initialAddress.regionId;
                console.log('Initial region set:', regionName);
                self.currentRegion(regionName);
                self.isVisible(true);
            }
            
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                console.log('Initial method:', currentMethod);
                self.selectedMethod(currentMethod.carrier_code + '_' + currentMethod.method_code);
            }
            
            // Force visibility after short delay to ensure DOM is ready
            setTimeout(function() {
                console.log('Force visibility timeout triggered');
                self.isVisible(true);
                var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                if (wrapper) {
                    wrapper.style.display = 'block';
                    wrapper.style.visibility = 'visible';
                    wrapper.style.opacity = '1';
                    console.log('Wrapper made visible via DOM manipulation');
                } else {
                    console.warn('Wrapper element not found!');
                }
            }, 500);
            
            return self;
        },

        /**
         * Process shipping rates from Magento
         * @param {Array} rates
         */
        processShippingRates: function (rates) {
            var self = this;
            var methods = [];
            
            console.log('Processing rates, count:', rates.length);
            
            rates.forEach(function (rate) {
                console.log('Processing rate:', rate);
                
                var method = {
                    method_code: rate.carrier_code + '_' + rate.method_code,
                    carrier_code: rate.carrier_code,
                    method_id: rate.method_code,
                    method_title: rate.method_title || rate.carrier_title,
                    amount: parseFloat(rate.amount) || 0,
                    price_formatted: self.formatPrice(rate.amount),
                    carrier_logo: self.getCarrierLogo(rate),
                    delivery_time: self.getDeliveryTime(rate),
                    is_free: parseFloat(rate.amount) === 0,
                    description: self.getMethodDescription(rate),
                    error_message: rate.error_message || '',
                    available: rate.available !== false
                };
                
                console.log('Created method object:', method);
                methods.push(method);
            });
            
            console.log('Setting methods array, count:', methods.length);
            self.shippingMethods(methods);
            
            // Make visible if we have methods
            if (methods.length > 0) {
                self.isVisible(true);
                console.log('Methods loaded, setting visible');
            }
        },

        /**
         * Get carrier logo URL
         * @param {Object} rate
         * @returns {String}
         */
        getCarrierLogo: function (rate) {
            var baseUrl = 'https://dev.technostationery.com/media/mageplaza/tablerate/';
            var methodCode = rate.method_code;
            
            // Map method codes to logos
            var logoMap = {
                '17': 'techno.png',      // Retrait Techno Batna
                '20': 'techno.png',      // Retrait Techno Setif
                '24': 'yalidine-logo.jpg', // Retrait en agence
                '2': 'yalidine-logo.jpg'   // Livraison à domicile
            };
            
            // Check if rate has image property
            if (rate.image) {
                return rate.image;
            }
            
            // Use mapping or default
            var logo = logoMap[methodCode] || 'default-carrier.png';
            return baseUrl + logo;
        },

        /**
         * Get delivery time text
         * @param {Object} rate
         * @returns {String}
         */
        getDeliveryTime: function (rate) {
            var methodCode = rate.method_code;
            var title = (rate.method_title || '').toLowerCase();
            
            // Map based on method code or title
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
         * @param {Object} rate
         * @returns {String}
         */
        getMethodDescription: function (rate) {
            var methodCode = rate.method_code;
            var title = (rate.method_title || '').toLowerCase();
            var region = this.currentRegion() || '';
            
            if (methodCode === '17' || methodCode === '20' || title.includes('retrait techno')) {
                return $t('Retirez votre commande à notre magasin de %1').replace('%1', region);
            } else if (methodCode === '24' || title.includes('retrait en agence')) {
                return $t('Retrait à l\'agence Yalidine la plus proche');
            } else if (methodCode === '2' || title.includes('livraison')) {
                return $t('Livraison directement à votre domicile');
            }
            
            return rate.carrier_title || '';
        },

        /**
         * Format price
         * @param {Number} amount
         * @returns {String}
         */
        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            
            if (price === 0) {
                return $t('Gratuit');
            }
            
            return price.toFixed(0) + ' DA';
        },

        /**
         * Get shipping methods
         * @returns {Array}
         */
        getShippingMethods: function () {
            return this.shippingMethods();
        },

        /**
         * Select shipping method
         * @param {Object} method
         */
        selectMethod: function (method) {
            var self = this;
            
            console.log('Selecting shipping method:', method);
            
            if (!method.available) {
                console.warn('Method not available:', method);
                return;
            }
            
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

            console.log('Calling selectShippingMethodAction with:', shippingMethod);
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(method.carrier_code + '_' + method.method_id);
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
            
            if (!method.available) {
                classes += ' unavailable';
            }
            
            return classes;
        },

        /**
         * Get region name for display
         * @returns {String}
         */
        getRegionName: function () {
            return this.currentRegion() || $t('votre région');
        }
    });
});
