/**
 * Safe Grand Total Mixin
 * Replaces problematic Amasty grand-total mixin with safe null-checking version
 */
define([
    'jquery',
    'ko',
    'uiComponent'
], function ($, ko, Component) {
    'use strict';

    return function (target) {
        return target.extend({
            /**
             * Safe getValue with null checking
             * @returns {*}
             */
            getValue: function () {
                try {
                    // Get totals safely
                    var totals = this.totals ? this.totals() : null;
                    
                    if (!totals) {
                        console.warn('[SafeGrandTotal] Totals not available yet');
                        return '0.00';
                    }
                    
                    // Check if grand_total exists
                    if (!totals.grand_total) {
                        console.warn('[SafeGrandTotal] grand_total not found in totals');
                        return totals.base_grand_total || '0.00';
                    }
                    
                    // Return value safely
                    return totals.grand_total;
                    
                } catch (e) {
                    console.error('[SafeGrandTotal] Error in getValue:', e);
                    return '0.00';
                }
            },

            /**
             * Check if tax is displayed in grand total
             * @returns {Boolean}
             */
            isTaxDisplayedInGrandTotal: function () {
                try {
                    var displayConfig = window.checkoutConfig ? window.checkoutConfig.displayTaxInGrandTotal : false;
                    return displayConfig === true;
                } catch (e) {
                    console.error('[SafeGrandTotal] Error checking tax display:', e);
                    return false;
                }
            },

            /**
             * Check if total should be displayed
             * @returns {Boolean}
             */
            isDisplayed: function () {
                try {
                    var totals = this.totals ? this.totals() : null;
                    return !!(totals && (totals.grand_total || totals.base_grand_total));
                } catch (e) {
                    console.error('[SafeGrandTotal] Error in isDisplayed:', e);
                    return false;
                }
            }
        });
    };
});
