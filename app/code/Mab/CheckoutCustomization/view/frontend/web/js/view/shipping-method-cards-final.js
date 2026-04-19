/**
 * Shipping Method Cards - FINAL VERSION
 * Ensures shipping methods display and Next button appears after selection
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
            template: 'Mab_CheckoutCustomization/shipping-method-cards-working'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            
            self._super();
            
            console.log('🚀 [Shipping Cards FINAL] Component initializing...');
            
            // Observable properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(false);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');
            
            // Subscribe to shipping rates from Magento/Mageplaza
            shippingService.getShippingRates().subscribe(function (rates) {
                console.log('📦 [Shipping Cards FINAL] Rates received:', rates.length);
                
                if (rates && rates.length > 0) {
                    self.processShippingRates(rates);
                    self.isVisible(true);
                    self.isLoading(false);
                    self.errorMessage('');
                    
                    // Force visibility on wrapper
                    setTimeout(function() {
                        $('.shipping-methods-cards-wrapper').css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                        console.log('✅ [Shipping Cards FINAL] Wrapper made visible');
                    }, 50);
                } else {
                    console.warn('⚠️ [Shipping Cards FINAL] No rates available');
                    self.shippingMethods([]);
                    self.isVisible(false);
                    self.errorMessage($t('Aucune méthode de livraison disponible'));
                }
            });
            
            // Subscribe to quote shipping address changes
            quote.shippingAddress.subscribe(function (address) {
                if (address && (address.regionId || address.region)) {
                    var region = address.region || address.regionCode || 'Region ' + address.regionId;
                    self.currentRegion(region);
                    self.isLoading(true);
                    console.log('📍 [Shipping Cards FINAL] Address changed to:', region);
                }
            });
            
            // Subscribe to selected shipping method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                    console.log('✅ [Shipping Cards FINAL] Method selected:', methodCode);
                    
                    // Ensure next button is visible
                    self.ensureNextButtonVisible();
                }
            });
            
            // Check for initial rates
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                console.log('📦 [Shipping Cards FINAL] Processing initial rates:', initialRates.length);
                self.processShippingRates(initialRates);
                self.isVisible(true);
            }
            
            // Check initial address
            var initialAddress = quote.shippingAddress();
            if (initialAddress && (initialAddress.regionId || initialAddress.region)) {
                var region = initialAddress.region || initialAddress.regionCode || 'Region ' + initialAddress.regionId;
                self.currentRegion(region);
            }
            
            // Check initial selected method
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                var methodCode = currentMethod.carrier_code + '_' + currentMethod.method_code;
                self.selectedMethod(methodCode);
                console.log('✅ [Shipping Cards FINAL] Initial method:', methodCode);
            }
            
            console.log('✅ [Shipping Cards FINAL] Component initialized');
            
            return self;
        },

        /**
         * Process shipping rates and create method objects
         */
        processShippingRates: function (rates) {
            var self = this;
            var methods = [];
            
            console.log('🔄 [Shipping Cards FINAL] Processing', rates.length, 'rates...');
            
            $.each(rates, function (index, rate) {
                if (!rate.carrier_code || !rate.method_code) {
                    console.warn('⚠️ [Shipping Cards FINAL] Skipping invalid rate:', rate);
                    return true; // continue
                }
                
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
                    available: rate.available !== false
                };
                
                methods.push(method);
                console.log('✅ [Shipping Cards FINAL] Added method:', method.method_code);
            });
            
            self.shippingMethods(methods);
            console.log('✅ [Shipping Cards FINAL] Total methods set:', methods.length);
        },

        /**
         * Get carrier logo based on rate
         */
        getCarrierLogo: function (rate) {
            var baseUrl = 'https://dev.technostationery.com/media/mageplaza/tablerate/';
            var methodCode = rate.method_code;
            var title = (rate.method_title || rate.carrier_title || '').toLowerCase();
            
            // Logo mapping by method code (from database)
            var logoMap = {
                '17': 'techno.png',          // Retrait Techno Batna
                '20': 'techno.png',          // Retrait Techno Setif
                '24': 'yalidine-logo.jpg',   // Retrait en agence
                '2': 'yalidine-logo.jpg'     // Livraison à domicile
            };
            
            // Use rate image if provided
            if (rate.image) {
                return rate.image;
            }
            
            // Check by exact method code from database
            if (logoMap[methodCode]) {
                return baseUrl + logoMap[methodCode];
            }
            
            // Fallback: check by title keywords
            if (title.includes('techno') || title.includes('retrait techno')) {
                return baseUrl + 'techno.png';
            } else if (title.includes('yalidine') || title.includes('agence') || title.includes('domicile')) {
                return baseUrl + 'yalidine-logo.jpg';
            }
            
            // Default carrier image
            return baseUrl + 'default-carrier.png';
        },

        /**
         * Get delivery time based on method
         */
        getDeliveryTime: function (rate) {
            var methodCode = rate.method_code;
            var title = (rate.method_title || '').toLowerCase();
            
            // Map by exact method code from database
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
            
            // Descriptions based on actual method codes from database
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
         * Format price with DZD currency
         */
        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            
            if (price === 0) {
                return $t('Gratuit');
            }
            
            // Format: "XXX,XX DZD"
            return price.toFixed(2).replace('.', ',') + ' DZD';
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
            
            console.log('👆 [Shipping Cards FINAL] User clicked:', method.method_code);
            
            if (!method.available) {
                console.warn('⚠️ [Shipping Cards FINAL] Method not available');
                return;
            }
            
            // Update UI selection
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
            
            console.log('📝 [Shipping Cards FINAL] Calling selectShippingMethodAction');
            
            // Select in Magento
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(method.carrier_code + '_' + method.method_id);
            
            // Force quote update
            setTimeout(function() {
                quote.shippingMethod.valueHasMutated();
                console.log('✅ [Shipping Cards FINAL] Quote updated');
                
                // Ensure next button is visible
                self.ensureNextButtonVisible();
                
                // Trigger custom event for other components
                $(document).trigger('shipping-method-selected', [shippingMethod]);
            }, 100);
            
            console.log('✅ [Shipping Cards FINAL] Method selected successfully');
        },

        /**
         * Ensure the Next button is visible and functional
         */
        ensureNextButtonVisible: function() {
            console.log('🔍 [Shipping Cards FINAL] Ensuring Next button is visible...');
            
            // Multiple selectors to catch all possible button locations
            var buttonSelectors = [
                '#shipping-method-buttons-container',
                '.opc-wrapper .step-content .actions-toolbar',
                '.checkout-shipping-method .actions-toolbar',
                '.button.action.continue.primary'
            ];
            
            setTimeout(function() {
                buttonSelectors.forEach(function(selector) {
                    $(selector).css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    }).show();
                });
                
                console.log('✅ [Shipping Cards FINAL] Next button made visible');
            }, 150);
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
