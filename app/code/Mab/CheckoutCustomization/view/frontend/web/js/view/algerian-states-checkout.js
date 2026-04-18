/**
 * Algerian States Checkout Integration
 * Integrates Algerian wilayas and communes with Magento checkout
 * Provides dependent dropdowns and dynamic address handling
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Mab_CheckoutCustomization/js/algerian-states-loader',
    'mage/translate'
], function ($, ko, Component, quote, algerianStates, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/algerian-states-selector'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            
            self._super();
            
            console.log('🇩🇿 [Algerian States Integration] Initializing...');
            
            // Observable properties
            self.selectedWilaya = ko.observable(null);
            self.selectedCommune = ko.observable(null);
            self.availableCommunes = ko.observableArray([]);
            self.deliveryInfo = ko.observable(null);
            
            // Log statistics
            var stats = algerianStates.getStats();
            console.log('📊 [Algerian States] Statistics:', stats);
            
            // Wait for DOM ready
            $(document).ready(function() {
                self.initializeSelectors();
            });
            
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
         * Display delivery information
         */
        displayDeliveryInfo: function(info) {
            // Find or create info container
            var $container = $('.algerian-delivery-info');
            
            if ($container.length === 0) {
                $container = $('<div>', {
                    class: 'algerian-delivery-info'
                }).insertAfter('.field[name="shippingAddress.city"]');
            }
            
            // Build HTML
            var html = '<div class="delivery-info-card">';
            html += '<div class="info-row">';
            html += '<span class="info-label">Zone de livraison:</span>';
            html += '<span class="info-value zone-' + info.zone + '">' + info.zoneName + '</span>';
            html += '</div>';
            
            if (info.commune && info.deliveryDays) {
                html += '<div class="info-row">';
                html += '<span class="info-label">Délai de livraison:</span>';
                html += '<span class="info-value">' + info.deliveryDays + ' jour(s)</span>';
                html += '</div>';
            }
            
            if (info.stopDesk) {
                html += '<div class="info-row highlight">';
                html += '<span class="info-icon">📍</span>';
                html += '<span class="info-text">Point relais disponible</span>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $container.html(html);
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
            
            $warning.html('<span class="warning-icon">⚠️</span> ' + message).show();
            
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
