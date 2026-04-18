/**
 * Algeria Wilaya Region Updater Mixin
 * Ensures the region dropdown displays for Algeria (DZ)
 * Uses Magento's built-in regionJson data (correct DB IDs 859-916)
 * Hides postcode for Algeria
 */
define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    return function (targetWidget) {
        $.widget('mage.directoryRegionUpdater', targetWidget, {

            /**
             * Initialize - force update for DZ on load
             */
            _create: function () {
                this._super();

                // Force update on initialization if country is already DZ
                var countrySelect = $(this.options.countryListId);
                if (countrySelect.val() === 'DZ') {
                    setTimeout($.proxy(function () {
                        this._updateRegion('DZ');
                    }, this), 200);
                }
            },

            /**
             * Override _updateRegion to handle Algeria (DZ) properly:
             * - Always show the SELECT dropdown (never text input)
             * - Hide postcode field
             * - Add French placeholder text
             */
            _updateRegion: function (country) {
                console.log('🔧 [Region Updater Mixin] _updateRegion called for country:', country);
                var regionList = $(this.options.regionListId),
                    regionInput = $(this.options.regionInputId),
                    postcode = $(this.options.postcodeId),
                    postcodeField = postcode ? postcode.closest('.field') : $(),
                    label = regionList.parent().siblings('label'),
                    container = regionList.parents('div.field'),
                    regionsEntries,
                    regionId,
                    regionData;

                this._clearError();
                this._checkRegionRequired(country);

                // === ALGERIA (DZ): Force dropdown, hide postcode ===
                if (country === 'DZ' && this.options.regionJson['DZ'] &&
                    Object.keys(this.options.regionJson['DZ']).length > 0) {

                    // Clear existing options
                    this._removeSelectOptions(regionList);

                    regionsEntries = _.pairs(this.options.regionJson['DZ']);

                    // Sort wilayas alphabetically by name
                    regionsEntries.sort(function (a, b) {
                        return a[1].name.localeCompare(b[1].name, 'fr');
                    });

                    // Add wilayas to dropdown (using correct DB region IDs)
                    $.each(regionsEntries, $.proxy(function (key, value) {
                        regionData = value[1];
                        regionId = value[0]; // DB region_id (859-916)
                        this._renderSelectOption(regionList, regionId, regionData);
                    }, this));

                    // Add placeholder
                    regionList.prepend('<option value="">S\u00e9lectionnez une wilaya</option>');

                    // Restore selection if one was set
                    if (this.currentRegionOption) {
                        regionList.val(this.currentRegionOption);
                    }

                    if (this.setOption) {
                        regionList.find('option').filter(function () {
                            return this.text === regionInput.val();
                        }).attr('selected', true);
                    }

                    // Force dropdown visible, hide text input
                    regionList
                        .addClass('required-entry validate-select')
                        .removeAttr('disabled')
                        .show();
                    container.addClass('required').show();

                    regionInput
                        .hide()
                        .val('')
                        .removeAttr('required')
                        .removeClass('required-entry');

                    label.attr('for', regionList.attr('id')).show();

                    // Hide postcode for Algeria (uses wilaya/commune)
                    if (postcodeField.length) {
                        postcodeField.hide();
                        postcode.removeClass('required-entry').removeAttr('required');
                        postcodeField.removeClass('required');
                    }

                    regionList.attr('defaultvalue', this.options.defaultRegion);
                    this.options.form.find('[type="submit"]').removeAttr('disabled').show();
                    return;
                }

                // === Other countries: use default Magento behavior ===
                this._super(country);
            },

            /**
             * Algeria requires region selection
             */
            _checkRegionRequired: function (country) {
                if (country === 'DZ') {
                    this.options.isRegionRequired = true;
                    return;
                }
                // Call parent for other countries
                this._super(country);
            }
        });

        return $.mage.directoryRegionUpdater;
    };
});
