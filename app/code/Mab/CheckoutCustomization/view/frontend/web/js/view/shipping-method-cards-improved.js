/**
 * Mab_CheckoutCustomization - Enhanced Shipping Method Cards with Region Filtering
 * Properly integrates with Mageplaza TableRateShipping region-based filtering
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/model/shipping-service',
    'mage/translate'
], function ($, ko, Component, quote, selectShippingMethodAction, shippingService, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards'
        },

        shippingMethods: ko.observableArray([]),
        selectedMethod: ko.observable(null),
        isLoading: ko.observable(false),

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            var self = this;

            // Subscribe to shipping rates changes (triggered by region selection)
            shippingService.getShippingRates().subscribe(function (rates) {
                console.log('Shipping rates updated:', rates);
                self.processShippingRates(rates);
            });

            // Subscribe to region/address changes
            quote.shippingAddress.subscribe(function (address) {
                console.log('Shipping address changed:', address);
                self.isLoading(true);
                // Wait for Mageplaza to filter methods by region
                setTimeout(function () {
                    self.refreshShippingCards();
                }, 1000);
            });

            // Watch for selected shipping method
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    var methodCode = method.carrier_code + '_' + method.method_code;
                    self.selectedMethod(methodCode);
                }
            });

            // Initial load
            setTimeout(function () {
                self.refreshShippingCards();
            }, 1500);

            return this;
        },

        /**
         * Process shipping rates from service
         */
        processShippingRates: function (rates) {
            var self = this;
            self.isLoading(false);
            
            if (!rates || rates.length === 0) {
                console.warn('No shipping rates available');
                self.shippingMethods([]);
                return;
            }

            // Convert rates to card format
            var methods = [];
            rates.forEach(function (rate) {
                methods.push({
                    carrier_code: rate.carrier_code,
                    method_code: rate.method_code,
                    carrier_title: rate.carrier_title,
                    method_title: rate.method_title,
                    price_excl_tax: rate.amount,
                    price_incl_tax: rate.amount,
                    available: rate.available,
                    error_message: rate.error_message
                });
            });

            self.shippingMethods(methods);
            console.log('Processed shipping methods:', methods.length);
        },

        /**
         * Refresh shipping cards (re-read from DOM for Mageplaza compatibility)
         */
        refreshShippingCards: function () {
            var self = this;
            var $shippingTable = $('#checkout-shipping-method-load table.table-checkout-shipping-method');

            if ($shippingTable.length === 0 || $shippingTable.find('tbody tr').length === 0) {
                console.log('Waiting for shipping table to load...');
                setTimeout(function () {
                    self.refreshShippingCards();
                }, 500);
                return;
            }

            var methods = [];
            $shippingTable.find('tbody tr').each(function () {
                var $row = $(this);
                var $input = $row.find('input[type="radio"]');
                var $priceCell = $row.find('.col-price .price');
                var $methodCell = $row.find('.col-method');
                var $carrierCell = $row.find('.col-carrier');

                if ($input.length) {
                    var inputValue = $input.val();
                    var parts = inputValue.split('_');
                    var carrierCode = parts[0];
                    var methodCode = parts.slice(1).join('_');

                    methods.push({
                        carrier_code: carrierCode,
                        method_code: methodCode,
                        carrier_title: $carrierCell.text().trim() || self.getCarrierLabel(carrierCode),
                        method_title: $methodCell.text().trim(),
                        price_excl_tax: self.extractPrice($priceCell),
                        price_incl_tax: self.extractPrice($priceCell),
                        available: true,
                        error_message: ''
                    });
                }
            });

            self.shippingMethods(methods);
            self.isLoading(false);
            console.log('Refreshed shipping methods from table:', methods.length);

            // Hide original table, show cards
            $shippingTable.addClass('shipping-table-hidden');
        },

        /**
         * Extract price from jQuery element
         */
        extractPrice: function ($elem) {
            if (!$elem || $elem.length === 0) {
                return 0;
            }
            var text = $elem.text().trim();
            // Remove currency symbols and parse
            var price = parseFloat(text.replace(/[^\d.,]/g, '').replace(',', '.'));
            return isNaN(price) ? 0 : price;
        },

        /**
         * Get carrier display label
         */
        getCarrierLabel: function (code) {
            var labels = {
                'mptablerate': $t('Méthodes de livraison'),
                'flatrate': $t('Tarif fixe'),
                'freeshipping': $t('Livraison gratuite'),
                'tablerate': $t('Tarif variable')
            };
            return labels[code] || code;
        },

        /**
         * Get carrier icon class
         */
        getCarrierIcon: function (method) {
            var carrierCode = method.carrier_code.toLowerCase();
            var methodTitle = method.method_title.toLowerCase();

            if (methodTitle.indexOf('domicile') !== -1 || methodTitle.indexOf('home') !== -1) {
                return 'fa-home';
            } else if (methodTitle.indexOf('store') !== -1 || methodTitle.indexOf('magasin') !== -1) {
                return 'fa-store';
            } else if (methodTitle.indexOf('express') !== -1 || methodTitle.indexOf('rapide') !== -1) {
                return 'fa-bolt';
            } else if (methodTitle.indexOf('standard') !== -1) {
                return 'fa-truck';
            }

            return 'fa-shipping-fast';
        },

        /**
         * Get estimated delivery time based on method
         */
        getDeliveryEstimate: function (method) {
            var methodTitle = method.method_title.toLowerCase();

            if (methodTitle.indexOf('express') !== -1 || methodTitle.indexOf('rapide') !== -1) {
                return $t('24-48h');
            } else if (methodTitle.indexOf('standard') !== -1) {
                return $t('3-5 jours');
            } else if (methodTitle.indexOf('store') !== -1 || methodTitle.indexOf('magasin') !== -1) {
                return $t('Retrait immédiat');
            }

            return $t('2-4 jours');
        },

        /**
         * Select shipping method
         */
        selectMethod: function (method) {
            var self = this;
            var methodCode = method.carrier_code + '_' + method.method_code;

            // Update observable
            self.selectedMethod(methodCode);

            // Find original radio input and trigger click
            var $input = $('input[name="shipping_method"][value="' + methodCode + '"]');
            if ($input.length) {
                $input.prop('checked', true).trigger('click').trigger('change');
            }

            // Use Magento's action to select shipping method
            selectShippingMethodAction({
                'carrier_code': method.carrier_code,
                'method_code': method.method_code
            });

            console.log('Selected shipping method:', methodCode);
        },

        /**
         * Check if method is selected
         */
        isSelected: function (method) {
            var methodCode = method.carrier_code + '_' + method.method_code;
            return this.selectedMethod() === methodCode;
        },

        /**
         * Format price with currency
         */
        formatPrice: function (price) {
            if (!price || price === 0) {
                return $t('Gratuit');
            }
            return price.toFixed(2) + ' DZD';
        },

        /**
         * Get method description
         */
        getMethodDescription: function (method) {
            if (method.error_message) {
                return method.error_message;
            }
            return method.method_title || '';
        }
    });
});
