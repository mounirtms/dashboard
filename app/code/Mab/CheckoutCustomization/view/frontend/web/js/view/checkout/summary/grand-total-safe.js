/**
 * Grand Total Component with Safe getValue
 * Extends Magento_Tax grand total to prevent errors when grand_total segment is missing.
 * Handles both cases: segment-based total and direct grand_total.
 */
define([
    'Magento_Tax/js/view/checkout/summary/grand-total',
    'Magento_Checkout/js/model/totals'
], function(GrandTotal, totals) {
    'use strict';

    return GrandTotal.extend({
        /**
         * Safely get grand total value.
         * Handles cases where the grand_total segment is not yet available.
         * @returns {String}
         */
        getValue: function() {
            var price = 0;

            if (this.totals()) {
                var segment = totals.getSegment('grand_total');
                if (segment && segment.value !== undefined) {
                    price = segment.value;
                } else if (typeof this.totals().grand_total !== 'undefined') {
                    price = this.totals().grand_total;
                } else {
                    price = 0;
                }
            }

            // Ensure price is a valid number formatted to 2 decimals
            var floatPrice = parseFloat(price);
            if (isNaN(floatPrice)) {
                floatPrice = 0;
            } else {
                floatPrice = floatPrice.toFixed(2);
            }

            return this.getFormattedPrice(floatPrice);
        }
    });
});
