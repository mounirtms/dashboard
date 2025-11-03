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
            this.customField = ko.observable('');
            this.successMessage = ko.observable('');
            this.errorMessage = ko.observable('');
            return this;
        },
        submitForm: function () {
            var self = this;
            self.successMessage('');
            self.errorMessage('');
            // TODO: Implement AJAX call to save custom field
            if (self.customField()) {
                self.successMessage($t('Custom field submitted: ') + self.customField());
            } else {
                self.errorMessage($t('Please enter a value.'));
            }
        }
    });
});
