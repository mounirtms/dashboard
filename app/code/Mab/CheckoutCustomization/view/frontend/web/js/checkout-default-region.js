/**
 * Set default country to DZ, region to Alger and hide fax/postcode fields
 * Fixes: billing address defaulting to US causing order placement failures
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote'
], function ($, quote) {
    'use strict';

    return function () {
        // Wait for checkout to load
        $(document).ready(function () {
            // Set default country + region after a short delay
            setTimeout(function () {
                // CRITICAL FIX: Force country to DZ on ALL address forms
                $('select[name="country_id"]').each(function () {
                    var $countrySelect = $(this);
                    if ($countrySelect.val() !== 'DZ') {
                        $countrySelect.val('DZ').trigger('change');
                        console.log('[MabCheckout] Country forced to DZ (was: ' + $countrySelect.val() + ')');
                    }
                });

                // Also force billing country via Knockout observables
                if (typeof quote.billingAddress === 'function') {
                    var billing = quote.billingAddress();
                    if (billing && billing.countryId !== 'DZ') {
                        billing.countryId = 'DZ';
                        quote.billingAddress(billing);
                        console.log('[MabCheckout] Billing address country forced to DZ');
                    }
                }

                // Find region dropdown in shipping address
                var regionSelect = $('select[name="region_id"]');
                if (regionSelect.length > 0 && regionSelect.val() === '') {
                    // Try to select Alger (common IDs: 874, 16)
                    if (regionSelect.find('option[value="874"]').length > 0) {
                        regionSelect.val('874').trigger('change');
                        console.log('[MabCheckout] Default region set to Alger (874)');
                    } else if (regionSelect.find('option[value="16"]').length > 0) {
                        regionSelect.val('16').trigger('change');
                        console.log('[MabCheckout] Default region set to Alger (16)');
                    }
                }

                // Hide fax field in shipping address
                $('div[name="shippingAddress.fax"]').hide();
                $('label[for*="fax"]').hide();
                $('input[name="fax"]').closest('.field').hide();

                // Hide fax field in billing address
                $('div[name="billingAddress.fax"]').hide();
                $('input[name="billingAddress.fax"]').closest('.field').hide();

                // Hide postcode field (Algeria uses wilaya/commune instead)
                $('div[name="shippingAddress.postcode"]').hide();
                $('div[name="billingAddress.postcode"]').hide();
                $('input[name="postcode"]').closest('.field').hide();

                console.log('[MabCheckout] Fax + postcode fields hidden, country=DZ enforced');
            }, 500);

            // Re-apply on step changes (shipping -> payment)
            $(document).on('checkout:shipping:saved', function () {
                setTimeout(function () {
                    $('select[name="country_id"]').each(function () {
                        if ($(this).val() !== 'DZ') {
                            $(this).val('DZ').trigger('change');
                        }
                    });
                }, 300);
            });
        });
    };
});
