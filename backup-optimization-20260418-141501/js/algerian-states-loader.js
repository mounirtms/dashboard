/**
 * Algerian States & Communes Loader
 * Loads and manages Algerian geographic data (58 wilayas, 1,541 communes)
 * Provides dynamic dropdown population and dependent selection
 */
define([
    'jquery',
    'ko',
    'mage/translate',
    'text!Mab_CheckoutCustomization/data/algerian-states.json'
], function ($, ko, $t, algerianDataJson) {
    'use strict';

    // Parse JSON data
    var algerianData = JSON.parse(algerianDataJson);
    var wilayas = algerianData.wilayas || [];
    var communes = algerianData.communes || [];
    
    console.log('🇩🇿 [Algerian States] Loaded data:', {
        wilayas: wilayas.length,
        communes: communes.length
    });

    return {
        /**
         * Get all wilayas
         * @param {Boolean} deliverableOnly - Filter only deliverable wilayas
         * @returns {Array}
         */
        getWilayas: function(deliverableOnly) {
            if (deliverableOnly) {
                return wilayas.filter(function(w) {
                    return w.is_deliverable === 1;
                });
            }
            return wilayas;
        },

        /**
         * Get wilaya by ID
         * @param {Number} wilayaId
         * @returns {Object|null}
         */
        getWilayaById: function(wilayaId) {
            return wilayas.find(function(w) {
                return w.id === parseInt(wilayaId);
            }) || null;
        },

        /**
         * Get wilaya by name
         * @param {String} name
         * @returns {Object|null}
         */
        getWilayaByName: function(name) {
            var normalized = name.toLowerCase().trim();
            return wilayas.find(function(w) {
                return w.name.toLowerCase() === normalized;
            }) || null;
        },

        /**
         * Get communes for a specific wilaya
         * @param {Number} wilayaId
         * @param {Boolean} deliverableOnly
         * @returns {Array}
         */
        getCommunesByWilaya: function(wilayaId, deliverableOnly) {
            var filtered = communes.filter(function(c) {
                return c.wilaya_id === parseInt(wilayaId);
            });
            
            if (deliverableOnly) {
                filtered = filtered.filter(function(c) {
                    return c.is_deliverable === 1;
                });
            }
            
            // Sort alphabetically
            return filtered.sort(function(a, b) {
                return a.name.localeCompare(b.name, 'fr');
            });
        },

        /**
         * Get commune by ID
         * @param {Number} communeId
         * @returns {Object|null}
         */
        getCommuneById: function(communeId) {
            return communes.find(function(c) {
                return c.id === parseInt(communeId);
            }) || null;
        },

        /**
         * Get delivery zone for wilaya
         * @param {Number} wilayaId
         * @returns {Number}
         */
        getDeliveryZone: function(wilayaId) {
            var wilaya = this.getWilayaById(wilayaId);
            return wilaya ? wilaya.zone : 0;
        },

        /**
         * Get delivery time for commune
         * @param {Number} communeId
         * @returns {Object} {parcel: days, payment: days}
         */
        getDeliveryTime: function(communeId) {
            var commune = this.getCommuneById(communeId);
            if (commune) {
                return {
                    parcel: commune.delivery_time_parcel || 0,
                    payment: commune.delivery_time_payment || 0
                };
            }
            return {parcel: 0, payment: 0};
        },

        /**
         * Check if commune has stop desk
         * @param {Number} communeId
         * @returns {Boolean}
         */
        hasStopDesk: function(communeId) {
            var commune = this.getCommuneById(communeId);
            return commune ? commune.has_stop_desk === 1 : false;
        },

        /**
         * Check if location is deliverable
         * @param {Number} wilayaId
         * @param {Number} communeId (optional)
         * @returns {Boolean}
         */
        isDeliverable: function(wilayaId, communeId) {
            var wilaya = this.getWilayaById(wilayaId);
            if (!wilaya || wilaya.is_deliverable !== 1) {
                return false;
            }
            
            if (communeId) {
                var commune = this.getCommuneById(communeId);
                return commune ? commune.is_deliverable === 1 : false;
            }
            
            return true;
        },

        /**
         * Search wilayas by name
         * @param {String} query
         * @param {Number} limit
         * @returns {Array}
         */
        searchWilayas: function(query, limit) {
            limit = limit || 10;
            var normalized = query.toLowerCase().trim();
            
            if (!normalized) {
                return this.getWilayas(true).slice(0, limit);
            }
            
            var results = wilayas.filter(function(w) {
                return w.name.toLowerCase().indexOf(normalized) !== -1 &&
                       w.is_deliverable === 1;
            });
            
            return results.slice(0, limit);
        },

        /**
         * Search communes by name
         * @param {String} query
         * @param {Number} wilayaId (optional) - Filter by wilaya
         * @param {Number} limit
         * @returns {Array}
         */
        searchCommunes: function(query, wilayaId, limit) {
            limit = limit || 20;
            var normalized = query.toLowerCase().trim();
            
            var filtered = communes;
            
            // Filter by wilaya if provided
            if (wilayaId) {
                filtered = filtered.filter(function(c) {
                    return c.wilaya_id === parseInt(wilayaId);
                });
            }
            
            // Filter by query
            if (normalized) {
                filtered = filtered.filter(function(c) {
                    return c.name.toLowerCase().indexOf(normalized) !== -1 &&
                           c.is_deliverable === 1;
                });
            } else {
                filtered = filtered.filter(function(c) {
                    return c.is_deliverable === 1;
                });
            }
            
            return filtered.slice(0, limit);
        },

        /**
         * Populate select element with wilayas
         * @param {jQuery} $select - jQuery select element
         * @param {Number} selectedId - Currently selected wilaya ID
         */
        populateWilayasSelect: function($select, selectedId) {
            var deliverableWilayas = this.getWilayas(true);
            
            // Clear existing options except placeholder
            $select.find('option:not(:first)').remove();
            
            // Add wilayas
            deliverableWilayas.forEach(function(wilaya) {
                var option = $('<option>')
                    .val(wilaya.id)
                    .text(wilaya.name)
                    .data('zone', wilaya.zone);
                
                if (wilaya.id === parseInt(selectedId)) {
                    option.attr('selected', 'selected');
                }
                
                $select.append(option);
            });
            
            console.log('✅ [Algerian States] Populated', deliverableWilayas.length, 'wilayas');
        },

        /**
         * Populate select element with communes for a wilaya
         * @param {jQuery} $select - jQuery select element
         * @param {Number} wilayaId - Wilaya ID
         * @param {Number} selectedId - Currently selected commune ID
         */
        populateCommunesSelect: function($select, wilayaId, selectedId) {
            var communes = this.getCommunesByWilaya(wilayaId, true);
            
            // Clear existing options except placeholder
            $select.find('option:not(:first)').remove();
            
            if (communes.length === 0) {
                $select.prop('disabled', true);
                console.warn('⚠️ [Algerian States] No communes for wilaya:', wilayaId);
                return;
            }
            
            // Enable select
            $select.prop('disabled', false);
            
            // Add communes
            communes.forEach(function(commune) {
                var label = commune.name;
                
                // Add stop desk indicator
                if (commune.has_stop_desk === 1) {
                    label += ' 📍'; // Stop desk available
                }
                
                var option = $('<option>')
                    .val(commune.id)
                    .text(label)
                    .data('commune', commune);
                
                if (commune.id === parseInt(selectedId)) {
                    option.attr('selected', 'selected');
                }
                
                $select.append(option);
            });
            
            console.log('✅ [Algerian States] Populated', communes.length, 'communes for wilaya', wilayaId);
        },

        /**
         * Get formatted address parts
         * @param {Number} wilayaId
         * @param {Number} communeId
         * @returns {Object}
         */
        getAddressParts: function(wilayaId, communeId) {
            var wilaya = this.getWilayaById(wilayaId);
            var commune = this.getCommuneById(communeId);
            
            return {
                wilaya: wilaya ? wilaya.name : '',
                commune: commune ? commune.name : '',
                zone: wilaya ? wilaya.zone : 0,
                deliverable: this.isDeliverable(wilayaId, communeId),
                stopDesk: commune ? commune.has_stop_desk === 1 : false,
                deliveryTime: this.getDeliveryTime(communeId)
            };
        },

        /**
         * Get statistics
         * @returns {Object}
         */
        getStats: function() {
            var deliverableWilayas = this.getWilayas(true).length;
            var deliverableCommunes = communes.filter(function(c) {
                return c.is_deliverable === 1;
            }).length;
            var stopDesks = communes.filter(function(c) {
                return c.has_stop_desk === 1;
            }).length;
            
            return {
                totalWilayas: wilayas.length,
                deliverableWilayas: deliverableWilayas,
                totalCommunes: communes.length,
                deliverableCommunes: deliverableCommunes,
                stopDesks: stopDesks,
                zones: {
                    zone1: wilayas.filter(function(w) { return w.zone === 1; }).length,
                    zone2: wilayas.filter(function(w) { return w.zone === 2; }).length,
                    zone3: wilayas.filter(function(w) { return w.zone === 3; }).length,
                    zone4: wilayas.filter(function(w) { return w.zone === 4; }).length
                }
            };
        }
    };
});
