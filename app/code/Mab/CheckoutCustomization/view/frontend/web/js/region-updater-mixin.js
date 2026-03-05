/**
 * Algeria Wilaya Region Updater - Enhanced
 * Properly loads and displays all 58 Algeria wilayas in checkout
 * Removes postcode requirement for Algeria
 */
define([
    'jquery',
    'mage/template',
    'underscore',
    'Magento_Directory/js/region-collection'
], function ($, mageTemplate, _, regionCollection) {
    'use strict';

    return function (targetWidget) {
        $.widget('mage.directoryRegionUpdater', targetWidget, {

            /**
             * Initialize with Algeria wilaya data
             */
            _create: function () {
                // Load Algeria wilayas if not already loaded
                if (!this.options.regionJson['DZ'] || Object.keys(this.options.regionJson['DZ']).length === 0) {
                    this.loadAlgeriaWilayas();
                }
                
                this._super();
            },

            /**
             * Load Algeria wilayas from region collection
             */
            loadAlgeriaWilayas: function () {
                var self = this;
                
                // Algeria wilayas data (58 wilayas)
                var algeriaWilayas = {
                    '1': { code: '1', id: '1', name: 'Adrar' },
                    '2': { code: '2', id: '2', name: 'Chlef' },
                    '3': { code: '3', id: '3', name: 'Laghouat' },
                    '4': { code: '4', id: '4', name: 'Oum El Bouaghi' },
                    '5': { code: '5', id: '5', name: 'Batna' },
                    '6': { code: '6', id: '6', name: 'Béjaïa' },
                    '7': { code: '7', id: '7', name: 'Biskra' },
                    '8': { code: '8', id: '8', name: 'Béchar' },
                    '9': { code: '9', id: '9', name: 'Blida' },
                    '10': { code: '10', id: '10', name: 'Bouira' },
                    '11': { code: '11', id: '11', name: 'Tamanrasset' },
                    '12': { code: '12', id: '12', name: 'Tébessa' },
                    '13': { code: '13', id: '13', name: 'Tlemcen' },
                    '14': { code: '14', id: '14', name: 'Tiaret' },
                    '15': { code: '15', id: '15', name: 'Tizi Ouzou' },
                    '16': { code: '16', id: '16', name: 'Alger' },
                    '17': { code: '17', id: '17', name: 'Djelfa' },
                    '18': { code: '18', id: '18', name: 'Jijel' },
                    '19': { code: '19', id: '19', name: 'Sétif' },
                    '20': { code: '20', id: '20', name: 'Saïda' },
                    '21': { code: '21', id: '21', name: 'Skikda' },
                    '22': { code: '22', id: '22', name: 'Sidi Bel Abbès' },
                    '23': { code: '23', id: '23', name: 'Annaba' },
                    '24': { code: '24', id: '24', name: 'Guelma' },
                    '25': { code: '25', id: '25', name: 'Constantine' },
                    '26': { code: '26', id: '26', name: 'Médéa' },
                    '27': { code: '27', id: '27', name: 'Mostaganem' },
                    '28': { code: '28', id: '28', name: 'M\'Sila' },
                    '29': { code: '29', id: '29', name: 'Mascara' },
                    '30': { code: '30', id: '30', name: 'Ouargla' },
                    '31': { code: '31', id: '31', name: 'Oran' },
                    '32': { code: '32', id: '32', name: 'El Bayadh' },
                    '33': { code: '33', id: '33', name: 'Illizi' },
                    '34': { code: '34', id: '34', name: 'Bordj Bou Arreridj' },
                    '35': { code: '35', id: '35', name: 'Boumerdès' },
                    '36': { code: '36', id: '36', name: 'El Tarf' },
                    '37': { code: '37', id: '37', name: 'Tindouf' },
                    '38': { code: '38', id: '38', name: 'Tissemsilt' },
                    '39': { code: '39', id: '39', name: 'El Oued' },
                    '40': { code: '40', id: '40', name: 'Khenchela' },
                    '41': { code: '41', id: '41', name: 'Souk Ahras' },
                    '42': { code: '42', id: '42', name: 'Tipaza' },
                    '43': { code: '43', id: '43', name: 'Mila' },
                    '44': { code: '44', id: '44', name: 'Aïn Defla' },
                    '45': { code: '45', id: '45', name: 'Naâma' },
                    '46': { code: '46', id: '46', name: 'Aïn Témouchent' },
                    '47': { code: '47', id: '47', name: 'Ghardaïa' },
                    '48': { code: '48', id: '48', name: 'Relizane' },
                    '49': { code: '49', id: '49', name: 'Timimoun' },
                    '50': { code: '50', id: '50', name: 'Bordj Badji Mokhtar' },
                    '51': { code: '51', id: '51', name: 'Ouled Djellal' },
                    '52': { code: '52', id: '52', name: 'Béni Abbès' },
                    '53': { code: '53', id: '53', name: 'In Salah' },
                    '54': { code: '54', id: '54', name: 'In Guezzam' },
                    '55': { code: '55', id: '55', name: 'Touggourt' },
                    '56': { code: '56', id: '56', name: 'Djanet' },
                    '57': { code: '57', id: '57', name: 'El M\'Ghair' },
                    '58': { code: '58', id: '58', name: 'El Menia' }
                };
                
                this.options.regionJson['DZ'] = algeriaWilayas;
            },

            /**
             * Override _updateRegion to handle Algeria (DZ) wilayas properly
             * and hide postcode for Algeria
             */
            _updateRegion: function (country) {
                var regionList = $(this.options.regionListId),
                    regionInput = $(this.options.regionInputId),
                    postcode = $(this.options.postcodeId),
                    postcodeField = postcode.closest('.field'),
                    label = regionList.parent().siblings('label'),
                    container = regionList.parents('div.field'),
                    regionsEntries,
                    regionId,
                    regionData;

                this._clearError();
                this._checkRegionRequired(country);

                // Special handling for Algeria (DZ) - ensure wilayas are shown
                if (country === 'DZ' && this.options.regionJson['DZ']) {
                    this._removeSelectOptions(regionList);
                    regionsEntries = _.pairs(this.options.regionJson['DZ']);
                    
                    // Sort wilayas by name for better UX
                    regionsEntries.sort(function (a, b) {
                        return a[1].name.localeCompare(b[1].name);
                    });

                    $.each(regionsEntries, $.proxy(function (key, value) {
                        regionData = value[1];
                        regionId = regionData.id;
                        this._renderSelectOption(regionList, regionId.toString(), regionData);
                    }, this));

                    // Add default "Sélectionnez une wilaya" option at the beginning
                    regionList.prepend('<option value="">Sélectionnez une wilaya</option>');

                    if (this.currentRegionOption) {
                        regionList.val(this.currentRegionOption);
                    }

                    if (this.setOption) {
                        regionList.find('option').filter(function () {
                            return this.text === regionInput.val();
                        }).attr('selected', true);
                    }

                    // Wilaya is required for Algeria
                    regionList.addClass('required-entry').removeAttr('disabled');
                    container.addClass('required').show();
                    regionList.show();
                    regionInput.hide();
                    label.attr('for', regionList.attr('id'));
                    
                    // Hide postcode field for Algeria
                    postcodeField.hide();
                    postcode.removeClass('required-entry').removeAttr('required');
                    postcodeField.removeClass('required');
                    
                    // Trigger custom event for wilaya change
                    regionList.trigger('wilaya:updated', [country]);
                }
                // Handle other countries with regions
                else if (this.options.regionJson[country]) {
                    this._removeSelectOptions(regionList);
                    regionsEntries = _.pairs(this.options.regionJson[country]);
                    $.each(regionsEntries, $.proxy(function (key, value) {
                        regionData = value[1];
                        regionId = regionData.id;
                        this._renderSelectOption(regionList, regionId.toString(), regionData);
                    }, this));

                    if (this.currentRegionOption) {
                        regionList.val(this.currentRegionOption);
                    }

                    if (this.setOption) {
                        regionList.find('option').filter(function () {
                            return this.text === regionInput.val();
                        }).attr('selected', true);
                    }

                    if (this.options.isRegionRequired) {
                        regionList.addClass('required-entry').removeAttr('disabled');
                        container.addClass('required').show();
                    } else {
                        regionList.removeClass('required-entry validate-select');
                        container.removeClass('required');

                        if (!this.options.optionalRegionAllowed) {
                            regionList.hide();
                            container.hide();
                        } else {
                            regionList.removeAttr('disabled').show();
                        }
                    }

                    regionList.show();
                    regionInput.hide();
                    label.attr('for', regionList.attr('id'));
                    
                    // Show postcode for non-Algeria countries
                    postcodeField.show();
                } else {
                    // No regions available - use text input
                    this._removeSelectOptions(regionList);

                    if (this.options.isRegionRequired) {
                        regionInput.addClass('required-entry').removeAttr('disabled');
                        container.addClass('required').show();
                    } else {
                        if (!this.options.optionalRegionAllowed) {
                            regionInput.attr('disabled', 'disabled');
                            container.hide();
                        }
                        container.removeClass('required');
                        regionInput.removeClass('required-entry');
                    }

                    regionList.removeClass('required-entry').prop('disabled', 'disabled').hide();
                    regionInput.show();
                    label.attr('for', regionInput.attr('id'));
                }

                // If country is in optionalzip list, make postcode input not required
                if (this.options.isZipRequired) {
                    $.inArray(country, this.options.countriesWithOptionalZip) >= 0 ?
                        postcode.removeClass('required-entry').closest('.field').removeClass('required') :
                        postcode.addClass('required-entry').closest('.field').addClass('required');
                }

                // Add defaultvalue attribute to state/province select element
                regionList.attr('defaultvalue', this.options.defaultRegion);
                this.options.form.find('[type="submit"]').removeAttr('disabled').show();
            },

            /**
             * Enhanced region required check for Algeria
             */
            _checkRegionRequired: function (country) {
                var self = this;

                // Algeria requires wilaya selection
                if (country === 'DZ') {
                    this.options.isRegionRequired = true;
                    return;
                }

                this.options.isRegionRequired = false;
                $.each(this.options.regionJson.config['regions_required'], function (index, elem) {
                    if (elem === country) {
                        self.options.isRegionRequired = true;
                    }
                });
            }
        });

        return $.mage.directoryRegionUpdater;
    };
});
