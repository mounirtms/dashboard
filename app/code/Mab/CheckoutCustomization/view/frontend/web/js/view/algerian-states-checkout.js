/**
 * Algerian States Checkout Integration
 * Integrates Algerian wilayas and communes with Magento checkout
 * Provides dependent dropdowns and dynamic address handling
 * 
 * Version: 2.0.0 (Enhanced with security and error handling)
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Mab_CheckoutCustomization/js/algerian-states-loader',
    'Mab_CheckoutCustomization/js/utils/security-helper',
    'Mab_CheckoutCustomization/js/utils/error-handler',
    'Mab_CheckoutCustomization/js/utils/performance-monitor',
    'Mab_CheckoutCustomization/js/utils/region-id-mapper',
    'mage/translate'
], function ($, ko, Component, quote, algerianStates, SecurityHelper, ErrorHandler, PerfMonitor, RegionMapper, $t) {
    'use strict';

    return Component.extend({
        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            
            self._super();
            
            PerfMonitor.start('algerian-states-init');
            
            try {
                SecurityHelper.log('info', '🇩🇿 [Algerian States Integration] Initializing...');
                
                // Observable properties
                self.selectedWilaya = ko.observable(null);
                self.selectedCommune = ko.observable(null);
                self.availableCommunes = ko.observableArray([]);
                self.deliveryInfo = ko.observable(null);
                self.isInitialized = ko.observable(false);
                self.hasError = ko.observable(false);
            
                // Log statistics
                var stats = algerianStates.getStats();
                SecurityHelper.log('info', '📊 [Algerian States] Statistics:', stats);
                
                // Wait for DOM ready
                $(document).ready(function() {
                    self.initializeSelectors();
                });
                
                PerfMonitor.end('algerian-states-init');
                self.isInitialized(true);
                
            } catch (error) {
                ErrorHandler.handleError('algerian-states', 'initialize', error);
                self.hasError(true);
            }
            
            return self;
        },

        /**
         * Initialize selectors after DOM is ready
         */
        initializeSelectors: function() {
            var self = this;
            
            console.log('🔧 [Algerian States] Setting up selectors...');
            
            // Find region/wilaya select
            var $regionSelect = $('select[name="region_id"]');
            if ($regionSelect.length === 0) {
                console.warn('⚠️ [Algerian States] Region select not found, retrying...');
                setTimeout(function() {
                    self.initializeSelectors();
                }, 500);
                return;
            }
            
            console.log('✅ [Algerian States] Found region select');
            
            // Populate wilayas
            this.populateWilayas($regionSelect);
            
            // Find or create commune select
            var $cityField = $('.field[name="shippingAddress.city"]');
            if ($cityField.length > 0) {
                this.createCommuneSelector($cityField);
            }
            
            // Set up event handlers
            this.setupEventHandlers($regionSelect);
            
            // Subscribe to quote address changes
            quote.shippingAddress.subscribe(function(address) {
                if (address && address.regionId) {
                    console.log('📍 [Algerian States] Address updated:', address.regionId);
                    self.updateFromAddress(address);
                }
            });
        },

        /**
         * Populate wilayas dropdown
         */
        populateWilayas: function($select) {
            console.log('📝 [Algerian States] Populating wilayas...');
            
            var currentValue = $select.val();
            algerianStates.populateWilayasSelect($select, currentValue);
            
            // Add custom styling
            $select.addClass('algerian-wilaya-select');
        },

        /**
         * Create commune selector
         */
        createCommuneSelector: function($cityField) {
            var self = this;
            
            console.log('📝 [Algerian States] Creating commune selector...');
            
            // Check if select already exists
            var $existingSelect = $cityField.find('select[name="city"]');
            if ($existingSelect.length > 0) {
                self.$communeSelect = $existingSelect;
                return;
            }
            
            // Replace input with select
            var $input = $cityField.find('input[name="city"]');
            if ($input.length === 0) {
                console.warn('⚠️ [Algerian States] City input not found');
                return;
            }
            
            var currentValue = $input.val();
            
            // Create select element
            var $select = $('<select>', {
                name: 'city',
                class: 'algerian-commune-select input-text',
                disabled: true
            });
            
            // Add placeholder option
            $select.append($('<option>', {
                value: '',
                text: $t('Sélectionnez d\'abord une wilaya')
            }));
            
            // Replace input with select
            $input.replaceWith($select);
            
            self.$communeSelect = $select;
            
            console.log('✅ [Algerian States] Commune selector created');
        },

        /**
         * Setup event handlers
         */
        setupEventHandlers: function($regionSelect) {
            var self = this;
            
            // Wilaya change handler
            $regionSelect.on('change', function() {
                var wilayaId = $(this).val();
                console.log('🔄 [Algerian States] Wilaya changed:', wilayaId);
                
                if (wilayaId) {
                    self.onWilayaChange(parseInt(wilayaId));
                } else {
                    self.clearCommunes();
                }
            });
            
            // Commune change handler
            if (self.$communeSelect) {
                self.$communeSelect.on('change', function() {
                    var communeId = $(this).val();
                    console.log('🔄 [Algerian States] Commune changed:', communeId);
                    
                    if (communeId) {
                        self.onCommuneChange(parseInt(communeId));
                    }
                });
            }
        },

        /**
         * Handle wilaya change
         */
        onWilayaChange: function(wilayaId) {
            var self = this;
            
            self.selectedWilaya(wilayaId);
            
            // Get wilaya info
            var wilaya = algerianStates.getWilayaById(wilayaId);
            if (!wilaya) {
                console.error('❌ [Algerian States] Wilaya not found:', wilayaId);
                return;
            }
            
            console.log('📍 [Algerian States] Selected wilaya:', wilaya.name, '(Zone', wilaya.zone + ')');
            
            // Check deliverability
            if (!algerianStates.isDeliverable(wilayaId)) {
                console.warn('⚠️ [Algerian States] Wilaya not deliverable:', wilaya.name);
                this.showDeliverabilityWarning(wilaya.name);
                return;
            }
            
            // Populate communes
            if (self.$communeSelect) {
                algerianStates.populateCommunesSelect(self.$communeSelect, wilayaId);
                
                // Get communes for this wilaya
                var communes = algerianStates.getCommunesByWilaya(wilayaId, true);
                self.availableCommunes(communes);
                
                // Update placeholder
                self.$communeSelect.find('option:first').text(
                    $t('Sélectionnez une commune (%1 disponibles)').replace('%1', communes.length)
                );
            }
            
            // Update delivery info
            this.updateDeliveryInfo(wilayaId, null);
            
            // CRITICAL: Trigger Magento shipping rate estimation
            // Update quote shipping address with the selected region
            var address = quote.shippingAddress();
            if (address) {
                // Convert custom ID (1-58) to Magento ID (859-900+)
                var magentoRegionId = RegionMapper.toMagentoId(wilayaId);
                if (!magentoRegionId) {
                    console.error('❌ [Algerian States] Failed to map region ID:', wilayaId);
                    return;
                }
                
                // Update region_id in the address with Magento ID
                address.regionId = magentoRegionId;
                address.region = wilaya.name;
                address.regionCode = wilayaId.toString().padStart(2, '0'); // Format: "01", "02", etc.
                
                // Trigger address update to recalculate shipping
                quote.shippingAddress(address);
                
                console.log('🚚 [Algerian States] Updated quote address for shipping calculation:', {
                    customId: wilayaId,
                    magentoRegionId: magentoRegionId,
                    region: address.region,
                    regionCode: address.regionCode
                });
                
                // Force estimate shipping rates
                require(['Magento_Checkout/js/action/select-shipping-address'], function(selectShippingAddress) {
                    selectShippingAddress(address);
                    console.log('✅ [Algerian States] Triggered shipping rate estimation');
                });
            }
        },

        /**
         * Handle commune change
         */
        onCommuneChange: function(communeId) {
            var self = this;
            
            self.selectedCommune(communeId);
            
            var commune = algerianStates.getCommuneById(communeId);
            if (!commune) {
                console.error('❌ [Algerian States] Commune not found:', communeId);
                return;
            }
            
            console.log('📍 [Algerian States] Selected commune:', commune.name);
            
            // Check deliverability
            if (!algerianStates.isDeliverable(commune.wilaya_id, communeId)) {
                console.warn('⚠️ [Algerian States] Commune not deliverable:', commune.name);
                this.showDeliverabilityWarning(commune.name);
                return;
            }
            
            // Update delivery info
            this.updateDeliveryInfo(commune.wilaya_id, communeId);
            
            // Update city input value (hidden, for form submission)
            $('input[name="city"]').val(commune.name);
        },

        /**
         * Clear communes dropdown
         */
        clearCommunes: function() {
            if (this.$communeSelect) {
                this.$communeSelect
                    .find('option:not(:first)')
                    .remove();
                    
                this.$communeSelect
                    .prop('disabled', true)
                    .find('option:first')
                    .text($t('Sélectionnez d\'abord une wilaya'));
            }
            
            this.availableCommunes([]);
            this.selectedCommune(null);
            this.deliveryInfo(null);
        },

        /**
         * Update delivery information display
         */
        updateDeliveryInfo: function(wilayaId, communeId) {
            var addressParts = algerianStates.getAddressParts(wilayaId, communeId);
            
            var info = {
                wilaya: addressParts.wilaya,
                commune: addressParts.commune,
                zone: addressParts.zone,
                zoneName: this.getZoneName(addressParts.zone),
                deliverable: addressParts.deliverable,
                stopDesk: addressParts.stopDesk,
                deliveryDays: addressParts.deliveryTime.parcel,
                paymentDays: addressParts.deliveryTime.payment
            };
            
            this.deliveryInfo(info);
            
            console.log('📦 [Algerian States] Delivery info updated:', info);
            
            // Show delivery info UI
            this.displayDeliveryInfo(info);
        },

        /**
         * Display delivery information - Compact inline layout
         */
        displayDeliveryInfo: function(info) {
            // Find or create info container
            var $container = $('.algerian-delivery-info');
            
            if ($container.length === 0) {
                $container = $('<div>', {
                    class: 'algerian-delivery-info'
                }).insertAfter('.field[name="shippingAddress.city"]');
            }
            
            // Build compact HTML using safe methods
            $container.empty();
            
            var $card = SecurityHelper.createSafeElement('div', {class: 'delivery-info-card'});
            
            // Compact inline layout - Zone info
            var $zoneRow = SecurityHelper.createSafeElement('span', {class: 'info-row'});
            $zoneRow.append(SecurityHelper.createSafeElement('span', {class: 'info-label'}, 'Zone:'));
            $zoneRow.append(SecurityHelper.createSafeElement('span', {class: 'info-value zone-' + info.zone}, 'Zone ' + info.zone));
            $card.append($zoneRow);
            
            // Delivery days inline
            if (info.commune && info.deliveryDays) {
                var $deliveryRow = SecurityHelper.createSafeElement('span', {class: 'info-row'});
                $deliveryRow.append(SecurityHelper.createSafeElement('span', {class: 'info-label'}, 'Délai:'));
                $deliveryRow.append(SecurityHelper.createSafeElement('span', {class: 'info-value'}, info.deliveryDays + 'j'));
                $card.append($deliveryRow);
            }
            
            // Stop desk inline (if available)
            if (info.stopDesk) {
                var $stopDeskRow = SecurityHelper.createSafeElement('span', {class: 'info-row highlight'});
                $stopDeskRow.append(SecurityHelper.createSafeElement('span', {class: 'info-icon'}, '📍'));
                $stopDeskRow.append(SecurityHelper.createSafeElement('span', {class: 'info-text'}, 'Point relais'));
                $card.append($stopDeskRow);
            }
            
            $container.append($card);
            
            // Shipping cards component handles its own visibility based on rates
            SecurityHelper.log('info', '🚚 [Algerian States] Region selected, waiting for shipping rates');
        },

        /**
         * Show deliverability warning
         */
        showDeliverabilityWarning: function(locationName) {
            var message = $t('Attention: %1 n\'est actuellement pas desservi pour les livraisons.')
                .replace('%1', locationName);
            
            // Find or create warning container
            var $warning = $('.algerian-deliverability-warning');
            
            if ($warning.length === 0) {
                $warning = $('<div>', {
                    class: 'algerian-deliverability-warning message-warning'
                }).insertAfter('.field[name="shippingAddress.city"]');
            }
            
            // Use safe element creation
            $warning.empty();
            $warning.append(
                SecurityHelper.createSafeElement('span', {class: 'warning-icon'}, '⚠️')
            ).append(
                SecurityHelper.createSafeElement('span', {}, message)
            ).show();
            
            setTimeout(function() {
                $warning.fadeOut();
            }, 5000);
        },

        /**
         * Update from quote address
         */
        updateFromAddress: function(address) {
            if (address.regionId) {
                this.selectedWilaya(address.regionId);
                
                if (this.$communeSelect && address.city) {
                    // Try to find commune by name
                    var communes = algerianStates.getCommunesByWilaya(address.regionId, true);
                    var commune = communes.find(function(c) {
                        return c.name.toLowerCase() === address.city.toLowerCase();
                    });
                    
                    if (commune) {
                        this.selectedCommune(commune.id);
                        this.$communeSelect.val(commune.id);
                    }
                }
            }
        },

        /**
         * Get zone name
         */
        getZoneName: function(zone) {
            var names = {
                1: $t('Zone 1 - Centre (Alger, Blida, Boumerdès)'),
                2: $t('Zone 2 - Nord'),
                3: $t('Zone 3 - Hauts Plateaux'),
                4: $t('Zone 4 - Sud')
            };
            
            return names[zone] || $t('Zone inconnue');
        }
    });
});
