/**
 * Shipping Method Cards - HOTFIX VERSION
 * ULTRA-AGGRESSIVE Next Button Display + Proper Magento Validation
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/step-navigator',
    'mage/translate'
], function ($, ko, Component, quote, shippingService, selectShippingMethodAction, setShippingInformationAction, checkoutData, stepNavigator, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards-working'
        },

        initialize: function () {
            var self = this;
            
            self._super();
            
            console.log('🚀 [HOTFIX Shipping] Component initializing...');
            
            // Observable properties
            self.shippingMethods = ko.observableArray([]);
            self.selectedMethod = ko.observable(null);
            self.isVisible = ko.observable(false);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');
            
            // Subscribe to shipping rates
            shippingService.getShippingRates().subscribe(function (rates) {
                console.log('📦 [HOTFIX Shipping] Rates received:', rates.length);
                
                if (rates && rates.length > 0) {
                    self.processShippingRates(rates);
                    self.isVisible(true);
                    self.isLoading(false);
                    self.errorMessage('');
                    
                    // Ultra-aggressive wrapper visibility
                    setTimeout(function() {
                        $('.shipping-methods-cards-wrapper').attr('style', 
                            'display: block !important; visibility: visible !important; opacity: 1 !important;'
                        );
                        console.log('✅ [HOTFIX Shipping] Wrapper forced visible');
                    }, 50);
                } else {
                    console.warn('⚠️ [HOTFIX Shipping] No rates available');
                    self.shippingMethods([]);
                    self.isVisible(false);
                    self.errorMessage($t('Aucune méthode de livraison disponible'));
                }
            });
            
            // Subscribe to address changes
            quote.shippingAddress.subscribe(function (address) {
                if (address && (address.regionId || address.region)) {
                    var region = address.region || address.regionCode || 'Region ' + address.regionId;
                    self.currentRegion(region);
                    self.isLoading(true);
                    console.log('📍 [HOTFIX Shipping] Address changed to:', region);
                }
            });
            
            // Subscribe to selected method - ENHANCED
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                    console.log('✅ [HOTFIX Shipping] Method selected:', methodCode);
                    
                    // ULTRA-AGGRESSIVE button display
                    self.forceNextButtonDisplay();
                    
                    // Validate the selection to enable the button
                    self.validateShippingInformation();
                }
            });
            
            // Process initial rates
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                console.log('📦 [HOTFIX Shipping] Processing initial rates:', initialRates.length);
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
                console.log('✅ [HOTFIX Shipping] Initial method:', methodCode);
                self.forceNextButtonDisplay();
            }
            
            console.log('✅ [HOTFIX Shipping] Component initialized');
            
            return self;
        },

        processShippingRates: function (rates) {
            var self = this;
            var methods = [];
            
            console.log('🔄 [HOTFIX Shipping] Processing', rates.length, 'rates...');
            
            $.each(rates, function (index, rate) {
                // Skip rates with missing carrier_code OR method_code
                if (!rate || !rate.carrier_code || !rate.method_code) {
                    console.warn('⚠️ [HOTFIX Shipping] Skipping invalid rate (missing carrier or method):', {
                        carrier_code: rate ? rate.carrier_code : null,
                        method_code: rate ? rate.method_code : null,
                        carrier_title: rate ? rate.carrier_title : 'N/A'
                    });
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
                console.log('✅ [HOTFIX Shipping] Added method:', method.method_code);
            });
            
            self.shippingMethods(methods);
            console.log('✅ [HOTFIX Shipping] Total methods set:', methods.length);
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
            if (price === 0) return $t('Gratuit');
            return price.toFixed(2).replace('.', ',') + ' DZD';
        },

        getShippingMethods: function () {
            return this.shippingMethods();
        },

        selectMethod: function (method) {
            var self = this;
            
            console.log('👆 [HOTFIX Shipping] User clicked:', method.method_code);
            
            if (!method.available) {
                console.warn('⚠️ [HOTFIX Shipping] Method not available');
                return;
            }
            
            // Update UI
            self.selectedMethod(method.method_code);
            
            // Create proper shipping method object
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
            
            console.log('📝 [HOTFIX Shipping] Calling selectShippingMethodAction');
            
            // Select in Magento
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(method.carrier_code + '_' + method.method_id);
            
            // Force quote update with multiple triggers
            setTimeout(function() {
                quote.shippingMethod.valueHasMutated();
                console.log('✅ [HOTFIX Shipping] Quote updated');
                
                // Auto-advance to payment step
                self.proceedToPayment();
                
                // Trigger events
                $(document).trigger('shipping-method-selected', [shippingMethod]);
                $(document).trigger('checkout-data-updated');
            }, 100);
            
            console.log('✅ [HOTFIX Shipping] Method selected successfully');
        },

        /**
         * Proceed to payment step after shipping method selection
         */
        proceedToPayment: function() {
            var self = this;
            
            console.log('🚀 [HOTFIX Shipping] Proceeding to payment step...');
            
            // Check if shipping method is selected
            var method = quote.shippingMethod();
            if (!method || !method.carrier_code || !method.method_code) {
                console.warn('⚠️ [HOTFIX Shipping] No shipping method selected, cannot proceed');
                return;
            }
            
            // Call setShippingInformation to advance to next step
            var deferred = setShippingInformationAction();
            
            if (deferred) {
                deferred.done(function() {
                    console.log('✅ [HOTFIX Shipping] Successfully moved to payment step');
                }).fail(function() {
                    console.warn('⚠️ [HOTFIX Shipping] Failed to move to payment step, trying alternative method');
                    // Fallback: manually navigate to next step
                    var steps = stepNavigator.steps();
                    for (var i = 0; i < steps.length; i++) {
                        if (steps[i].isVisible && !steps[i].isComplete()) {
                            stepNavigator.navigateTo(steps[i]);
                            console.log('✅ [HOTFIX Shipping] Navigated to step:', steps[i].code);
                            break;
                        }
                    }
                });
            }
        },

        /**
         * ULTRA-AGGRESSIVE Next Button Display
         * Uses multiple techniques to force button visibility
         */
        forceNextButtonDisplay: function() {
            console.log('🔍 [HOTFIX Shipping] FORCING Next button display...');
            
            var attempts = 0;
            var maxAttempts = 5;
            
            var forceDisplay = function() {
                attempts++;
                
                // Strategy 1: CSS manipulation
                var selectors = [
                    '.opc-wrapper .step-content .actions-toolbar',
                    '#shipping-method-buttons-container',
                    '.checkout-shipping-method .actions-toolbar',
                    '.step-content > .actions-toolbar',
                    '[name="shippingAddress.shipping-address-fieldset"] .actions-toolbar',
                    'div[name="shippingAddress"] .actions-toolbar'
                ];
                
                selectors.forEach(function(selector) {
                    $(selector).each(function() {
                        $(this).attr('style', 'display: flex !important; visibility: visible !important; opacity: 1 !important;');
                        $(this).show();
                        $(this).css({
                            'display': 'flex',
                            'visibility': 'visible',
                            'opacity': '1',
                            'height': 'auto',
                            'max-height': 'none'
                        });
                    });
                });
                
                // Strategy 2: Force buttons directly
                var buttonSelectors = [
                    '.opc-wrapper button.action.continue',
                    'button.action.continue.primary',
                    '.actions-toolbar button.action.primary',
                    'button[type="submit"].action.continue'
                ];
                
                buttonSelectors.forEach(function(selector) {
                    $(selector).each(function() {
                        $(this).attr('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important;');
                        $(this).show();
                        $(this).prop('disabled', false);
                    });
                });
                
                // Strategy 3: Remove hidden classes
                $('.actions-toolbar').removeClass('no-display').removeClass('hidden');
                $('button.action.continue').removeClass('disabled').removeClass('hidden');
                
                // Strategy 4: Knockout binding override
                $('.actions-toolbar[data-bind*="visible"]').each(function() {
                    $(this).show().css('display', 'flex');
                });
                
                console.log('✅ [HOTFIX Shipping] Button force attempt #' + attempts);
                
                // Retry if button still not visible
                if (attempts < maxAttempts) {
                    var isVisible = $('.opc-wrapper button.action.continue:visible').length > 0;
                    if (!isVisible) {
                        console.warn('⚠️ [HOTFIX Shipping] Button still hidden, retrying...');
                        setTimeout(forceDisplay, 200);
                    } else {
                        console.log('🎉 [HOTFIX Shipping] Button is now VISIBLE!');
                    }
                }
            };
            
            // Execute immediately and with delays
            forceDisplay();
            setTimeout(forceDisplay, 150);
            setTimeout(forceDisplay, 300);
        },

        /**
         * Validate shipping information to enable the Next button
         */
        validateShippingInformation: function() {
            console.log('✓ [HOTFIX Shipping] Validating shipping information...');
            
            try {
                // Check if shipping method is selected
                var method = quote.shippingMethod();
                if (method && method.carrier_code && method.method_code) {
                    console.log('✓ [HOTFIX Shipping] Validation passed:', method.carrier_code + '_' + method.method_code);
                    
                    // Trigger validation events
                    $(document).trigger('shipping-method-validated');
                    
                    // Force button enabled state
                    setTimeout(function() {
                        $('button.action.continue').prop('disabled', false).removeClass('disabled');
                    }, 100);
                }
            } catch (e) {
                console.error('❌ [HOTFIX Shipping] Validation error:', e);
            }
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
