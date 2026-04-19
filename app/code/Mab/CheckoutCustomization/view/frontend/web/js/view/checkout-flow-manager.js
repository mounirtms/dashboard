/**
 * Checkout Flow Manager
 * Ensures smooth transitions between checkout steps
 * Handles validation, button visibility, and step navigation
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/checkout-data',
    'mage/translate'
], function ($, ko, Component, quote, stepNavigator, checkoutData, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/checkout-flow-manager'
        },

        initialize: function () {
            var self = this;
            
            self._super();
            
            console.log('🚀 [Checkout Flow] Manager initializing...');
            
            // Monitor step changes
            stepNavigator.steps.subscribe(function (steps) {
                console.log('📋 [Checkout Flow] Steps updated:', steps.length);
                self.ensureStepButtonsVisible();
            });
            
            // Monitor active step
            stepNavigator.next.subscribe(function () {
                console.log('➡️ [Checkout Flow] Moving to next step');
                self.ensureStepButtonsVisible();
            });
            
            // Monitor shipping method selection
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    console.log('✅ [Checkout Flow] Shipping method selected:', method.carrier_code + '_' + method.method_code);
                    self.forceShippingNextButton();
                }
            });
            
            // Monitor payment method selection
            quote.paymentMethod.subscribe(function (method) {
                if (method) {
                    console.log('✅ [Checkout Flow] Payment method selected:', method.method);
                    self.forcePlaceOrderButton();
                }
            });
            
            // Initial check after short delay
            setTimeout(function () {
                self.ensureStepButtonsVisible();
            }, 1000);
            
            // Periodic check (every 2 seconds)
            setInterval(function () {
                self.ensureStepButtonsVisible();
            }, 2000);
            
            console.log('✅ [Checkout Flow] Manager initialized');
            
            return self;
        },

        /**
         * Ensure all step buttons are visible
         */
        ensureStepButtonsVisible: function () {
            var self = this;
            
            // Force shipping Next button
            self.forceShippingNextButton();
            
            // Force payment Place Order button
            self.forcePlaceOrderButton();
        },

        /**
         * Force shipping step Next button visibility
         */
        forceShippingNextButton: function () {
            var buttonSelectors = [
                '.opc-wrapper #checkout-step-shipping .actions-toolbar',
                '.opc-wrapper .shipping-address-items .actions-toolbar',
                '#shipping-method-buttons-container',
                '.checkout-shipping-method .actions-toolbar'
            ];
            
            buttonSelectors.forEach(function (selector) {
                $(selector).each(function () {
                    $(this).css({
                        'display': 'flex',
                        'visibility': 'visible',
                        'opacity': '1'
                    }).show();
                });
            });
            
            // Force buttons themselves
            $('.opc-wrapper button.action.continue.primary').each(function () {
                $(this).css({
                    'display': 'inline-block',
                    'visibility': 'visible',
                    'opacity': '1'
                }).show();
            });
        },

        /**
         * Force payment step Place Order button visibility
         */
        forcePlaceOrderButton: function () {
            var buttonContainerSelectors = [
                '.payment-method .actions-toolbar',
                '#checkout-payment-method-load .actions-toolbar',
                '.checkout-agreements-block + .actions-toolbar',
                '.opc-wrapper .payment-method .actions-toolbar'
            ];
            
            buttonContainerSelectors.forEach(function (selector) {
                $(selector).each(function () {
                    $(this).css({
                        'display': 'flex',
                        'visibility': 'visible',
                        'opacity': '1',
                        'min-height': '60px'
                    }).show();
                });
            });
            
            // Force Place Order button
            var buttonSelectors = [
                'button.action.primary.checkout',
                '.action.primary.checkout',
                'button[type="submit"].checkout',
                '.payment-method button.action.primary.checkout'
            ];
            
            buttonSelectors.forEach(function (selector) {
                $(selector).each(function () {
                    $(this).css({
                        'display': 'inline-block',
                        'visibility': 'visible',
                        'opacity': '1',
                        'pointer-events': 'auto'
                    }).show();
                });
            });
            
            console.log('✅ [Checkout Flow] Place Order button forced visible');
        }
    });
});
