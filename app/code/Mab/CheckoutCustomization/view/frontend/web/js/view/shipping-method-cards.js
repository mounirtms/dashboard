/**
 * Mab_CheckoutCustomization - Shipping Method Cards Display
 * Replaces Magento shipping step with custom card layout
 * 
 * Yalidine method IDs (from mageplaza_tablerate_method): 2, 24, 29
 * All other method IDs are Techno store pickup (Retrait en magasin)
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

    // Exact Yalidine method IDs from mageplaza_tablerate_method database table
    var YALIDINE_IDS = [2, 24, 29];

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

            // Replace entire shipping step with our cards after page load
            setTimeout(function () {
                self.replaceShippingStep();
            }, 800);

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
         * Extract method ID from code (e.g., "mptablerate_24" => 24)
         */
        extractMethodId: function (methodCode) {
            var parts = methodCode.split('_');
            if (parts.length > 1) {
                return parseInt(parts[parts.length - 1], 10);
            }
            return 0;
        },

        /**
         * Check if method ID is a Yalidine method
         */
        isYalidine: function (methodId) {
            return YALIDINE_IDS.indexOf(methodId) !== -1;
        },

        /**
         * Replace entire shipping step with custom cards
         */
        replaceShippingStep: function () {
            var self = this;
            var $stepContent = $('#checkout-step-shipping_method');

            if ($stepContent.length === 0) {
                setTimeout(function () {
                    self.replaceShippingStep();
                }, 500);
                return;
            }

            // Prevent duplicate rendering
            if ($stepContent.data('cards-rendered')) return;
            $stepContent.data('cards-rendered', true);

            // Find the shipping method form/table
            var $shippingForm = $stepContent.find('#co-shipping-method-form');
            var $shippingTable = $stepContent.find('table.table-checkout-shipping-method');

            if ($shippingTable.length === 0 || $shippingForm.length === 0) {
                // Remove render flag to allow retry
                $stepContent.data('cards-rendered', false);
                setTimeout(function () {
                    self.replaceShippingStep();
                }, 500);
                return;
            }

            // Hide the entire step container
            $stepContent.hide();

            // Build cards HTML
            var cardsHtml = this.buildCardsHtml($shippingTable);

            // Remove any existing cards wrapper to prevent duplicates
            $('.shipping-methods-cards-wrapper').remove();

            // Insert our cards wrapper BEFORE the hidden step
            var $cardsWrapper = $('<div class="shipping-methods-cards-wrapper">' +
                '<div class="cards-header">' +
                    '<h3 class="section-title">🚚 Modes de livraison</h3>' +
                    '<p class="section-subtitle">Choisissez votre option de livraison préférée</p>' +
                '</div>' +
                '<div id="shipping-method-cards-container" class="shipping-cards-grid">' + cardsHtml + '</div>' +
                '<div class="shipping-info-notice">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="info-icon"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' +
                    '<span>Les délais de livraison sont des estimations et peuvent varier selon votre wilaya</span>' +
                '</div>' +
            '</div>');

            $stepContent.before($cardsWrapper);

            // Bind click handlers
            this.bindCardHandlers();
        },

        /**
         * Build cards HTML from table data
         */
        buildCardsHtml: function ($shippingTable) {
            var self = this;
            var html = '';

            $shippingTable.find('tbody tr.row').each(function () {
                var $row = $(this);
                var $radio = $row.find('input[type="radio"]');

                if (!$radio.length) return;

                var methodCode = $radio.val();
                var methodId = self.extractMethodId(methodCode);

                // Extract method title
                var methodName = $row.find('.col-method').first().text().trim();
                if (!methodName) {
                    methodName = $row.find('td').eq(2).text().trim();
                }
                if (!methodName) {
                    methodName = 'Method';
                }

                // Extract price
                var priceText = '';
                var $priceCol = $row.find('.col-price .price').last();
                if ($priceCol.length) {
                    priceText = $priceCol.text().trim();
                }
                if (!priceText) {
                    priceText = $row.find('td').eq(1).text().trim();
                }

                var isFree = !priceText || priceText.indexOf('0,00') === 0 || priceText.indexOf('0.00') === 0 || priceText.toLowerCase().indexOf('gratuit') >= 0 || priceText.toLowerCase().indexOf('free') >= 0;
                var formattedPrice = isFree ? '' : priceText;
                var carrier = self.identifyCarrier(methodId, methodName);
                var carrierLogo = self.getCarrierLogo(carrier);
                var deliveryTime = self.estimateDeliveryTime(carrier, methodId, methodName);
                var isChecked = $radio.is(':checked');

                html += '<div class="shipping-card ' + (isFree ? 'free-shipping' : '') + (isChecked ? ' selected' : '') + '" data-method-code="' + methodCode + '">' +
                    '<input type="radio" name="shipping-method-card" id="card-' + methodCode + '" value="' + methodCode + '" ' + (isChecked ? 'checked' : '') + ' class="shipping-radio" />' +
                    '<div class="card-content">' +
                        '<div class="card-left">' +
                            '<div class="carrier-logo">' + carrierLogo + '</div>' +
                            '<div class="method-info">' +
                                '<h4 class="method-name">' + methodName + '</h4>' +
                                '<div class="delivery-time">' +
                                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="clock-icon"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                                    '<span>' + deliveryTime + '</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="card-right">' +
                            (isFree ? '<span class="free-badge">🎁 Gratuit</span>' : '<span class="price">' + formattedPrice + '</span>') +
                        '</div>' +
                    '</div>' +
                    '<div class="check-indicator">' +
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' +
                    '</div>' +
                '</div>';
            });

            return html;
        },

        /**
         * Bind click handlers to cards
         */
        bindCardHandlers: function () {
            var self = this;

            $('.shipping-card').on('click', function (e) {
                var $card = $(this);
                var methodCode = $card.data('method-code');
                var $radio = $card.find('.shipping-radio');

                if (!$(e.target).is('input[type="radio"]')) {
                    $radio.prop('checked', true).trigger('change');
                }

                // Update original hidden radio
                var $originalRadio = $('table.table-checkout-shipping-method input[value="' + methodCode + '"]');
                if ($originalRadio.length) {
                    $originalRadio.prop('checked', true).trigger('click');
                }

                self.selectCard($card);
            });
        },

        /**
         * Select a shipping card
         */
        selectCard: function ($card) {
            $('.shipping-card').removeClass('selected');
            $card.addClass('selected');
        },

        /**
         * Update selected card when method changes
         */
        updateSelectedCard: function (methodCode) {
            $('.shipping-card').removeClass('selected');
            $('.shipping-card[data-method-code="' + methodCode + '"]').addClass('selected');
        },

        /**
         * Get carrier logo HTML
         */
        getCarrierLogo: function (carrier) {
            var baseUrl = window.BASE_URL || '';

            if (carrier === 'yalidine') {
                // Try primary yalidine logo, fallback to JPG
                return '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/yalidine.png" ' +
                       'onerror="this.onerror=null; this.src=\'' + baseUrl + 'pub/media/mageplaza/tablerate/y/a/yalidine-logo.jpg\';" ' +
                       'alt="Yalidine" class="carrier-img" />';
            }

            // Techno store pickup
            return '<img src="' + baseUrl + 'pub/media/mageplaza/tablerate/techno.png" ' +
                   'onerror="this.onerror=null; this.src=\'' + baseUrl + 'pub/media/logo/default/logo_techno.png\';" ' +
                   'alt="Techno" class="carrier-img" />';
        },

        /**
         * Identify carrier using exact method IDs
         */
        identifyCarrier: function (methodId) {
            if (this.isYalidine(methodId)) {
                return 'yalidine';
            }
            return 'store-pickup';
        },

        /**
         * Estimate delivery time - French only
         */
        estimateDeliveryTime: function (carrier, methodId) {
            if (carrier === 'yalidine') {
                if (methodId === 24) return 'Retrait en agence - 2-3 jours';
                if (methodId === 29) return 'Livraison à domicile gratuite - 3-5 jours';
                return 'Livraison à domicile - 3-5 jours';
            }
            return 'Retrait en magasin - Disponible immédiatement';
        }
    });
});
