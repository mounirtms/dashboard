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

                // Create simplified card HTML - radio + logo + name + price only
                var cardHtml = `
                    <div class="shipping-card ${isFree ? 'free-shipping' : ''}" data-method-code="${methodCode}">
                        <div class="card-content">
                            <div class="card-left">
                                <input type="radio"
                                    name="shipping-method-card"
                                    id="card-${methodCode}"
                                    value="${methodCode}"
                                    ${$radio.is(':checked') ? 'checked' : ''}
                                    class="shipping-radio" />
                                <div class="carrier-logo">
                                    ${carrierLogo}
                                </div>
                                <div class="method-info">
                                    <h3 class="method-name">${methodName}</h3>
                                    <div class="delivery-time">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <span>${deliveryTime}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-right">
                                ${isFree ?
                                    '<span class="free-badge">' + $t('Gratuit') + '</span>' :
                                    '<span class="price">' + formattedPrice + '</span>'
                                }
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
         * Get carrier logo HTML - uses unified logos from database
         * All Techno methods use techno.png, all Yalidine use yalidine-logo.jpg
         */
        getCarrierLogo: function (carrier, imageFromTable, methodName) {
            var baseUrl = window.BASE_URL || '';

            // Use unified logo paths based on carrier type
            if (carrier === 'yalidine') {
                return '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/yalidine-logo.jpg" alt="Yalidine" class="carrier-img" />';
            }

            if (carrier === 'store-pickup') {
                return '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/techno.png" alt="Techno" class="carrier-img" />';
            }

            // If we have an image from the table, use it as fallback
            if (imageFromTable && imageFromTable.length > 0) {
                var imgSrc = imageFromTable;
                if (imageFromTable.indexOf('http') !== 0 && imageFromTable.indexOf('//') !== 0) {
                    imgSrc = baseUrl + imageFromTable.replace(/^\/+/, '');
                }
                return '<img src="' + imgSrc + '" alt="' + methodName + '" class="carrier-img" />';
            }

            // Default fallback
            return '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/techno.png" alt="Shipping" class="carrier-img" />';
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
         * All Techno methods = store-pickup, all Yalidine = yalidine
         */
        identifyCarrier: function (methodName, carrierTitle) {
            var name = methodName.toLowerCase();
            var carrier = carrierTitle ? carrierTitle.toLowerCase() : '';

            // Yalidine - all Yalidine methods (home delivery or agence)
            if (name.indexOf('yalidine') >= 0 || carrier.indexOf('yalidine') >= 0) {
                return 'yalidine';
            }

            // Techno store pickup - ALL Techno stores
            if (name.indexOf('techno') >= 0 || carrier.indexOf('techno') >= 0) {
                return 'store-pickup';
            }

            return 'default';
        },

        /**
         * Estimate delivery time - French locale only
         */
        estimateDeliveryTime: function (carrier, methodName) {
            var name = methodName.toLowerCase();

            // Yalidine - home delivery or agence pickup
            if (carrier === 'yalidine') {
                if (name.indexOf('agence') >= 0) {
                    return 'Retrait en agence - 2-3 jours';
                }
                return 'Livraison à domicile - 3-5 jours';
            }

            // Techno store pickup (Retrait en magasin)
            if (carrier === 'store-pickup') {
                return 'Retrait en magasin - Disponible immédiatement';
            }

            // Default
            return 'Livraison standard - 3-5 jours';
        }
    });
});
