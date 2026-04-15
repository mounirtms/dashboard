/**
 * Mab_CheckoutCustomization - Shipping Method Cards Display
 * Converts Mageplaza table-based shipping methods to modern card layout
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'mage/translate'
], function ($, ko, Component, quote, selectShippingMethodAction, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/shipping-method-cards'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            var self = this;

            // Watch for shipping methods changes
            quote.shippingMethod.subscribe(function (method) {
                if (method) {
                    self.updateSelectedCard(method.carrier_code + '_' + method.method_code);
                }
            });

            // Initial conversion after page load
            setTimeout(function () {
                self.convertToCards();
            }, 1000);

            return this;
        },

        /**
         * Get available shipping methods
         */
        getShippingMethods: function () {
            return ko.computed(function () {
                var methods = quote.shippingMethod() ? quote.shippingMethod().available_rates || [] : [];
                return methods;
            });
        },

        /**
         * Convert table-based shipping methods to cards
         */
        convertToCards: function () {
            var self = this;
            var $shippingTable = $('#checkout-shipping-method-load table.table-checkout-shipping-method');

            if ($shippingTable.length === 0) {
                // Retry if table not loaded yet
                setTimeout(function () {
                    self.convertToCards();
                }, 500);
                return;
            }

            // Hide original table
            $shippingTable.addClass('shipping-table-hidden');

            // Create cards container if not exists
            var $container = $('#shipping-method-cards-container');
            if ($container.length === 0) {
                $container = $('<div id="shipping-method-cards-container" class="shipping-cards-grid"></div>');
                $shippingTable.after($container);
            }

            $container.empty();

            // Process each shipping method row
            $shippingTable.find('tbody tr.row').each(function () {
                var $row = $(this);
                var $radio = $row.find('input[type="radio"]');
                
                // Skip if no radio button (extension rows)
                if (!$radio.length) return;
                
                var methodCode = $radio.val();
                
                // Extract method title - try multiple selectors for robustness
                var methodName = $row.find('.col-method').first().text().trim();
                if (!methodName) {
                    methodName = $row.find('td').eq(2).text().trim(); // 3rd column
                }
                if (!methodName) {
                    methodName = $radio.attr('data-title') || 'Shipping Method';
                }
                
                // Extract carrier title - try multiple selectors
                var carrierTitle = $row.find('.col-carrier').text().trim();
                if (!carrierTitle) {
                    carrierTitle = $row.find('td').eq(3).text().trim(); // 4th column
                }
                
                // Extract price - try multiple selectors
                var $priceCol = $row.find('.col-price');
                var priceText = '';
                if ($priceCol.length) {
                    priceText = $priceCol.find('.price').last().text().trim();
                }
                if (!priceText) {
                    priceText = $row.find('td').eq(1).text().trim(); // 2nd column
                }
                var isFree = !priceText || priceText.indexOf('0,00') === 0 || priceText.indexOf('0.00') === 0 || priceText.toLowerCase().indexOf('gratuit') >= 0 || priceText.toLowerCase().indexOf('free') >= 0;
                
                // Extract carrier image from table (MagePlaza stores it in the row)
                var carrierImageSrc = '';
                var $imageCell = $row.find('img');
                if ($imageCell.length) {
                    carrierImageSrc = $imageCell.attr('src');
                }
                
                // Extract carrier info
                var carrier = self.identifyCarrier(methodName, carrierTitle);
                var deliveryTime = self.estimateDeliveryTime(carrier, methodName);
                var carrierLogo = self.getCarrierLogo(carrier, carrierImageSrc, methodName);
                
                // Format price - keep original DZD format
                var formattedPrice = isFree ? '' : priceText;

                // Create card HTML - only use radio, no checkbox
                var cardHtml = `
                    <div class="shipping-card ${isFree ? 'free-shipping' : ''}" data-method-code="${methodCode}">
                        <div class="card-header">
                            <div class="radio-wrapper">
                                <input type="radio" 
                                    name="shipping-method-card" 
                                    id="card-${methodCode}" 
                                    value="${methodCode}" 
                                    ${$radio.is(':checked') ? 'checked' : ''} 
                                    class="shipping-radio" />
                                <label for="card-${methodCode}" class="radio-label"></label>
                            </div>
                            <div class="carrier-logo">
                                ${carrierLogo}
                            </div>
                        </div>
                        <div class="card-body">
                            <h3 class="method-name">${methodName}</h3>
                            <p class="carrier-title">${carrierTitle}</p>
                            <div class="method-details">
                                <div class="delivery-time">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    <span>${deliveryTime}</span>
                                </div>
                                <div class="price-wrapper">
                                    ${isFree ? 
                                        '<span class="free-badge">' + $t('Gratuit') + '</span>' : 
                                        '<span class="price">' + formattedPrice + '</span>'
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                var $card = $(cardHtml);
                $container.append($card);

                // Click handler for card
                $card.on('click', function (e) {
                    if (!$(e.target).is('input[type="radio"]')) {
                        $card.find('input[type="radio"]').prop('checked', true).trigger('click');
                    }
                    self.selectCard($card, methodCode, $radio);
                });

                // Click handler for radio button
                $card.find('input[type="radio"]').on('change', function () {
                    self.selectCard($card, methodCode, $radio);
                });
            });
        },

        /**
         * Select a shipping card
         */
        selectCard: function ($card, methodCode, $originalRadio) {
            // Remove selected class from all cards
            $('.shipping-card').removeClass('selected');
            
            // Add selected class to clicked card
            $card.addClass('selected');

            // Trigger original radio button click
            if ($originalRadio && $originalRadio.length) {
                $originalRadio.prop('checked', true).trigger('click');
            }
        },

        /**
         * Update selected card when method changes
         */
        updateSelectedCard: function (methodCode) {
            $('.shipping-card').removeClass('selected');
            $('.shipping-card[data-method-code="' + methodCode + '"]').addClass('selected');
        },

        /**
         * Get carrier logo HTML - reads from MagePlaza table or uses defaults
         */
        getCarrierLogo: function (carrier, imageFromTable, methodName) {
            var baseUrl = window.BASE_URL || '';
            var name = methodName.toLowerCase();
            
            // If we have an image from the table, use it
            if (imageFromTable && imageFromTable.length > 0) {
                // Check if it's a full URL or relative path
                var imgSrc = imageFromTable;
                if (imageFromTable.indexOf('http') !== 0 && imageFromTable.indexOf('//') !== 0) {
                    // Relative path - ensure it starts from baseUrl
                    imgSrc = baseUrl + imageFromTable.replace(/^\/+/, '');
                }
                
                // Determine fallback based on carrier type
                var fallback = baseUrl + 'pub/media/logo/default/logo_techno.png';
                
                return '<img src="' + imgSrc + '" alt="' + methodName + '" class="carrier-img" onerror="this.src=\'' + fallback + '\'" />';
            }
            
            // Fallback to hardcoded logos based on carrier detection
            var logos = {
                // Yalidine - use JPG logo
                'yalidine': '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/y/a/yalidine-logo.jpg" alt="Yalidine" class="carrier-img" onerror="this.src=\'' + baseUrl + 'pub/media/mageplaza/tablerate/yalidine.png\'" />',
                // Ecotrak - use PNG
                'ecotrak': '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/ecotrak.png" alt="Ecotrak" class="carrier-img" onerror="this.style.display=\'none\'" />',
                // Techno store pickup - use Techno PNG for ALL Techno stores
                'store-pickup': '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/techno.png" alt="Techno Store" class="carrier-img" onerror="this.src=\'' + baseUrl + 'pub/media/logo/default/logo_techno.png\'" />',
                // Free shipping - purple badge
                'free': '<svg width="80" height="40" viewBox="0 0 80 40"><rect width="80" height="40" fill="#9C27B0"/><text x="40" y="24" font-family="Arial" font-size="14" font-weight="bold" fill="white" text-anchor="middle">Gratuit</text></svg>',
                // Default
                'default': '<svg width="80" height="40" viewBox="0 0 80 40"><rect width="80" height="40" fill="#757575"/><text x="40" y="24" font-family="Arial" font-size="12" font-weight="bold" fill="white" text-anchor="middle">Livraison</text></svg>'
            };
            return logos[carrier] || logos['default'];
        },

        /**
         * Format price to Algerian format (e.g., 2,500.00 DZD)
         */
        formatPrice: function (priceText) {
            // Extract numeric value
            var matches = priceText.match(/[\d,\.]+/);
            if (!matches) return priceText;
            
            var numStr = matches[0].replace(/,/g, '');
            var num = parseFloat(numStr);
            
            if (isNaN(num)) return priceText;
            
            // Format with thousands separator and 2 decimals
            var formatted = num.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
            
            // Add currency
            return formatted + ' DZD';
        },

        /**
         * Identify carrier from method name and carrier title
         * Supports: Yalidine (home/agence), Ecotrak, Techno (retrait/pickup), Free shipping
         */
        identifyCarrier: function (methodName, carrierTitle) {
            var name = methodName.toLowerCase();
            var carrier = carrierTitle ? carrierTitle.toLowerCase() : '';
            
            // Yalidine - home or agence pickup
            if (name.indexOf('yalidine') >= 0 || carrier.indexOf('yalidine') >= 0) {
                return 'yalidine';
            }
            
            // Ecotrak
            if (name.indexOf('ecotrak') >= 0 || carrier.indexOf('ecotrak') >= 0) {
                return 'ecotrak';
            }
            
            // Techno store pickup (ALL Techno stores: Pins Maritimes, etc.)
            if (name.indexOf('techno') >= 0 || 
                carrier.indexOf('techno') >= 0 ||
                name.indexOf('pins') >= 0 ||
                name.indexOf('maritimes') >= 0 ||
                name.indexOf('retrait') >= 0 || 
                name.indexOf('pickup') >= 0 || 
                name.indexOf('magasin') >= 0 ||
                name.indexOf('store') >= 0) {
                return 'store-pickup';
            }
            
            // Free shipping
            if (name.indexOf('gratuit') >= 0 || 
                name.indexOf('free') >= 0 ||
                name.indexOf('offert') >= 0) {
                return 'free';
            }
            
            return 'default';
        },

        /**
         * Estimate delivery time with French locale
         * All text in French for Algeria market
         */
        estimateDeliveryTime: function (carrier, methodName) {
            var name = methodName.toLowerCase();
            
            // Yalidine - check for home vs agence
            if (carrier === 'yalidine') {
                if (name.indexOf('agence') >= 0 || name.indexOf('agency') >= 0) {
                    return $t('Retrait en agence - 2-3 jours');
                } else if (name.indexOf('home') >= 0 || name.indexOf('domicile') >= 0 || name.indexOf('maison') >= 0) {
                    return $t('Livraison à domicile - 3-5 jours');
                }
                return $t('Livraison - 2-5 jours ouvrables');
            }
            
            // Ecotrak
            if (carrier === 'ecotrak') {
                return $t('Livraison - 3-5 jours ouvrables');
            }
            
            // Techno store pickup (Retrait magasin)
            if (carrier === 'store-pickup' || name.indexOf('techno') >= 0 || name.indexOf('retrait') >= 0) {
                return $t('Retrait immédiat en magasin');
            }
            
            // Free shipping
            if (carrier === 'free') {
                return $t('Livraison gratuite - 5-7 jours');
            }
            
            // Default
            return $t('Livraison standard');
        }
    });
});
