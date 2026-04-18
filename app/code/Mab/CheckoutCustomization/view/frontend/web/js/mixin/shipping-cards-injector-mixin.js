/**
 * Mab_CheckoutCustomization - Shipping Cards Injector Mixin
 * Directly injects the shipping cards template into the DOM
 * Ensures the cards component can render even when layout system fails
 */
define([
    'jquery',
    'ko',
    'uiRegistry'
], function ($, ko, registry) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * Initialize and inject shipping cards template
             */
            initialize: function () {
                this._super();
                var self = this;

                console.log('💉 [Cards Injector] Initializing template injection...');

                // Wait for DOM to be ready
                setTimeout(function () {
                    self.injectShippingCardsTemplate();
                    self.bindCardsComponent();
                }, 1000);

                // Also inject when rates change
                if (this.rates && ko.isObservable(this.rates)) {
                    this.rates.subscribe(function (rates) {
                        if (rates && rates.length > 0) {
                            console.log('📦 [Cards Injector] Rates changed, ensuring template is injected');
                            setTimeout(function () {
                                self.injectShippingCardsTemplate();
                                self.bindCardsComponent();
                            }, 500);
                        }
                    });
                }

                return this;
            },

            /**
             * Inject the shipping cards template HTML into the shipping method section
             */
            injectShippingCardsTemplate: function () {
                var $shippingMethodSection = $('#opc-shipping_method');
                var $existingWrapper = $('.shipping-methods-cards-wrapper');

                console.log('💉 [Cards Injector] Checking injection need...');
                console.log('   - Shipping method section found:', $shippingMethodSection.length);
                console.log('   - Existing wrapper found:', $existingWrapper.length);

                // Only inject if wrapper doesn't exist yet
                if ($shippingMethodSection.length > 0 && $existingWrapper.length === 0) {
                    console.log('💉 [Cards Injector] Injecting template...');

                    // Find the insertion point - before the step-content div
                    var $stepContent = $shippingMethodSection.find('.step-content').first();
                    
                    if ($stepContent.length === 0) {
                        console.warn('⚠️ [Cards Injector] step-content not found, appending to shipping method section');
                        $stepContent = $shippingMethodSection;
                    }

                    // Inject a placeholder div for our component
                    var templateHtml = '<div class="shipping-cards-component-placeholder" data-bind="scope: \'shippingCardsComponent\'"></div>';
                    $stepContent.before(templateHtml);

                    console.log('✅ [Cards Injector] Template placeholder injected');
                } else if ($existingWrapper.length > 0) {
                    console.log('✅ [Cards Injector] Wrapper already exists, no injection needed');
                } else {
                    console.warn('⚠️ [Cards Injector] Cannot inject - shipping method section not found');
                }
            },

            /**
             * Bind the shipping cards component to the injected template
             */
            bindCardsComponent: function () {
                console.log('🔗 [Cards Injector] Attempting to bind component...');

                // Check if component is registered
                var component = registry.get('checkout.steps.shipping-step.shippingAddress.before-shipping-method-form.shipping-method-cards');
                
                if (!component) {
                    console.log('🔍 [Cards Injector] Component not found in registry, trying alternative path...');
                    component = registry.get('shippingCardsComponent');
                }

                if (component) {
                    console.log('✅ [Cards Injector] Component found:', component);
                    
                    // The component should automatically bind to its template
                    // But we can force visibility
                    setTimeout(function () {
                        var $wrapper = $('.shipping-methods-cards-wrapper');
                        if ($wrapper.length > 0) {
                            console.log('✅ [Cards Injector] Forcing wrapper visibility');
                            $wrapper.css({
                                'display': 'block',
                                'visibility': 'visible',
                                'opacity': '1'
                            });
                        }
                    }, 500);
                } else {
                    console.warn('⚠️ [Cards Injector] Shipping cards component not found in UI registry');
                    console.log('   Available components:', Object.keys(registry.get('checkout')));
                }
            }
        });
    };
});
