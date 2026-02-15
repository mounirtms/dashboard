/**
 * Wilaya-Commune Conditional Dropdown
 * Filters communes based on selected wilaya
 */
define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    return function (config, element) {
        var $wilayaSelect = $(element);
        var $communeSelect = $('select[name="city"]');
        
        // Store all communes data
        var communesData = {};
        
        /**
         * Load communes from JSON file
         */
        function loadCommunesData() {
            $.ajax({
                url: urlBuilder.build('rest/V1/directory/communes'),
                type: 'GET',
                dataType: 'json',
                async: false,
                success: function(data) {
                    communesData = data;
                },
                error: function() {
                    // Fallback: try loading from static file
                    $.ajax({
                        url: '/pub/media/communes.json',
                        type: 'GET',
                        dataType: 'json',
                        async: false,
                        success: function(data) {
                            // Group by wilaya_id
                            data.forEach(function(commune) {
                                if (!communesData[commune.wilaya_id]) {
                                    communesData[commune.wilaya_id] = [];
                                }
                                communesData[commune.wilaya_id].push(commune);
                            });
                        }
                    });
                }
            });
        }
        
        /**
         * Filter communes based on wilaya
         */
        function filterCommunes(wilayaId) {
            if (!wilayaId) {
                $communeSelect.html('<option value="">Sélectionnez une commune</option>').prop('disabled', true);
                return;
            }
            
            var communes = communesData[wilayaId] || [];
            var options = '<option value="">Sélectionnez une commune</option>';
            
            communes.forEach(function(commune) {
                options += '<option value="' + commune.id + '">' + commune.name + '</option>';
            });
            
            $communeSelect.html(options).prop('disabled', false);
        }
        
        /**
         * Initialize
         */
        function init() {
            // Load communes data
            loadCommunesData();
            
            // Listen to wilaya change
            $wilayaSelect.on('change', function() {
                var wilayaId = $(this).val();
                filterCommunes(wilayaId);
            });
            
            // Initialize on page load if wilaya is already selected
            var initialWilayaId = $wilayaSelect.val();
            if (initialWilayaId) {
                filterCommunes(initialWilayaId);
            }
        }
        
        // Execute on DOM ready
        $(document).ready(function() {
            init();
        });
    };
});
