/**
 * Wilaya-Commune Conditional Dropdown for Checkout
 * Filters communes based on selected wilaya in Magento 2 checkout
 * 
 * Usage: Apply as mixin to checkout address form components
 */
define([
    'jquery',
    'mage/url',
    'underscore'
], function ($, urlBuilder, _) {
    'use strict';

    return function (target) {
        return target.extend({
            defaults: {
                template: 'Mab_CheckoutCustomization/address-with-commune',
                communeOptions: [],
                selectedWilayaId: null
            },

            /**
             * Initialize component
             */
            initialize: function () {
                this._super();
                
                // Load communes data
                this.loadCommunesData();
                
                // Listen to region (wilaya) changes
                if (this.customAttributes && this.customAttributes.region_id) {
                    this.customAttributes.region_id.subscribe(function (wilayaId) {
                        this.onWilayaChange(wilayaId);
                    }, this);
                }
                
                return this;
            },

            /**
             * Load communes from API
             */
            loadCommunesData: function () {
                var self = this;
                
                $.ajax({
                    url: urlBuilder.build('rest/V1/directory/communes'),
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        self.communeOptions = data;
                        self.groupCommunesByWilaya();
                    },
                    error: function () {
                        console.warn('Could not load communes data. Using fallback.');
                        self.loadCommunesFallback();
                    }
                });
            },

            /**
             * Load communes from static fallback
             */
            loadCommunesFallback: function () {
                var self = this;
                
                $.ajax({
                    url: '/pub/media/communes.json',
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        self.communeOptions = data;
                        self.groupCommunesByWilaya();
                    }
                });
            },

            /**
             * Group communes by wilaya_id for easy lookup
             */
            groupCommunesByWilaya: function () {
                var grouped = {};
                
                _.each(this.communeOptions, function (commune) {
                    var wilayaId = commune.wilaya_id;
                    if (!grouped[wilayaId]) {
                        grouped[wilayaId] = [];
                    }
                    grouped[wilayaId].push(commune);
                });
                
                this.communeOptions = grouped;
            },

            /**
             * Handle wilaya change
             * @param {String|Number} wilayaId
             */
            onWilayaChange: function (wilayaId) {
                this.selectedWilayaId = wilayaId;
                
                if (!wilayaId) {
                    this.clearCommuneOptions();
                    return;
                }
                
                this.filterCommunes(wilayaId);
            },

            /**
             * Filter communes based on wilaya
             * @param {String|Number} wilayaId
             */
            filterCommunes: function (wilayaId) {
                var communes = this.communeOptions[wilayaId] || [];
                var options = [{
                    value: '',
                    label: 'Sélectionnez une commune'
                }];
                
                _.each(communes, function (commune) {
                    options.push({
                        value: commune.id,
                        label: commune.name
                    });
                });
                
                this.updateCommuneOptions(options);
            },

            /**
             * Clear commune options
             */
            clearCommuneOptions: function () {
                this.updateCommuneOptions([{
                    value: '',
                    label: 'Sélectionnez une commune'
                }]);
            },

            /**
             * Update commune dropdown options
             * @param {Array} options
             */
            updateCommuneOptions: function (options) {
                if (this.communeOptionsObservable) {
                    this.communeOptionsObservable(options);
                }
                
                // Trigger change event for validation
                $(document).trigger('commune:updated', [options]);
            },

            /**
             * Get commune options for template
             * @returns {Array}
             */
            getCommuneOptions: function () {
                return this.communeOptionsObservable || [];
            },

            /**
             * Set selected commune
             * @param {String} communeId
             */
            setCommune: function (communeId) {
                if (this.customAttributes && this.customAttributes.city) {
                    this.customAttributes.city(communeId);
                }
            },

            /**
             * Get selected commune
             * @returns {String}
             */
            getCommune: function () {
                if (this.customAttributes && this.customAttributes.city) {
                    return this.customAttributes.city();
                }
                return '';
            }
        });
    };
});
