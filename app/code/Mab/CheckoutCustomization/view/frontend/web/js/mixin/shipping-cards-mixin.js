/**
 * Mab_CheckoutCustomization - Shipping Cards Mixin
 * Directly renders shipping method cards into the checkout shipping step.
 * Uses the rates already available from the parent shipping component.
 * Preserves exact method_title from API response.
 */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/checkout-data',
    'mage/translate'
], function ($, ko, quote, selectShippingMethodAction, setShippingInformationAction, checkoutData, $t) {
    'use strict';

    function debounce(func, wait) {
        var timeout;
        return function () {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () { func.apply(context, args); }, wait);
        };
    }

    /**
     * Format price for display
     */
    function formatPrice(amount) {
        var price = parseFloat(amount) || 0;
        if (price === 0) return $t('Gratuit');
        return price.toFixed(2).replace('.', ',') + ' DZD';
    }

    /**
     * Get delivery time text based on method title
     * Techno Retrait: same-day pickup
     * Yalidine (agence + domicile): 2-5 jours with phone notice
     */
    function getDeliveryTime(methodTitle) {
        var title = (methodTitle || '').toLowerCase();
        
        // Techno retrait en agence - same day pickup
        if (title.indexOf('techno') !== -1 && (title.indexOf('retrait') !== -1 || title.indexOf('agence') !== -1)) {
            return $t('Retrait aujourd\'hui possible');
        }
        
        // Yalidine livraison à domicile - 2-5 days with phone notice
        if (title.indexOf('yalidine') !== -1 && (title.indexOf('livraison') !== -1 || title.indexOf('domicile') !== -1)) {
            return $t('2-5 jours (préavis par téléphone)');
        }
        
        // Yalidine retrait en agence - 2-5 days with phone notice
        if (title.indexOf('yalidine') !== -1 && title.indexOf('agence') !== -1) {
            return $t('2-5 jours (préavis par téléphone)');
        }
        
        // Generic fallbacks
        if (title.indexOf('livraison') !== -1 || title.indexOf('domicile') !== -1) {
            return $t('2-5 jours');
        }
        if (title.indexOf('retrait') !== -1 || title.indexOf('agence') !== -1) {
            return $t('Retrait immediat');
        }
        
        return $t('Retrait immediat');
    }

    /**
     * Build HTML for a single shipping card
     */
    function buildCardHTML(method, isSelected) {
        var selectedClass = isSelected ? ' selected' : '';
        var logoHTML = '';
        
        if (method.carrier_logo) {
            logoHTML = '<img class="carrier-img" src="' + method.carrier_logo + '" alt="' + method.method_title + '" onerror="this.parentElement.classList.add(\'logo-error\');">';
        } else {
            logoHTML = '<svg class="logo-placeholder" width="32" height="32" viewBox="0 0 32 32" fill="none"><rect x="2" y="2" width="28" height="28" rx="6" stroke="#ccc" stroke-width="1.5" stroke-dasharray="3 3"/><path d="M10 16h12M16 10v12" stroke="#ccc" stroke-width="1.5" stroke-linecap="round"/></svg>';
        }

        var priceHTML = '';
        if (method.is_free) {
            priceHTML = '<div class="free-badge"><span>Gratuit</span></div>';
        } else {
            priceHTML = '<div class="price"><span>' + method.price_formatted + '</span></div>';
        }

        // Build delivery time with appropriate icon
        var deliveryTimeHTML = '';
        if (method.delivery_time) {
            var timeLower = (method.delivery_time || '').toLowerCase();
            var icon = '';
            // Phone icon for préavis notifications
            if (timeLower.indexOf('préavis') !== -1 || timeLower.indexOf('telephone') !== -1) {
                icon = '<svg class="phone-icon" width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3.5 2C3.5 1.72386 3.72386 1.5 4 1.5H6.5L8 4.5L6.5 5.5C6.5 5.5 7.5 8 10.5 9.5L11.5 8L14.5 9.5V12C14.5 12.2761 14.2761 12.5 14 12.5C10 12.5 3.5 6 3.5 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';
            } else {
                icon = '<svg class="clock-icon" width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 4V8L10.5 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
            }
            deliveryTimeHTML = '<div class="delivery-time">' + icon + '<span>' + method.delivery_time + '</span></div>';
        }

        return '<div class="shipping-card' + selectedClass + '" data-method-code="' + method.method_code + '" data-carrier="' + method.carrier_code + '" data-method="' + method.method_id + '">' +
            '<div class="card-header">' +
                '<div class="carrier-logo' + (isSelected ? ' selected-logo' : '') + '">' +
                    logoHTML +
                '</div>' +
                '<div class="method-info">' +
                    '<h4 class="method-name">' + method.method_title + '</h4>' +
                    deliveryTimeHTML +
                '</div>' +
            '</div>' +
            '<div class="card-footer">' +
                priceHTML +
            '</div>' +
        '</div>';
    }

    /**
     * Build the complete cards HTML
     */
    function buildCardsHTML(methods, selectedMethodCode, regionName) {
        var cardsHTML = '';
        methods.forEach(function (method) {
            cardsHTML += buildCardHTML(method, method.method_code === selectedMethodCode);
        });

        var noticeText = regionName ? $t('Selectionnez votre mode de livraison pour la region de ') + regionName : $t('Selectionnez votre mode de livraison');

        return '<div class="cards-header">' +
                '<h3 class="section-title">' +
                    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M20 8H4V6H20V8ZM20 10H4V12H20V10ZM20 14H4V16H20V14Z" fill="currentColor"/><path d="M3 3H21C21.5523 3 22 3.44772 22 4V20C22 20.5523 21.5523 21 21 21H3C2.44772 21 2 20.5523 2 20V4C2 3.44772 2.44772 3 3 3Z" stroke="currentColor" stroke-width="2"/></svg>' +
                    '<span>Mode de livraison</span>' +
                '</h3>' +
                '<p class="section-subtitle">' + noticeText + '</p>' +
            '</div>' +
            '<div class="shipping-cards-grid">' +
                cardsHTML +
            '</div>' +
            '<div class="delivery-policy-link">' +
                '<a href="/livraison" class="policy-link">' +
                    '<svg class="link-icon" width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 2C5.58 2 2 5.58 2 10C2 14.42 5.58 18 10 18C14.42 18 18 14.42 18 10C18 5.58 14.42 2 10 2Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 9H11V10H9V9Z" fill="currentColor"/><path d="M9 6H11V7H9V6Z" fill="currentColor"/></svg>' +
                    '<span>Voir notre politique de livraison</span>' +
                '</a>' +
            '</div>';
    }

    /**
     * Resolve logo URL for a shipping method
     * Fix duplicate path issue: /media/mageplaza/tablerate/mageplaza/tablerate/ -> /media/mageplaza/tablerate/
     */
    function resolveLogo(rate) {
        var logoUrl = '';
        
        if (rate.extension_attributes && rate.extension_attributes.mptablerate_image) {
            logoUrl = rate.extension_attributes.mptablerate_image;
        } else if (rate.image) {
            logoUrl = rate.image;
        }
        
        // Fix duplicate path segment if present
        if (logoUrl && logoUrl.indexOf('/mageplaza/tablerate/mageplaza/tablerate/') !== -1) {
            logoUrl = logoUrl.replace('/mageplaza/tablerate/mageplaza/tablerate/', '/mageplaza/tablerate/');
        }
        
        return logoUrl;
    }

    /**
     * Convert rate to method object
     * Adds 'Yalidine' prefix for agency and home delivery options
     */
    function rateToMethod(rate) {
        var methodTitle = rate.method_title || rate.carrier_title;
        var titleLower = (methodTitle || '').toLowerCase();
        
        // Add 'Yalidine' prefix for agency and home delivery if not already present
        if ((titleLower.indexOf('retrait en agence') !== -1 || titleLower.indexOf('livraison') !== -1) && 
            titleLower.indexOf('yalidine') === -1) {
            methodTitle = 'Yalidine ' + methodTitle;
        }
        
        return {
            method_code: rate.carrier_code + '_' + rate.method_code,
            carrier_code: rate.carrier_code,
            method_id: rate.method_code,
            method_title: methodTitle,
            carrier_title: rate.carrier_title,
            amount: parseFloat(rate.amount) || 0,
            price_formatted: formatPrice(rate.amount),
            carrier_logo: resolveLogo(rate),
            delivery_time: getDeliveryTime(methodTitle),
            is_free: parseFloat(rate.amount) === 0,
            available: rate.available !== false
        };
    }

    // Mixin state
    var cardsRendered = false;
    var currentRegionName = '';

    /**
     * Render shipping cards directly into the DOM
     */
    function renderCards(self) {
        var rates = self.rates ? self.rates() : [];
        if (!rates || rates.length === 0) {
            console.log('No rates available, skipping render');
            return;
        }

        var $container = $('#checkout-shipping-method-load');
        if ($container.length === 0) {
            console.warn('Shipping container not found, will retry...');
            setTimeout(function () { renderCards(self); }, 500);
            return;
        }

        // Convert rates to methods
        var methods = [];
        $.each(rates, function (i, rate) {
            if (rate && rate.carrier_code && rate.method_code) {
                methods.push(rateToMethod(rate));
            }
        });

        if (methods.length === 0) {
            console.log('No valid methods to render');
            return;
        }

        // Get selected method code
        var selectedMethod = quote.shippingMethod();
        var selectedMethodCode = selectedMethod ? (selectedMethod.carrier_code + '_' + selectedMethod.method_code) : null;

        console.log('Rendering', methods.length, 'shipping methods. Selected:', selectedMethodCode);

        // Build and insert HTML
        var cardsHTML = buildCardsHTML(methods, selectedMethodCode, currentRegionName);
        $container.html('<div class="shipping-methods-cards-wrapper">' + cardsHTML + '</div>');

        // Hide ONLY the original Magento shipping table, NOT the form or buttons
        $('.checkout-shipping-method .methods-shipping, .checkout-shipping-method .table-checkout-shipping-method').hide();
        
        // Ensure the form and actions toolbar are VISIBLE
        $('#co-shipping-method-form').css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1',
            'height': 'auto',
            'max-height': 'none'
        });
        $('#shipping-method-buttons-container, .actions-toolbar').css({
            'display': 'flex',
            'visibility': 'visible',
            'opacity': '1',
            'height': 'auto',
            'margin-top': '20px'
        });
        $('#shipping-method-buttons-container button.action.continue').css({
            'display': 'inline-block',
            'visibility': 'visible',
            'opacity': '1'
        }).prop('disabled', false);

        // Attach click handlers to cards
        $container.off('click', '.shipping-card').on('click', '.shipping-card', function () {
            var $card = $(this);
            var methodCode = $card.data('method-code');
            var carrierCode = $card.data('carrier');
            var methodId = $card.data('method');

            // Update selected state visually
            $container.find('.shipping-card').removeClass('selected');
            $card.addClass('selected');

            // Select the shipping method
            var shippingMethod = {
                carrier_code: carrierCode,
                method_code: methodId,
                carrier_title: $card.find('.method-name').text(),
                method_title: $card.find('.method-name').text(),
                amount: 0,
                base_amount: 0,
                available: true,
                error_message: '',
                price_excl_tax: 0,
                price_incl_tax: 0
            };

            // Find the amount from the card
            var priceText = $card.find('.price span').text();
            if (priceText && priceText !== $t('Gratuit')) {
                var amount = parseFloat(priceText.replace(',', '.').replace(' DZD', ''));
                if (!isNaN(amount)) {
                    shippingMethod.amount = amount;
                    shippingMethod.base_amount = amount;
                    shippingMethod.price_excl_tax = amount;
                    shippingMethod.price_incl_tax = amount;
                }
            }

            console.log('Selected shipping method:', shippingMethod);

            // Call Magento's select shipping method action
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(carrierCode + '_' + methodId);

            // Trigger proceed to payment after a short delay
            setTimeout(function () {
                var deferred = setShippingInformationAction();
                if (deferred) {
                    deferred.done(function () {
                        console.log('Shipping info set successfully');
                    }).fail(function () {
                        console.warn('Failed to set shipping info');
                    });
                }
            }, 200);
        });

        cardsRendered = true;
        console.log('Shipping cards rendered successfully');
    }

    var mixin = {
        _renderTimer: null,
        _initialRenderDone: false,

        initialize: function () {
            this._super();
            var self = this;

            console.log('Shipping cards mixin initializing...');

            // Debounced render function
            var debouncedRender = debounce(function () {
                if (self.rates() && self.rates().length > 0 && !self.isLoading()) {
                    console.log('Rates loaded, rendering cards...');
                    renderCards(self);
                }
            }, 300);

            // Subscribe to rates changes
            if (this.rates) {
                this.rates.subscribe(function (newRates) {
                    console.log('Rates changed:', newRates ? newRates.length : 0);
                    if (newRates && newRates.length > 0) {
                        cardsRendered = false;
                        window.requestAnimationFrame(function () {
                            debouncedRender();
                        });
                    }
                });
            }

            // Subscribe to loading state
            if (this.isLoading) {
                this.isLoading.subscribe(function (isLoading) {
                    if (!isLoading && self.rates() && self.rates().length > 0) {
                        window.requestAnimationFrame(function () {
                            debouncedRender();
                        });
                    }
                });
            }

            // Subscribe to address changes for region name
            if (quote.shippingAddress) {
                quote.shippingAddress.subscribe(function (address) {
                    if (address && address.region) {
                        currentRegionName = address.region;
                        console.log('Region changed to:', currentRegionName);
                        cardsRendered = false;
                        setTimeout(function () {
                            debouncedRender();
                        }, 500);
                    }
                });
            }

            // Listen for wilaya:changed event
            $(document).on('wilaya:changed', function (e, wilayaId, wilayaName) {
                console.log('Wilaya changed:', wilayaId, wilayaName);
                currentRegionName = wilayaName || '';
                cardsRendered = false;
                setTimeout(function () {
                    debouncedRender();
                }, 800);
            });

            // Fallback: initial render after delay
            setTimeout(function () {
                if (!cardsRendered && self.rates() && self.rates().length > 0) {
                    console.log('Fallback: rendering shipping cards...');
                    renderCards(self);
                }
            }, 2000);

            return this;
        },

        setShippingInformation: function () {
            var result = this._super();
            var self = this;

            // Re-render cards after shipping info is set
            setTimeout(function () {
                cardsRendered = false;
                if (self.rates() && self.rates().length > 0) {
                    renderCards(self);
                }
            }, 500);

            return result;
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
