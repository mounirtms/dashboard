define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals'
], function (Component, quote, priceUtils, totals) {
    'use strict';
    
    return Component.extend({
        defaults: {
            displayArea: 'after_details',
            template: 'Mab_CheckoutCustomization/summary/item_details'
        },
        
        /**
         * @param {Object} item
         * @return {*}
         */
        getRowPriceInclTax: function (item) {
            var total = totals.getTotal('grand_total');
            if (total && total.extension_attributes) {
                return priceUtils.formatPrice(item.row_total_incl_tax);
            }
            return '';
        }
    });
});
