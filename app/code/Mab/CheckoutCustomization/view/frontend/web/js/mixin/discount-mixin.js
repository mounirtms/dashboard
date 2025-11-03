// app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/discount-mixin.js
define([
    'mage/utils/wrapper',
    'Mab_CheckoutCustomization/js/model/discount-config'
], function (wrapper, discountConfig) {
    'use strict';

    return function (target) {
        target.isDisplayed = wrapper.wrap(target.isDisplayed, function (original) {
            return !discountConfig.isDiscountDisabled();
        });

        return target;
    };
});