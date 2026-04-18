/**
 * Region ID Mapper
 * Maps between custom Algerian region IDs (1-58) and Magento standard IDs (859-900+)
 * 
 * Background: Algerian wilayas are stored with custom sequential IDs (1-58) in our
 * algerian-states.json for easier management, but Magento uses official numeric codes
 * (859-900+). This module handles the conversion for API calls and address management.
 */
define([], function () {
    'use strict';

    // Custom ID (1-58) => Magento ID (859-900+)
    var REGION_ID_MAPPING = {
        1: 859,   // Adrar
        2: 860,   // Chlef
        3: 861,   // Laghouat
        4: 862,   // Oum El Bouaghi
        5: 863,   // Batna
        6: 864,   // Béjaïa
        7: 865,   // Biskra
        8: 866,   // Béchar
        9: 867,   // Blida
        10: 868,  // Bouira
        11: 869,  // Tamanrasset
        12: 870,  // Tébessa
        13: 871,  // Tlemcen
        14: 872,  // Tiaret
        15: 873,  // Tizi Ouzou
        16: 874,  // Alger
        17: 875,  // Djelfa
        18: 876,  // Jijel
        19: 877,  // Sétif
        20: 878,  // Saïda
        21: 879,  // Skikda
        22: 880,  // Sidi Bel Abbès
        23: 881,  // Annaba
        24: 882,  // Guelma
        25: 883,  // Constantine
        26: 884,  // Médéa
        27: 885,  // Mostaganem
        28: 886,  // M'Sila
        29: 887,  // Mascara
        30: 888,  // Ouargla
        31: 889,  // Oran
        32: 890,  // El Bayadh
        33: 891,  // Illizi
        34: 892,  // Bordj Bou Arreridj
        35: 893,  // Boumerdès
        36: 894,  // El Tarf
        37: 895,  // Tindouf
        38: 896,  // Tissemsilt
        39: 897,  // El Oued
        40: 898,  // Khenchela
        41: 899,  // Souk Ahras
        42: 900,  // Tipaza
        43: 901,  // Mila
        44: 902,  // Aïn Defla
        45: 903,  // Naâma
        46: 904,  // Aïn Témouchent
        47: 905,  // Ghardaïa
        48: 906,  // Relizane
        49: 1683, // Timimoun
        50: 1684, // Bordj Badji Mokhtar
        51: 1685, // Ouled Djellal
        52: 1686, // Béni Abbès
        53: 1687, // In Salah
        54: 1688, // In Guezzam
        55: 1689, // Touggourt
        56: 1690, // Djanet
        57: 1691, // El M'Ghair
        58: 1692  // El Menia
    };

    // Reverse mapping: Magento ID => Custom ID
    var REGION_ID_REVERSE_MAPPING = {
        859: 1,    // Adrar
        860: 2,    // Chlef
        861: 3,    // Laghouat
        862: 4,    // Oum El Bouaghi
        863: 5,    // Batna
        864: 6,    // Béjaïa
        865: 7,    // Biskra
        866: 8,    // Béchar
        867: 9,    // Blida
        868: 10,   // Bouira
        869: 11,   // Tamanrasset
        870: 12,   // Tébessa
        871: 13,   // Tlemcen
        872: 14,   // Tiaret
        873: 15,   // Tizi Ouzou
        874: 16,   // Alger
        875: 17,   // Djelfa
        876: 18,   // Jijel
        877: 19,   // Sétif
        878: 20,   // Saïda
        879: 21,   // Skikda
        880: 22,   // Sidi Bel Abbès
        881: 23,   // Annaba
        882: 24,   // Guelma
        883: 25,   // Constantine
        884: 26,   // Médéa
        885: 27,   // Mostaganem
        886: 28,   // M'Sila
        887: 29,   // Mascara
        888: 30,   // Ouargla
        889: 31,   // Oran
        890: 32,   // El Bayadh
        891: 33,   // Illizi
        892: 34,   // Bordj Bou Arreridj
        893: 35,   // Boumerdès
        894: 36,   // El Tarf
        895: 37,   // Tindouf
        896: 38,   // Tissemsilt
        897: 39,   // El Oued
        898: 40,   // Khenchela
        899: 41,   // Souk Ahras
        900: 42,   // Tipaza
        901: 43,   // Mila
        902: 44,   // Aïn Defla
        903: 45,   // Naâma
        904: 46,   // Aïn Témouchent
        905: 47,   // Ghardaïa
        906: 48,   // Relizane
        1683: 49,  // Timimoun
        1684: 50,  // Bordj Badji Mokhtar
        1685: 51,  // Ouled Djellal
        1686: 52,  // Béni Abbès
        1687: 53,  // In Salah
        1688: 54,  // In Guezzam
        1689: 55,  // Touggourt
        1690: 56,  // Djanet
        1691: 57,  // El M'Ghair
        1692: 58   // El Menia
    };

    return {
        /**
         * Convert custom ID (1-58) to Magento ID (859-900+)
         * @param {number|string} customId - Custom region ID
         * @returns {number|null} Magento region ID or null if not found
         */
        toMagentoId: function(customId) {
            var id = parseInt(customId);
            if (isNaN(id)) {
                console.warn('[Region Mapper] Invalid custom ID:', customId);
                return null;
            }
            
            var magentoId = REGION_ID_MAPPING[id];
            if (!magentoId) {
                console.warn('[Region Mapper] No mapping found for custom ID:', id);
                return null;
            }
            
            console.log('[Region Mapper] Converted custom ID', id, 'to Magento ID', magentoId);
            return magentoId;
        },

        /**
         * Convert Magento ID (859-900+) to custom ID (1-58)
         * @param {number|string} magentoId - Magento region ID
         * @returns {number|null} Custom region ID or null if not found
         */
        toCustomId: function(magentoId) {
            var id = parseInt(magentoId);
            if (isNaN(id)) {
                console.warn('[Region Mapper] Invalid Magento ID:', magentoId);
                return null;
            }
            
            var customId = REGION_ID_REVERSE_MAPPING[id];
            if (!customId) {
                console.warn('[Region Mapper] No mapping found for Magento ID:', id);
                return null;
            }
            
            console.log('[Region Mapper] Converted Magento ID', id, 'to custom ID', customId);
            return customId;
        },

        /**
         * Check if an ID is a Magento ID (>= 859)
         * @param {number|string} id - Region ID to check
         * @returns {boolean} True if it's a Magento ID
         */
        isMagentoId: function(id) {
            var numId = parseInt(id);
            return !isNaN(numId) && numId >= 859;
        },

        /**
         * Check if an ID is a custom ID (1-58)
         * @param {number|string} id - Region ID to check
         * @returns {boolean} True if it's a custom ID
         */
        isCustomId: function(id) {
            var numId = parseInt(id);
            return !isNaN(numId) && numId >= 1 && numId <= 58;
        },

        /**
         * Get all mappings
         * @returns {object} Object with customToMagento and magentoToCustom mappings
         */
        getAllMappings: function() {
            return {
                customToMagento: REGION_ID_MAPPING,
                magentoToCustom: REGION_ID_REVERSE_MAPPING
            };
        }
    };
});
