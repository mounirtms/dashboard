/**
 * Mab_CheckoutCustomization - Custom Checkout Form Component
 * Provides an optional custom field in the checkout flow.
 */
define([
    'uiComponent',
    'ko',
    'jquery',
    'mage/translate'
], function (Component, ko, $, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/custom-checkout-form'
        },

        initialize: function () {
            this._super();
            this.customField    = ko.observable('');
            this.successMessage = ko.observable('');
            this.errorMessage   = ko.observable('');
            return this;
        },

        submitForm: function () {
            this.successMessage('');
            this.errorMessage('');

            if (this.customField()) {
                this.successMessage(
                    $t('Custom field submitted: ') + this.customField()
                );
            } else {
                this.errorMessage($t('Please enter a value.'));
            }
        }
    });
});
