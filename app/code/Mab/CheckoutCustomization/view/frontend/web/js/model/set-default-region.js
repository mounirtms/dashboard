/**
 * Set default region to Alger (ID 874) for Algeria and ensure dropdown shows
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Directory/js/model/region',
    'Magento_Directory/js/model/regions'
], function ($, quote, regionModel, regionListModel) {
    'use strict';

    return function () {
        // Watch for address changes
        quote.shippingAddress.subscribe(function (address) {
            if (address) {
                // When country changes to Algeria, reload regions
                if (address.countryId === 'DZ') {
                    // Force region dropdown to show
                    regionListModel.getRegions('DZ');
                    
                    // Set default region to Alger (ID 874) if not set
                    if (!address.regionId || address.regionId === '') {
                        address.regionId = '874';
                        address.region = 'Alger';
                        
                        // Trigger region change
                        regionModel.setRegionId('874');
                        regionModel.setRegionName('Alger');
                        
                        // Trigger change event
                        $(document).trigger('region-default-set');
                    }
                }
            }
        });

        // Also set for billing address if different
        quote.billingAddress.subscribe(function (address) {
            if (address && address.countryId === 'DZ') {
                regionListModel.getRegions('DZ');
                
                if (!address.regionId || address.regionId === '') {
                    address.regionId = '874';
                    address.region = 'Alger';
                    regionModel.setRegionId('874');
                    regionModel.setRegionName('Alger');
                }
            }
        });

        // Set immediately if address already exists
        var shippingAddress = quote.shippingAddress();
        if (shippingAddress && shippingAddress.countryId === 'DZ') {
            regionListModel.getRegions('DZ');
            
            if (!shippingAddress.regionId || shippingAddress.regionId === '') {
                shippingAddress.regionId = '874';
                shippingAddress.region = 'Alger';
                regionModel.setRegionId('874');
                regionModel.setRegionName('Alger');
            }
        }
    };
});
