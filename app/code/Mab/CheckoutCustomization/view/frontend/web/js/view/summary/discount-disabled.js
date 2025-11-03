/**
 * Disabled Discount Component
 * Replaces the original discount component with a non-functional placeholder
 * that maintains the UI component structure without breaking initialization
 */
define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/summary/discount-disabled',
            displayArea: 'discount',
            customMessage: ''
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            
            // Set observables
            this.isDisplayed = ko.observable(false);
            this.visible = ko.observable(false);
            this.customMessage = ko.observable(this.customMessage);
            
            return this;
        },

        /**
         * Initialize observables
         */
        initObservable: function () {
            this._super()
                .observe([
                    'isDisplayed',
                    'visible',
                    'customMessage'
                ]);

            // Set initial state
            this.isDisplayed(false);
            this.visible(false);

            return this;
        },

        /**
         * Check if component should be displayed
         * @returns {boolean}
         */
        isDisplayed: function () {
            return false;
        },

        /**
         * Check if component is visible
         * @returns {boolean}
         */
        isVisible: function () {
            return false;
        },

        /**
         * Get template
         * @returns {string}
         */
        getTemplate: function () {
            return this.template;
        }
    });
});
