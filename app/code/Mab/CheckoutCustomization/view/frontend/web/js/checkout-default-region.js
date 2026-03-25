/**
 * Mab_CheckoutCustomization - Algeria Default Country & Region
 *
 * Uses Knockout uiRegistry to interact with Magento checkout UI components.
 * Sets default country to DZ and default region to Alger (874).
 * Hides fax/postcode/company fields via the KO component API.
 *
 * This works with both standard Magento checkout AND Amasty OSC.
 */
define([
    'jquery',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data'
], function ($, registry, quote, checkoutData) {
    'use strict';

    var ALGER_REGION_ID = '874',
        DEFAULT_COUNTRY = 'DZ',
        initialized = false;

    /**
     * Set country to DZ using the Knockout UI component
     */
    function setCountryDZ(countryComponent) {
        if (countryComponent && countryComponent.value() !== DEFAULT_COUNTRY) {
            countryComponent.value(DEFAULT_COUNTRY);
        }
    }

    /**
     * Set default region to Alger using the Knockout UI component.
     * The region_id component uses Knockout observables, not jQuery.
     */
    function setDefaultRegion(regionComponent) {
        if (!regionComponent) {
            return;
        }

        // Only set if no region is already selected
        if (!regionComponent.value()) {
            // Wait a bit for the options to load from the country change
            setTimeout(function () {
                var options = regionComponent.options();
                if (options && options.length > 0) {
                    // Check if Alger (874) exists in options
                    var hasAlger = options.some(function (opt) {
                        return String(opt.value) === ALGER_REGION_ID;
                    });
                    if (hasAlger) {
                        regionComponent.value(ALGER_REGION_ID);
                    }
                }
            }, 500);
        }
    }

    /**
     * Force the region dropdown container to be visible.
     * Magento's KO region component hides it via style="display:none"
     * when there are no options loaded yet.
     */
    function forceRegionVisible(regionComponent) {
        if (regionComponent) {
            regionComponent.visible(true);
        }
    }

    /**
     * Hide unnecessary fields via KO component API
     */
    function hideField(fieldName) {
        registry.get(
            'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.' + fieldName,
            function (component) {
                if (component && typeof component.visible === 'function') {
                    component.visible(false);
                }
            }
        );
    }

    /**
     * Main initialization using uiRegistry
     */
    function initDefaults() {
        if (initialized) {
            return;
        }
        initialized = true;

        var shippingPrefix = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.';

        // Set default country via KO component
        registry.get(shippingPrefix + 'country_id', function (countryComponent) {
            setCountryDZ(countryComponent);

            // After country is set, handle region
            registry.get(shippingPrefix + 'region_id', function (regionComponent) {
                forceRegionVisible(regionComponent);
                setDefaultRegion(regionComponent);
            });
        });

        // Hide fax, postcode, company via KO (backup - layout XML should handle these)
        hideField('fax');
        hideField('postcode');
        hideField('company');
        hideField('middlename');

        // Also set in checkoutData for persistence
        if (checkoutData.setShippingAddressFromData) {
            var addr = checkoutData.getShippingAddressFromData();
            if (addr && !addr.country_id) {
                addr.country_id = DEFAULT_COUNTRY;
                checkoutData.setShippingAddressFromData(addr);
            }
        }
    }

    return function () {
        // Use uiRegistry async to wait for components to initialize
        registry.async('checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.country_id')(
            function () {
                initDefaults();
            }
        );

        // Fallback: retry after a delay in case async doesn't fire
        $(document).ready(function () {
            setTimeout(function () {
                if (!initialized) {
                    initDefaults();
                }
            }, 2000);
        });
    };
});
