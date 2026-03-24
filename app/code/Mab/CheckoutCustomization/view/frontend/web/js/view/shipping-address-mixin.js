/**
 * Apply default region on checkout load
 */
define([
    'jquery',
    'Mab_CheckoutCustomization/js/model/set-default-region'
], function ($, setDefaultRegion) {
    'use strict';

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this._super();
                
                // Apply default region after a short delay
                // This ensures Magento's region dropdown is properly initialized
                setTimeout(function () {
                    setDefaultRegion();
                }, 200);
                
                return this;
            }
        });
    };
});
