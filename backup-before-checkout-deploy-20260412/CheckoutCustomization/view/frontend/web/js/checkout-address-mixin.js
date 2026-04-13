/**
 * Mab_CheckoutCustomization - Checkout Address Mixin
 * Extends checkout address form to support wilaya-commune filtering.
 * Loads commune data and updates the city field when region (wilaya) changes.
 *
 * NOTE: This mixin is NOT currently registered in requirejs-config.
 * It is kept as a reference for future commune-dropdown integration.
 */
define([
    'jquery',
    'mage/url',
    'underscore'
], function ($, urlBuilder, _) {
    'use strict';

    var communesCache = null;

    /**
     * Load and cache communes grouped by wilaya_id.
     * @param {Function} callback - receives {wilayaId: [communes]}
     */
    function loadCommunes(callback) {
        if (communesCache) {
            callback(communesCache);
            return;
        }

        $.ajax({
            url: urlBuilder.build('rest/V1/directory/communes'),
            type: 'GET',
            dataType: 'json'
        }).done(function (data) {
            communesCache = {};

            _.each(data || [], function (c) {
                var wid = c.wilaya_id || c.region_id;

                if (!communesCache[wid]) {
                    communesCache[wid] = [];
                }
                communesCache[wid].push(c);
            });

            callback(communesCache);
        }).fail(function () {
            communesCache = {};
            callback(communesCache);
        });
    }

    return function (target) {
        return target.extend({

            /**
             * On initialisation, subscribe to region_id changes.
             */
            initialize: function () {
                this._super();

                var self = this;

                // Observe region changes via the quote shipping address
                if (this.source && typeof this.source.get === 'function') {
                    // Will trigger when region_id observable changes
                    this.source.on('shippingAddress.region_id', function (regionId) {
                        self.onWilayaChange(regionId);
                    });
                }

                return this;
            },

            /**
             * When wilaya changes, update commune options on the city field.
             * @param {String|Number} regionId
             */
            onWilayaChange: function (regionId) {
                if (!regionId) {
                    return;
                }

                loadCommunes(function (grouped) {
                    var communes = grouped[regionId] || [],
                        options  = [{value: '', label: 'S\u00e9lectionnez une commune'}];

                    _.each(communes, function (c) {
                        options.push({
                            value: c.name || c.id,
                            label: c.name || c.id
                        });
                    });

                    $(document).trigger('commune:updated', [options]);
                });
            }
        });
    };
});
