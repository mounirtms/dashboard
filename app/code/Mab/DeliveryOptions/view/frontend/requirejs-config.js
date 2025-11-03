/**
 * MAB Delivery Options - RequireJS Configuration
 * 
 * @category    Mab
 * @package     Mab_DeliveryOptions
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 */

var config = {
    map: {
        '*': {
            'mab-locations-fix': 'Mab_DeliveryOptions/js/locations-fix',
            'mab-delivery-options': 'Mab_DeliveryOptions/js/delivery-options'
        }
    },
    deps: [
        'mab-locations-fix'
    ],
    config: {
        'mab-locations-fix': {
            'autoInit': true
        }
    },
    shim: {
        'mab-locations-fix': {
            deps: ['jquery'],
            init: function($) {
                // Initialize locations fix immediately
                require(['mab-locations-fix'], function(locationsFix) {
                    locationsFix.init();
                    locationsFix.handleLocationErrors();
                    locationsFix.monitorLocations();
                });
            }
        }
    }
};