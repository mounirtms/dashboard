/**
 * Algeria Checkout Region Fix
 * Enhances checkout to properly display wilayas and commune fields
 */
define([
    'jquery',
    'mage/url',
    'underscore'
], function ($, urlBuilder, _) {
    'use strict';

    var communesCache = {};

    /**
     * Load communes data from API
     */
    function loadCommunesData(callback) {
        if (!_.isEmpty(communesCache)) {
            if (typeof callback === 'function') {
                callback(communesCache);
            }
            return;
        }

        $.ajax({
            url: urlBuilder.build('rest/V1/directory/communes'),
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data && _.isArray(data)) {
                    _.each(data, function (commune) {
                        var wilayaId = commune.wilaya_id;
                        if (!communesCache[wilayaId]) {
                            communesCache[wilayaId] = [];
                        }
                        communesCache[wilayaId].push(commune);
                    });
                }
                if (typeof callback === 'function') {
                    callback(communesCache);
                }
            },
            error: function () {
                console.warn('[MabCheckout] Could not load communes data');
                if (typeof callback === 'function') {
                    callback({});
                }
            }
        });
    }

    /**
     * Setup commune field placeholders on region dropdowns
     */
    function setupCommuneField() {
        $('select[name="region_id"]').each(function () {
            var $wilayaSelect = $(this);
            var countrySelect = $wilayaSelect.closest('fieldset').find('select[name="country_id"]');

            // Only enhance for Algeria
            if (countrySelect.length && countrySelect.val() !== 'DZ') {
                return;
            }

            // Add wilaya styling class
            $wilayaSelect.addClass('wilaya-select');

            // Update placeholder text
            if ($wilayaSelect.find('option[value=""]').length === 0) {
                $wilayaSelect.prepend('<option value="">S\u00e9lectionnez une wilaya</option>');
            } else {
                $wilayaSelect.find('option[value=""]').text('S\u00e9lectionnez une wilaya');
            }
        });
    }

    /**
     * Initialize wilaya-commune filtering
     */
    function initWilayaCommuneFilter() {
        $(document).on('change', 'select[name="region_id"]', function () {
            var $wilayaSelect = $(this);
            var wilayaId = $wilayaSelect.val();
            var countryId = $('select[name="country_id"]').val();

            if (countryId !== 'DZ') {
                return;
            }

            $wilayaSelect.trigger('wilaya:changed', [wilayaId]);
        });
    }

    return function () {
        $(document).ready(function () {
            if ($('.checkout-index-index, .checkout-cart-index').length > 0) {
                initWilayaCommuneFilter();
                loadCommunesData(function () {
                    setupCommuneField();
                });
            }
        });
    };
});
