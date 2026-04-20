/**
 * Shipping Method Cards - Enhanced Version
 *
 * Dynamic config from window.checkoutConfig (injected via x-magento-init).
 * Logos resolved from Mageplaza extension_attributes or fallback to database paths.
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

    // Dynamic config from PHP (injected via x-magento-init)
    var shippingConfig = (window.checkoutConfig && window.checkoutConfig.shippingMethodCards) || {};
    var messages = shippingConfig.messages || {};
    var mediaUrl = shippingConfig.mediaUrl || '';
    var defaultTechnoLogo = shippingConfig.defaultTechnoLogo || '';
    var defaultYalidineLogo = shippingConfig.defaultYalidineLogo || '';

    // Build logo map from database method data (populated by config provider)
    var methodLogoMap = shippingConfig.methodLogoMap || {};

    // Cache for preloaded images
    var imageCache = {};

    /**
     * Preload an image
     */
    function preloadImage(src) {
        if (src && !imageCache[src]) {
            imageCache[src] = new Image();
            imageCache[src].src = src;
        }
    }

    /**
     * Resolve logo URL for a shipping method
     * Priority: extension_attributes -> method_logo from config -> keyword-based fallback -> empty
     */
    function resolveLogo(rate) {
        // Priority 1: Extension attributes from API response
        if (rate.extension_attributes && rate.extension_attributes.mptablerate_image) {
            var extUrl = rate.extension_attributes.mptablerate_image;
            preloadImage(extUrl);
            return extUrl;
        }

        // Priority 2: Direct image field on rate
        if (rate.image) {
            preloadImage(rate.image);
            return rate.image;
        }

        // Priority 3: Lookup by method_id from config map (database-driven)
        var methodId = String(rate.method_code || '');
        if (methodId && methodLogoMap[methodId]) {
            preloadImage(methodLogoMap[methodId]);
            return methodLogoMap[methodId];
        }

        // Priority 4: Keyword-based fallback using method_title
        var title = (rate.method_title || '').toLowerCase();
        if (title.indexOf('yalidine') !== -1 && defaultYalidineLogo) {
            preloadImage(defaultYalidineLogo);
            return defaultYalidineLogo;
        }
        if (defaultTechnoLogo) {
            preloadImage(defaultTechnoLogo);
            return defaultTechnoLogo;
        }

        return '';
    }

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
            self.isVisible = ko.observable(false);
            self.isLoading = ko.observable(false);
            self.currentRegion = ko.observable('');
            self.errorMessage = ko.observable('');
            self.displayReady = ko.observable(false);

            // Subscribe to shipping rates
            shippingService.getShippingRates().subscribe(function (rates) {
                if (rates && rates.length > 0) {
                    self.processShippingRates(rates);
                    self.isVisible(true);
                    self.displayReady(true);
                    self.isLoading(false);
                    self.errorMessage('');
                } else {
                    self.shippingMethods([]);
                    self.isVisible(false);
                    self.errorMessage($t(messages.noMethodsAvailable || 'Aucune m\u00e9thode de livraison disponible'));
                }
            });

            // Subscribe to address changes
            quote.shippingAddress.subscribe(function (address) {
                if (address && (address.regionId || address.region)) {
                    var region = address.region || address.regionCode || 'Region ' + address.regionId;
                    self.currentRegion(region);
                    self.isLoading(true);
                }
            });

            // Subscribe to selected method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                    self.forceNextButtonDisplay();
                    self.validateShippingInformation();
                }
            });

            // Process initial rates
            var initialRates = shippingService.getShippingRates()();
            if (initialRates && initialRates.length > 0) {
                self.processShippingRates(initialRates);
                self.isVisible(true);
                self.displayReady(true);
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
                self.forceNextButtonDisplay();
            }

            return self;
        },

        processShippingRates: function (rates) {
            var self = this;
            var methods = [];

            $.each(rates, function (index, rate) {
                if (!rate || !rate.carrier_code || !rate.method_code) {
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
                    carrier_logo: resolveLogo(rate),
                    delivery_time: self.getDeliveryTime(rate),
                    is_free: parseFloat(rate.amount) === 0,
                    available: rate.available !== false
                };

                methods.push(method);
            });

            self.shippingMethods(methods);
        },

        getDeliveryTime: function (rate) {
            var title = (rate.method_title || '').toLowerCase();

            if (title.indexOf('livraison') !== -1 || title.indexOf('domicile') !== -1) {
                return $t(messages.delivery3to5Days || '3-5 jours');
            }
            if (title.indexOf('agence') !== -1 || (title.indexOf('yalidine') !== -1 && title.indexOf('retrait') !== -1)) {
                return $t(messages.delivery2to3Days || '2-3 jours');
            }
            return $t(messages.retraitImmediat || 'Retrait imm\u00e9diat');
        },

        formatPrice: function (amount) {
            var price = parseFloat(amount) || 0;
            if (price === 0) return $t(messages.freeShipping || 'Gratuit');
            return price.toFixed(2).replace('.', ',') + ' DZD';
        },

        getShippingMethods: function () {
            return this.shippingMethods();
        },

        /**
         * Replace the default Magento shipping step with our card-based UI
         * Called by the shipping-cards-mixin to render cards into the DOM
         */
        replaceShippingStep: function () {
            var self = this;
            var $shippingStep = $('#shipping-method-step, .checkout-shipping-method, [data-role="shipping-methods"]');

            if ($shippingStep.length === 0) {
                // Fallback: find the shipping step container
                $shippingStep = $('.opc-wrapper .step-content').first();
            }

            // Remove existing wrapper if present
            $('.shipping-methods-cards-wrapper').remove();

            // Render the KO template into the shipping step
            if ($shippingStep.length > 0) {
                // Apply knockout bindings to render the component
                var $container = $('<div class="shipping-cards-container"></div>');
                $shippingStep.prepend($container);

                // Apply KO bindings
                ko.applyBindings(self, $container[0]);
            }

            console.log('✅ Shipping cards DOM rendered');
        },

        selectMethod: function (method) {
            var self = this;

            if (!method.available) {
                return;
            }

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

            setTimeout(function() {
                quote.shippingMethod.valueHasMutated();
                self.proceedToPayment();
                $(document).trigger('shipping-method-selected', [shippingMethod]);
                $(document).trigger('checkout-data-updated');
            }, 100);
        },

        proceedToPayment: function() {
            var self = this;

            var method = quote.shippingMethod();
            if (!method || !method.carrier_code || !method.method_code) {
                return;
            }

            var deferred = setShippingInformationAction();

            if (deferred) {
                deferred.done(function() {
                    // Successfully moved to payment step
                }).fail(function() {
                    var steps = stepNavigator.steps();
                    for (var i = 0; i < steps.length; i++) {
                        if (steps[i].isVisible && !steps[i].isComplete()) {
                            stepNavigator.navigateTo(steps[i]);
                            break;
                        }
                    }
                });
            }
        },

        forceNextButtonDisplay: function() {
            var selectors = [
                '.opc-wrapper .step-content .actions-toolbar',
                '#shipping-method-buttons-container',
                '.checkout-shipping-method .actions-toolbar',
                '.step-content > .actions-toolbar'
            ];

            selectors.forEach(function(selector) {
                $(selector).each(function() {
                    $(this).css({
                        'display': 'flex',
                        'visibility': 'visible',
                        'opacity': '1',
                        'height': 'auto',
                        'max-height': 'none'
                    });
                });
            });

            $('button.action.continue').each(function() {
                $(this).css({
                    'display': 'inline-block',
                    'visibility': 'visible',
                    'opacity': '1'
                }).prop('disabled', false);
            });

            $('.actions-toolbar').removeClass('no-display').removeClass('hidden');
            $('button.action.continue').removeClass('disabled').removeClass('hidden');
        },

        validateShippingInformation: function() {
            var method = quote.shippingMethod();
            if (method && method.carrier_code && method.method_code) {
                $(document).trigger('shipping-method-validated');
                setTimeout(function() {
                    $('button.action.continue').prop('disabled', false).removeClass('disabled');
                }, 100);
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
            return this.currentRegion() || $t(messages.yourRegion || 'votre r\u00e9gion');
        },

        getShippingNoticeText: function () {
            var prefix = messages.selectShippingForRegion || 'S\u00e9lectionnez votre mode de livraison pour la r\u00e9gion de ';
            return $t(prefix) + this.getRegionName();
        },

        hasMethods: function () {
            return this.shippingMethods().length > 0;
        },

        isDisplayReady: function () {
            return this.displayReady();
        }
    });
});
