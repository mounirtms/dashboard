/**
 * Checkout Step Navigation Fix
 * Ensures Next button appears after shipping method selection
 * Triggers Magento's checkout step validation properly
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/step-navigator',
    'mage/translate'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData, stepNavigator, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards'
        },

        initialize: function () {
            var self = this;
            self._super();

            // Observable properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(true);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');

            // Subscribe to shipping rates
            shippingService.getShippingRates().subscribe(function (rates) {
                if (rates && rates.length > 0) {
                    var hasValidRates = rates.some(function(rate) {
                        return rate.method_code && rate.method_code !== null && rate.method_code !== 'null' && rate.available !== false;
                    });

                    if (hasValidRates) {
                        self.processShippingRates(rates);
                        self.isLoading(false);
                    } else {
                        self.shippingMethods([]);
                        self.isVisible(false);
                        self.errorMessage($t('Aucune méthode de livraison disponible pour cette région'));
                    }
                } else {
                    self.shippingMethods([]);
                    self.isVisible(false);
                    self.errorMessage($t('Aucune méthode de livraison disponible'));
                }
            });

            // Subscribe to address changes
            quote.shippingAddress.subscribe(function (address) {
                if (address && (address.regionId || address.region)) {
                    var region = address.region || address.regionCode || 'Region ' + address.regionId;
                    var previousRegion = self.currentRegion();

                    if (previousRegion !== region) {
                        self.selectedMethod(null);
                        self.shippingMethods([]);
                    }

                    self.currentRegion(region);
                    self.isLoading(true);
                }
            });

            // Subscribe to selected shipping method (without triggering validation loop)
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    // Only update UI, don't trigger validation here
                    self.selectedMethod(methodCode);
                }
            });

            // Check initial state
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                self.processShippingRates(initialRates);
            }

            var initialAddress = quote.shippingAddress();
            if (initialAddress && (initialAddress.regionId || initialAddress.region)) {
                self.currentRegion(initialAddress.region || initialAddress.regionCode || 'Region ' + initialAddress.regionId);
            }

            return self;
        },

        /**
         * Process shipping rates
         */
        processShippingRates: function (rates) {
            var self = this;
            var methods = [];

            $.each(rates, function (index, rate) {
                if (!rate.method_code || rate.method_code === null || rate.method_code === 'null') {
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
                    available: rate.available !== false
                });
            });

            if (methods.length === 0) {
                self.isVisible(false);
                self.errorMessage($t('Aucune méthode disponible'));
                return;
            }

            self.shippingMethods(methods);
            self.isVisible(true);
            self.errorMessage('');
        },

        /**
         * Select shipping method - SIMPLIFIED FIX
         */
        selectMethod: function (method, event) {
            var self = this;

            if (!method.available) {
                return false;
            }

            // Prevent event bubbling
            if (event && event.stopPropagation) {
                event.stopPropagation();
            }

            // Update UI selection
            self.selectedMethod(method.method_code);

            // Extract method code parts
            var actualMethodCode = method.method_code.split('_')[1] || method.method_code;

            // Create Magento shipping method object
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

            // Select in Magento
            try {
                console.log('[Shipping Cards] Selecting method:', method.method_code);
                
                // Save to checkout data first
                checkoutData.setSelectedShippingRate(method.carrier_code + '_' + actualMethodCode);
                
                // Then trigger Magento's selection action
                selectShippingMethodAction(shippingMethod);
                
                console.log('[Shipping Cards] Method selected successfully');
                
                // Force quote update to trigger validation
                setTimeout(function() {
                    quote.shippingMethod.valueHasMutated();
                }, 50);

            } catch (error) {
                console.error('[Shipping Cards] Error selecting method:', error);
            }
            
            return false;
        },



        /**
         * Format price
         */
        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            if (price === 0) {
                return $t('Gratuit');
            }
            return price.toFixed(2).replace('.', ',') + ' DZD';
        },

        /**
         * Get carrier logo
         */
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

            if (title.includes('techno')) return baseUrl + 'techno.png';
            if (title.includes('yalidine') || title.includes('agence') || title.includes('domicile')) {
                return baseUrl + 'yalidine-logo.jpg';
            }

            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIGZpbGw9IiNlMGUwZTAiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1zaXplPSIxMCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+Q2FycmllcjwvdGV4dD48L3N2Zz4=';
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
         * Get shipping methods
         */
        getShippingMethods: function () {
            return this.shippingMethods();
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
         * Get region name
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
