/**
 * Shipping Step Validator Mixin
 * Forces the shipping step to validate when a method is selected from our cards
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote'
], function ($, quote) {
    'use strict';

    return function (targetModule) {
        console.log('🔧 [Shipping Validator] Mixin applied to:', targetModule);
        
        // Save original validateShippingInformation
        var originalValidate = targetModule.prototype.validateShippingInformation;
        
        // Override validateShippingInformation
        targetModule.prototype.validateShippingInformation = function () {
            console.log('✓ [Shipping Validator] validateShippingInformation called');
            
            // Check if shipping method is selected
            var shippingMethod = quote.shippingMethod();
            
            if (shippingMethod && shippingMethod.carrier_code && shippingMethod.method_code) {
                console.log('✓ [Shipping Validator] Shipping method is selected:', 
                    shippingMethod.carrier_code + '_' + shippingMethod.method_code);
                
                // Force the step to be valid
                this.isShippingMethodSelected = true;
                
                // Call original validation
                return originalValidate.apply(this, arguments);
            } else {
                console.warn('⚠️ [Shipping Validator] No shipping method selected');
                return false;
            }
        };
        
        // Override navigateToNextStep to be more lenient
        if (targetModule.prototype.navigateToNextStep) {
            var originalNavigate = targetModule.prototype.navigateToNextStep;
            
            targetModule.prototype.navigateToNextStep = function (stepCode) {
                console.log('➡️ [Shipping Validator] navigateToNextStep called for:', stepCode);
                
                // Check if we have a shipping method
                var shippingMethod = quote.shippingMethod();
                
                if (shippingMethod && shippingMethod.carrier_code && shippingMethod.method_code) {
                    console.log('➡️ [Shipping Validator] Shipping method OK, navigating...');
                    return originalNavigate.apply(this, arguments);
                } else {
                    console.warn('⚠️ [Shipping Validator] Cannot navigate without shipping method');
                    return false;
                }
            };
        }
        
        console.log('✅ [Shipping Validator] Mixin successfully applied');
        
        return targetModule;
    };
});
