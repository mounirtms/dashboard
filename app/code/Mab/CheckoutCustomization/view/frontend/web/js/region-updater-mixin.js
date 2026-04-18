/**
 * Algeria Wilaya Region Updater Mixin
 * Works with both Magento_Directory and Magento_Checkout region-updater widgets
 * Ensures the region dropdown displays for Algeria (DZ) with correct Magento region IDs (859-916)
 * Hides postcode for Algeria and adds French placeholder.
 */
define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    return function (targetWidget) {
        if (!targetWidget || !targetWidget.prototype) {
            return targetWidget;
        }

        var proto = targetWidget.prototype;

        // Store original methods
        var originalCreate = proto._create;
        var originalUpdateRegion = proto._updateRegion;
        var originalCheckRegionRequired = proto._checkRegionRequired;

        /**
         * Override _create to force DZ update on init if country pre-selected
         */
        proto._create = function () {
            // Call original _create
            if (originalCreate) {
                originalCreate.apply(this, arguments);
            }

            // Force update for Algeria on initialization if country already DZ
            var countrySelect = $(this.options.countryListId);
            if (countrySelect && countrySelect.val() === 'DZ') {
                setTimeout($.proxy(function () {
                    this._updateRegion('DZ');
                }, this), 200);
            }
        };

        /**
         * Override _updateRegion to handle Algeria properly
         */
        proto._updateRegion = function (country) {
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

            // === ALGERIA (DZ): Force dropdown, hide postcode, use Magento IDs ===
            if (country === 'DZ' && this.options.regionJson['DZ'] && Object.keys(this.options.regionJson['DZ']).length > 0) {

                this._removeSelectOptions(regionList);

                regionsEntries = _.pairs(this.options.regionJson['DZ']);

                // Sort wilayas alphabetically by French name
                regionsEntries.sort(function (a, b) {
                    return a[1].name.localeCompare(b[1].name, 'fr');
                });

                // Add each wilaya as an option (value = Magento region_id)
                $.each(regionsEntries, $.proxy(function (key, value) {
                    regionData = value[1];
                    regionId = value[0]; // Magento DB region ID (859-916)
                    this._renderSelectOption(regionList, regionId, regionData);
                }, this));

                // Placeholder
                regionList.prepend('<option value="">Sélectionnez une wilaya</option>');

                // Restore previous selection if any
                if (this.currentRegionOption) {
                    regionList.val(this.currentRegionOption);
                }

                if (this.setOption) {
                    regionList.find('option').filter(function () {
                        return this.text === regionInput.val();
                    }).attr('selected', true);
                }

                // Show select, hide text input
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

            // === Other countries: use original behavior ===
            if (originalUpdateRegion) {
                originalUpdateRegion.call(this, country);
            }
        };

        /**
         * Override _checkRegionRequired: Algeria requires region
         */
        proto._checkRegionRequired = function (country) {
            if (country === 'DZ') {
                this.options.isRegionRequired = true;
                return;
            }
            if (originalCheckRegionRequired) {
                originalCheckRegionRequired.call(this, country);
            }
        };

        return targetWidget;
    };
});
