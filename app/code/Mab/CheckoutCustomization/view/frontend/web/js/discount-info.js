define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    return function (config) {
        // Only run if a coupon is applied
        if (!config.hasCoupon) {
            return;
        }

        // Removed discount info functionality since we're hiding the discount details section
        // The coupon is still applied and functional, we're just not displaying the extra details
        
        // We can keep minimal functionality for updating the coupon code display if needed
        function getElementById(id) {
            try {
                return document.getElementById(id);
            } catch (e) {
                console.warn('Error getting element by ID:', id, e);
                return null;
            }
        }

        function setTextContent(elementId, text) {
            try {
                var element = getElementById(elementId);
                if (element) {
                    // Only update if the text content has actually changed
                    if (element.textContent !== text) {
                        element.textContent = text;
                    }
                } else {
                    console.warn('Element not found:', elementId);
                }
            } catch (e) {
                console.warn('Error setting text content for element:', elementId, e);
            }
        }

        function updateCouponCode() {
            try {
                var cart = customerData.get('cart')();
                
                if (cart && cart.totalsData) {
                    // Update the coupon code if it exists in extension_attributes
                    if (cart.totalsData.extension_attributes && cart.totalsData.extension_attributes.coupon_label) {
                        setTextContent('applied-coupon-code', cart.totalsData.extension_attributes.coupon_label);
                    } else if (cart.totalsData.coupon_code) {
                        // Fallback to coupon_code if coupon_label is not available
                        setTextContent('applied-coupon-code', cart.totalsData.coupon_code);
                    }
                }
            } catch (e) {
                console.error('Error updating coupon code:', e);
            }
        }

        // Initial update of coupon code only
        updateCouponCode();

        // Listen for cart updates to update coupon code only
        try {
            customerData.get('cart').subscribe(function (updatedCart) {
                updateCouponCode();
            });
        } catch (e) {
            console.error('Error subscribing to cart updates:', e);
        }
    };
});