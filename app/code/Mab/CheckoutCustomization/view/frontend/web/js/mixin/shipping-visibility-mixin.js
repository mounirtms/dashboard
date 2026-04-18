/**
 * Mab_CheckoutCustomization - Shipping Visibility Mixin
 * Ensures shipping method cards are visible and properly initialized
 */
define([
    'jquery',
    'ko'
], function ($, ko) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * Initialize and ensure shipping cards component is visible
             */
            initialize: function () {
                this._super();
                var self = this;

                console.log('🚀 [Shipping Visibility Mixin] Initializing...');

                // Watch for when shipping methods become visible
                if (this.visible && ko.isObservable(this.visible)) {
                    this.visible.subscribe(function (isVisible) {
                        if (isVisible) {
                            console.log('👁️ [Shipping Visibility Mixin] Shipping step is now visible');
                            setTimeout(function () {
                                self.ensureCardsVisible();
                            }, 500);
                        }
                    });
                }

                // Also check after component is fully loaded
                setTimeout(function () {
                    self.ensureCardsVisible();
                }, 2000);

                return this;
            },

            /**
             * Ensure shipping cards wrapper is visible
             */
            ensureCardsVisible: function () {
                var $wrapper = $('.shipping-methods-cards-wrapper');
                var $shippingMethodSection = $('#opc-shipping_method');
                
                console.log('🔍 [Shipping Visibility Mixin] Checking cards visibility...');
                console.log('   - Wrapper found:', $wrapper.length);
                console.log('   - Shipping method section visible:', $shippingMethodSection.is(':visible'));
                
                if ($wrapper.length > 0) {
                    // Force visibility
                    $wrapper.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                    console.log('✅ [Shipping Visibility Mixin] Forced cards wrapper visible');
                    
                    // Check if cards were rendered
                    var cardCount = $wrapper.find('.shipping-card').length;
                    console.log('📊 [Shipping Visibility Mixin] Found', cardCount, 'shipping cards');
                    
                    if (cardCount === 0) {
                        console.warn('⚠️ [Shipping Visibility Mixin] No cards rendered - component may not have processed rates');
                    }
                } else {
                    console.warn('⚠️ [Shipping Visibility Mixin] Shipping cards wrapper not found in DOM');
                    console.log('   - This may indicate the component has not rendered its template');
                }
            }
        });
    };
});
