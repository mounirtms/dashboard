/**
 * Set default region to Alger (ID 874) for Algeria
 * Works with Magento's region dropdown visibility logic
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Directory/js/model/region',
    'Magento_Directory/js/model/regions'
], function ($, quote, regionModel, regionListModel) {
    'use strict';

    return function () {
        var applied = false;

        // Watch for country changes
        quote.shippingAddress.subscribe(function (address) {
            if (!address || applied) {
                return;
            }

            if (address.countryId === 'DZ') {
                // Wait for regions to load
                setTimeout(function () {
                    var regions = regionListModel.getRegions('DZ');
                    
                    // Only set default if regions are loaded and no region is selected
                    if (regions && regions.length > 0 && !address.regionId) {
                        // Find Alger (region code 16)
                        var algerRegion = regions.find(function (region) {
                            return region.code === '16' || region.id === 874;
                        });

                        if (algerRegion) {
                            address.regionId = algerRegion.id;
                            address.region = algerRegion.name;
                            
                            // Update region model
                            regionModel.setRegionId(algerRegion.id);
                            regionModel.setRegionName(algerRegion.name);
                            
                            applied = true;
                            console.log('Default region set to Alger');
                        }
                    }
                }, 100);
            }
        });

        // Also handle billing address
        quote.billingAddress.subscribe(function (address) {
            if (!address || applied) {
                return;
            }

            if (address.countryId === 'DZ') {
                setTimeout(function () {
                    var regions = regionListModel.getRegions('DZ');
                    
                    if (regions && regions.length > 0 && !address.regionId) {
                        var algerRegion = regions.find(function (region) {
                            return region.code === '16' || region.id === 874;
                        });

                        if (algerRegion) {
                            address.regionId = algerRegion.id;
                            address.region = algerRegion.name;
                            regionModel.setRegionId(algerRegion.id);
                            regionModel.setRegionName(algerRegion.name);
                            applied = true;
                        }
                    }
                }, 100);
            }
        });
    };
});
