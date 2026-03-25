/**
 * Set default country to DZ (Algeria) and default region to Alger
 * Also hides fax/postcode fields not needed for Algeria
 *
 * Region IDs reference (from directory_country_region table):
 *   Alger = 874 (DB region_id for code=16)
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote'
], function ($, quote) {
    'use strict';

    // Alger region_id in DB
    var ALGER_REGION_ID = '874';

    function forceCountryDZ() {
        $('select[name="country_id"]').each(function () {
            var $el = $(this);
            if ($el.val() !== 'DZ') {
                $el.val('DZ').trigger('change');
            }
        });
    }

    function setDefaultRegion() {
        $('select[name="region_id"]').each(function () {
            var $el = $(this);
            if (!$el.val() && $el.find('option[value="' + ALGER_REGION_ID + '"]').length) {
                $el.val(ALGER_REGION_ID).trigger('change');
            }
        });
    }

    function hideUnnecessaryFields() {
        // Hide fax fields
        $('div[name="shippingAddress.fax"]').hide();
        $('div[name="billingAddress.fax"]').hide();
        $('input[name="fax"]').closest('.field').hide();
        $('label[for*="fax"]').closest('.field').hide();

        // Hide postcode fields (Algeria uses wilaya/commune)
        $('div[name="shippingAddress.postcode"]').hide();
        $('div[name="billingAddress.postcode"]').hide();
        $('input[name="postcode"]').closest('.field').hide();
    }

    function forceBillingCountryDZ() {
        if (typeof quote.billingAddress === 'function') {
            var billing = quote.billingAddress();
            if (billing && billing.countryId !== 'DZ') {
                billing.countryId = 'DZ';
                quote.billingAddress(billing);
            }
        }
    }

    return function () {
        $(document).ready(function () {
            setTimeout(function () {
                forceCountryDZ();
                forceBillingCountryDZ();
                setDefaultRegion();
                hideUnnecessaryFields();
            }, 800);

            // Re-apply on checkout step change
            $(document).on('checkout:shipping:saved', function () {
                setTimeout(function () {
                    forceCountryDZ();
                    hideUnnecessaryFields();
                }, 400);
            });
        });
    };
});
