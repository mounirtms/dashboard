/**
 * Mab_CheckoutCustomization - Discount Mixin
 * Wraps isDisplayed() to always return false when discount is disabled.
 */
define([
    'mage/utils/wrapper',
    'Mab_CheckoutCustomization/js/model/discount-config'
], function (wrapper, discountConfig) {
    'use strict';

    return function (target) {
        if (target && typeof target.isDisplayed === 'function') {
            target.isDisplayed = wrapper.wrap(
                target.isDisplayed,
                function (/* original */) {
                    return !discountConfig.isDiscountDisabled();
                }
            );
        }

        return target;
    };
});
