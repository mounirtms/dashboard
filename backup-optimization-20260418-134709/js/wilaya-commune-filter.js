/**
 * Mab_CheckoutCustomization - Wilaya-Commune Conditional Dropdown
 * Loads commune data and filters the city select based on the chosen wilaya.
 *
 * Usage (data-mage-init on a wilaya <select>):
 *   <select data-mage-init='{"wilayaCommuneFilter": {}}'>
 */
define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    /** Module-level commune cache shared across instances. */
    var communesCache = null,
        loading       = false,
        callbacks     = [];

    /**
     * Load communes data (async, cached).
     * @param {Function} done - callback(communesByWilaya)
     */
    function loadCommunes(done) {
        if (communesCache) {
            done(communesCache);
            return;
        }

        callbacks.push(done);

        if (loading) {
            return;
        }

        loading = true;

        $.ajax({
            url: urlBuilder.build('rest/V1/directory/communes'),
            type: 'GET',
            dataType: 'json'
        }).done(function (data) {
            communesCache = groupByWilaya(data || []);
        }).fail(function () {
            // Fallback to static JSON
            $.getJSON('/pub/media/communes.json').done(function (data) {
                communesCache = groupByWilaya(data || []);
            }).fail(function () {
                communesCache = {};
            });
        }).always(function () {
            loading = false;
            $.each(callbacks, function (_, cb) {
                cb(communesCache || {});
            });
            callbacks = [];
        });
    }

    /**
     * Group flat commune array by wilaya_id.
     * @param {Array} data
     * @returns {Object}
     */
    function groupByWilaya(data) {
        var grouped = {};

        $.each(data, function (_, commune) {
            var wid = commune.wilaya_id || commune.region_id;

            if (wid) {
                if (!grouped[wid]) {
                    grouped[wid] = [];
                }
                grouped[wid].push(commune);
            }
        });

        return grouped;
    }

    /**
     * Update the commune <select> based on the chosen wilaya.
     */
    function filterCommunes($communeSelect, communesByWilaya, wilayaId) {
        var communes = communesByWilaya[wilayaId] || [],
            html     = '<option value="">S\u00e9lectionnez une commune</option>';

        $.each(communes, function (_, c) {
            html += '<option value="' + (c.name || c.id) + '">' +
                    (c.name || c.id) + '</option>';
        });

        $communeSelect.html(html).prop('disabled', communes.length === 0);
    }

    return function (config, element) {
        var $wilayaSelect  = $(element),
            $communeSelect = $wilayaSelect.closest('fieldset, form')
                                          .find('select[name="city"], input[name="city"]');

        if (!$communeSelect.length) {
            return;
        }

        loadCommunes(function (communesByWilaya) {
            // React to wilaya changes
            $wilayaSelect.on('change', function () {
                var wilayaId = $(this).val();
                filterCommunes($communeSelect, communesByWilaya, wilayaId);
                
                // Trigger event for shipping cards to listen
                if (wilayaId) {
                    $wilayaSelect.trigger('wilaya:changed', [wilayaId]);
                    $(document).trigger('wilaya:changed', [wilayaId]);
                }
            });

            // Apply initial filter if a wilaya is already selected
            if ($wilayaSelect.val()) {
                filterCommunes($communeSelect, communesByWilaya, $wilayaSelect.val());
            }
        });
    };
});
