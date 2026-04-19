/**
 * COMPREHENSIVE FIX - Shipping Method Cards with Next Button Trigger
 * Fixes: Next button not appearing, proper validation trigger
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
    'Magento_Checkout/js/action/set-shipping-information',
    'mage/translate'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, checkoutData, stepNavigator, setShippingInformationAction, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards'
        },

        initialize: function () {
            var self = this;
            self._super();

            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(true);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');

            // Subscribe to shipping rates
            shippingService.getShippingRates().subscribe(function (rates) {
                if (rates && rates.length > 0) {
                    self.processShippingRates(rates);
                    self.isLoading(false);
                } else {
                    self.shippingMethods([]);
                    self.isVisible(false);
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

            // Subscribe to selected shipping method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                }
            });

            return self;
        },

        /**
         * Process shipping rates
         */
        processShippingRates: function (rates) {
            var self = this;
            var methods = [];

            $.each(rates, function (index, rate) {
                if (!rate.method_code || rate.method_code === null || rate.method_code === 'null' || rate.available === false) {
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
                    available: true
                });
            });

            if (methods.length === 0) {
                self.isVisible(false);
                return;
            }

            self.shippingMethods(methods);
            self.isVisible(true);
        },

        /**
         * Select shipping method - FIXED: Triggers next button properly
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

            // Extract method code
            var actualMethodCode = method.method_id || method.method_code.split('_').pop();

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

            try {
                console.log('[Shipping] Selecting method:', method.carrier_code, actualMethodCode);
                
                // Save to checkout data
                checkoutData.setSelectedShippingRate(method.carrier_code + '_' + actualMethodCode);
                
                // Select method in quote
                selectShippingMethodAction(shippingMethod);
                
                // CRITICAL FIX: Trigger Magento's validation and show next button
                setTimeout(function() {
                    // Force quote update
                    quote.shippingMethod.valueHasMutated();
                    
                    // Trigger validation on shipping step
                    var shippingStep = stepNavigator.getActiveItemIndex();
                    if (shippingStep === 0) {
                        // Show the next button container
                        $('#shipping-method-buttons-container').show();
                        $('.button.action.continue.primary').show();
                        
                        // Trigger custom event for step completion
                        $(document).trigger('shipping-method-selected', [shippingMethod]);
                    }
                }, 100);

                console.log('[Shipping] Method selected, next button should appear');

            } catch (error) {
                console.error('[Shipping] Error selecting method:', error);
            }
            
            return false;
        },

        /**
         * Get carrier logo - NO HARDCODED NAMES
         */
        getCarrierLogo: function (rate) {
            var carrierCode = (rate.carrier_code || '').toLowerCase();
            
            // Use carrier code directly for logo path
            return require.toUrl('Mab_CheckoutCustomization/images/carriers/' + carrierCode + '.png');
        },

        /**
         * Get delivery time from method title or description
         */
        getDeliveryTime: function (rate) {
            var title = rate.method_title || rate.carrier_title || '';
            
            // Extract time patterns like "2-3 jours", "24h", etc.
            var timeMatch = title.match(/(\d+[-–]\d+|\d+)\s*(jour|jours|heure|heures|h|day|days)/i);
            if (timeMatch) {
                return timeMatch[0];
            }
            
            // Default based on amount
            if (parseFloat(rate.amount) === 0) {
                return $t('Standard');
            }
            
            return $t('Variable');
        },

        /**
         * Get method description
         */
        getMethodDescription: function (rate) {
            // Use actual carrier title without hardcoding
            return rate.carrier_title || rate.method_title || '';
        },

        /**
         * Format price
         */
        formatPrice: function (amount) {
            amount = parseFloat(amount) || 0;
            
            if (amount === 0) {
                return $t('Gratuit');
            }
            
            // French number format
            return new Intl.NumberFormat('fr-DZ', {
                style: 'currency',
                currency: 'DZD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }
    });
});
