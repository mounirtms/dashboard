/**
 * Shipping Method Cards Component
 * Displays Mageplaza shipping options as cards for Batna region
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
         * Shipping methods data
         */
        shippingMethods: [
            {
                method_code: 'mptablerate_17',
                carrier_code: 'mptablerate',
                method_id: '17',
                method_title: 'Retrait Techno Batna',
                amount: 0,
                price_formatted: 'Gratuit',
                carrier_logo: 'https://dev.technostationery.com/media/mageplaza/tablerate/techno.png',
                delivery_time: 'Retrait immédiat',
                is_free: true,
                description: 'Retirez votre commande à notre magasin de Batna'
            },
            {
                method_code: 'mptablerate_24',
                carrier_code: 'mptablerate',
                method_id: '24',
                method_title: 'Retrait en agence',
                amount: 400,
                price_formatted: '400 DA',
                carrier_logo: 'https://dev.technostationery.com/media/mageplaza/tablerate/yalidine-logo.jpg',
                delivery_time: '2-3 jours',
                is_free: false,
                description: 'Retrait à l\'agence Yalidine la plus proche'
            },
            {
                method_code: 'mptablerate_2',
                carrier_code: 'mptablerate',
                method_id: '2',
                method_title: 'Livraison à domicile',
                amount: 500,
                price_formatted: '500 DA',
                carrier_logo: 'https://dev.technostationery.com/media/mageplaza/tablerate/yalidine-logo.jpg',
                delivery_time: '3-5 jours',
                is_free: false,
                description: 'Livraison directement à votre domicile'
            }
        ],

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.selectedMethod = ko.observable(null);
            
            // Subscribe to quote shipping method changes
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    this.selectedMethod(method.carrier_code + '_' + method.method_code);
                }
            }, this);

            // Set initial selection
            var currentMethod = quote.shippingMethod();
            if (currentMethod) {
                this.selectedMethod(currentMethod.carrier_code + '_' + currentMethod.method_code);
            }

            return this;
        },

        /**
         * Get shipping methods
         * @returns {Array}
         */
        getShippingMethods: function () {
            return this.shippingMethods;
        },

        /**
         * Select shipping method
         * @param {Object} method
         */
        selectMethod: function (method) {
            console.log('Selecting shipping method:', method);
            
            this.selectedMethod(method.method_code);
            
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

            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(method.method_code);
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
            return classes;
        }
    });
});
