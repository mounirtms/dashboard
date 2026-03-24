/**
 * Set default region to Alger and hide fax field in checkout
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote'
], function ($, quote) {
    'use strict';

    return function () {
        // Wait for checkout to load
        $(document).ready(function () {
            // Set default region to Alger (ID: 874) after a short delay
            setTimeout(function () {
                // Find region dropdown in shipping address
                var regionSelect = $('select[name="region_id"]');
                if (regionSelect.length > 0 && regionSelect.val() === '') {
                    // Try to select Alger (common IDs: 874, 16)
                    if (regionSelect.find('option[value="874"]').length > 0) {
                        regionSelect.val('874').trigger('change');
                        console.log('Default region set to Alger (874)');
                    } else if (regionSelect.find('option[value="16"]').length > 0) {
                        regionSelect.val('16').trigger('change');
                        console.log('Default region set to Alger (16)');
                    }
                }

                // Hide fax field in shipping address
                $('div[name="shippingAddress.fax"]').hide();
                $('label[for*="fax"]').hide();
                $('input[name="fax"]').closest('.field').hide();

                // Hide fax field in billing address
                $('div[name="billingAddress.fax"]').hide();
                $('input[name="billingAddress.fax"]').closest('.field').hide();

                console.log('Fax fields hidden');
            }, 500);
        });
    };
});
