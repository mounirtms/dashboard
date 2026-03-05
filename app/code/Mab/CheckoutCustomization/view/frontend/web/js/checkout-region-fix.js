/**
 * Algeria Checkout Region Fix
 * Directly hooks into Magento checkout to ensure wilayas display properly
 */
define([
    'jquery',
    'mage/url',
    'Magento_Checkout/js/model/step-navigator',
    'underscore'
], function ($, urlBuilder, stepNavigator, _) {
    'use strict';

    var communesCache = {};

    /**
     * Load communes data from API
     */
    function loadCommunesData(callback) {
        if (!_.isEmpty(communesCache)) {
            callback(communesCache);
            return;
        }

        $.ajax({
            url: urlBuilder.build('rest/V1/directory/communes'),
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                // Group by wilaya_id
                _.each(data, function (commune) {
                    var wilayaId = commune.wilaya_id;
                    if (!communesCache[wilayaId]) {
                        communesCache[wilayaId] = [];
                    }
                    communesCache[wilayaId].push(commune);
                });
                callback(communesCache);
            },
            error: function () {
                console.warn('Could not load communes data');
                callback({});
            }
        });
    }

    /**
     * Initialize wilaya-commune filtering
     */
    function initWilayaCommuneFilter() {
        // Listen for address form rendering
        $(document).on('change', 'select[name="region_id"]', function () {
            var $wilayaSelect = $(this);
            var wilayaId = $wilayaSelect.val();
            
            // Only for Algeria
            var countryId = $('select[name="country_id"]').val();
            if (countryId !== 'DZ') {
                return;
            }

            // Trigger custom event
            $wilayaSelect.trigger('wilaya:changed', [wilayaId]);
        });

        // Initialize on checkout page load
        $(document).one('checkout-ready', function () {
            setupCommuneField();
        });
    }

    /**
     * Setup commune field in address form
     */
    function setupCommuneField() {
        loadCommunesData(function (communesData) {
            // Find region dropdowns and enhance them
            $('select[name="region_id"]').each(function () {
                var $wilayaSelect = $(this);
                var countrySelect = $wilayaSelect.closest('fieldset').find('select[name="country_id"]');
                
                // Only enhance for Algeria
                if (countrySelect.val() !== 'DZ') {
                    return;
                }

                // Add wilaya styling class
                $wilayaSelect.addClass('wilaya-select');
                
                // Update placeholder text
                if ($wilayaSelect.find('option[value=""]').length === 0) {
                    $wilayaSelect.prepend('<option value="">Sélectionnez une wilaya</option>');
                } else {
                    $wilayaSelect.find('option[value=""]').text('Sélectionnez une wilaya');
                }
            });
        });
    }

    /**
     * Enhanced region updater for Algeria
     */
    function enhanceAlgeriaRegionDropdown() {
        // Override the region updater initialization
        require(['Magento_Directory/js/region-updater'], function (regionUpdater) {
            var originalUpdate = regionUpdater.prototype._updateRegion;
            
            regionUpdater.prototype._updateRegion = function (country) {
                if (country === 'DZ') {
                    // Force region dropdown to show for Algeria
                    this.options.isRegionRequired = true;
                }
                
                originalUpdate.call(this, country);
                
                // Post-update enhancement for Algeria
                if (country === 'DZ') {
                    var $regionList = $(this.options.regionListId);
                    
                    // Ensure "Sélectionnez une wilaya" placeholder exists
                    if ($regionList.find('option[value=""]').length === 0) {
                        $regionList.prepend('<option value="">Sélectionnez une wilaya</option>');
                    } else {
                        $regionList.find('option[value=""]').text('Sélectionnez une wilaya');
                    }
                    
                    // Add custom class for styling
                    $regionList.addClass('wilaya-select');
                    
                    // Trigger event for commune filtering
                    $regionList.trigger('wilaya:updated');
                }
            };
        });
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function () {
        // Check if we're on checkout page
        if ($('.checkout-index-index').length > 0) {
            initWilayaCommuneFilter();
            enhanceAlgeriaRegionDropdown();
            
            // Also initialize when checkout loads via requirejs
            require(['Magento_Checkout/js/action/get-totals'], function () {
                setupCommuneField();
            });
        }
    });

    // Also initialize via Magento's checkout load
    define(['Magento_Checkout/js/model/quote'], function (quote) {
        quote.shippingAddress.subscribe(function (address) {
            if (address && address.countryId === 'DZ') {
                setTimeout(function () {
                    setupCommuneField();
                }, 100);
            }
        });
    });
});
