/**
 * Mab_CheckoutCustomization - Algeria Checkout Region Fix
 *
 * Uses both uiRegistry (KO components) and jQuery (DOM elements) to:
 * 1. Force the region_id dropdown visible for Algeria
 * 2. Update placeholder text to French "Sélectionnez une wilaya"
 * 3. Emit wilaya:changed event for commune filtering
 * 4. Ensure the region dropdown stays visible after country changes
 */
define([
    'jquery',
    'uiRegistry'
], function ($, registry) {
    'use strict';

    var shippingPrefix = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.';

    /**
     * Force the region_id component visible and update its caption
     */
    function fixRegionDropdown() {
        registry.get(shippingPrefix + 'region_id', function (regionComponent) {
            // Force visible
            regionComponent.visible(true);

            // Update caption (placeholder text) to French
            if (regionComponent.caption) {
                regionComponent.caption('S\u00e9lectionnez une wilaya');
            }

            // Subscribe to country changes to keep region visible for DZ
            registry.get(shippingPrefix + 'country_id', function (countryComponent) {
                countryComponent.value.subscribe(function (newCountry) {
                    if (newCountry === 'DZ') {
                        // Re-force visible after country change triggers region reload
                        setTimeout(function () {
                            regionComponent.visible(true);
                            if (regionComponent.caption) {
                                regionComponent.caption('S\u00e9lectionnez une wilaya');
                            }
                        }, 300);
                    }
                });
            });
        });
    }

    /**
     * Bind wilaya change event on the actual DOM select element
     * for commune filtering (jQuery-based, works on the rendered <select>)
     */
    function bindWilayaChangeEvent() {
        $(document).on('change', 'select[name="region_id"]', function () {
            var wilayaId = $(this).val(),
                $countrySelect = $(this).closest('form, fieldset').find('select[name="country_id"]');

            if ((!$countrySelect.length || $countrySelect.val() === 'DZ') && wilayaId) {
                $(this).trigger('wilaya:changed', [wilayaId]);
                $(document).trigger('wilaya:changed', [wilayaId]);
            }
        });
    }

    return function () {
        // Wait for the checkout KO components to initialize
        registry.async(shippingPrefix + 'region_id')(function () {
            fixRegionDropdown();
        });

        $(document).ready(function () {
            bindWilayaChangeEvent();

            // Fallback: force region visible via DOM if KO didn't work
            setTimeout(function () {
                var $regionDiv = $('div[name="shippingAddress.region_id"]');
                if ($regionDiv.length && $regionDiv.css('display') === 'none') {
                    $regionDiv.show();
                }
            }, 2000);
        });
    };
});
