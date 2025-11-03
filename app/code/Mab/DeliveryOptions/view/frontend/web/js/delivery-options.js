/**
 * MAB Delivery Options - Main JavaScript Module
 * 
 * @category    Mab
 * @package     Mab_DeliveryOptions
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 */

define([
    'jquery',
    'underscore',
    'mage/url',
    'mage/storage',
    'mage/translate',
    'mab-locations-fix'
], function ($, _, urlBuilder, storage, $t, locationsFix) {
    'use strict';

    return {
        options: {
            selectors: {
                shippingMethodsContainer: '.table-checkout-shipping-method',
                shippingMethod: 'input[name="shipping_method"]',
                freeShippingProgress: '.mab-free-shipping-progress',
                celebrationContainer: '.mab-celebration-container'
            },
            urls: {
                calculateShipping: 'mab_delivery/ajax/calculate',
                validateAddress: 'mab_delivery/ajax/validate'
            },
            cache: {
                enabled: true,
                ttl: 300000 // 5 minutes
            }
        },

        /**
         * Initialize the delivery options module
         */
        init: function(config) {
            this.options = $.extend(true, this.options, config || {});
            
            // Initialize locations fix first
            locationsFix.init();
            locationsFix.handleLocationErrors();
            locationsFix.monitorLocations();
            
            this.bindEvents();
            this.initializeShippingCalculator();
            this.setupProgressIndicators();
            
            console.log('[MAB Delivery] Module initialized successfully');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Shipping method change
            $(document).on('change', this.options.selectors.shippingMethod, function() {
                self.onShippingMethodChange($(this));
            });

            // Cart update events
            $(document).on('mab:cart:updated', function(event, data) {
                self.onCartUpdate(data);
            });

            // Address change events
            $(document).on('mab:address:changed', function(event, data) {
                self.onAddressChange(data);
            });
        },

        /**
         * Handle shipping method change
         */
        onShippingMethodChange: function($element) {
            const method = $element.val();
            const methodData = this.parseShippingMethod(method);
            
            if (methodData.carrier === 'yalidine') {
                this.handleYalidineMethod(methodData);
            } else if (methodData.carrier === 'mptablerate') {
                this.handleMageplazaMethod(methodData);
            }
            
            this.updateProgressIndicators();
            this.triggerVisualEffects(methodData);
        },

        /**
         * Parse shipping method string
         */
        parseShippingMethod: function(method) {
            const parts = method.split('_');
            return {
                carrier: parts[0],
                method: parts[1],
                full: method
            };
        },

        /**
         * Handle Yalidine shipping method
         */
        handleYalidineMethod: function(methodData) {
            console.log('[MAB Delivery] Yalidine method selected:', methodData);
            
            // Check if free shipping conditions are met
            this.checkFreeShippingEligibility().then(function(eligible) {
                if (eligible) {
                    this.showFreeShippingMessage();
                    this.triggerCelebrationEffect();
                }
            }.bind(this));
        },

        /**
         * Handle Mageplaza shipping method
         */
        handleMageplazaMethod: function(methodData) {
            console.log('[MAB Delivery] Mageplaza method selected:', methodData);
            
            // Apply MAB customizations to Mageplaza methods
            this.applyMageplazaCustomizations(methodData);
        },

        /**
         * Check free shipping eligibility
         */
        checkFreeShippingEligibility: function() {
            const cacheKey = 'mab_free_shipping_check';
            const cached = this.getFromCache(cacheKey);
            
            if (cached !== null) {
                return Promise.resolve(cached);
            }
            
            return storage.post(
                urlBuilder.build(this.options.urls.calculateShipping),
                JSON.stringify({
                    action: 'check_free_shipping'
                })
            ).then(function(response) {
                const eligible = response.eligible || false;
                this.setCache(cacheKey, eligible);
                return eligible;
            }.bind(this)).catch(function(error) {
                console.error('[MAB Delivery] Free shipping check failed:', error);
                return false;
            });
        },

        /**
         * Initialize shipping calculator
         */
        initializeShippingCalculator: function() {
            // Enhanced shipping calculation with caching
            this.shippingCalculator = {
                cache: new Map(),
                
                calculate: function(address, items) {
                    const key = this.generateCacheKey(address, items);
                    
                    if (this.cache.has(key)) {
                        return Promise.resolve(this.cache.get(key));
                    }
                    
                    return this.performCalculation(address, items).then(function(result) {
                        this.cache.set(key, result);
                        return result;
                    }.bind(this));
                }.bind(this),
                
                generateCacheKey: function(address, items) {
                    return btoa(JSON.stringify({
                        address: address,
                        items: items.map(item => ({
                            sku: item.sku,
                            qty: item.qty,
                            weight: item.weight
                        }))
                    }));
                },
                
                performCalculation: function(address, items) {
                    return storage.post(
                        urlBuilder.build(this.options.urls.calculateShipping),
                        JSON.stringify({
                            address: address,
                            items: items
                        })
                    );
                }
            };
        },

        /**
         * Setup progress indicators
         */
        setupProgressIndicators: function() {
            const $container = $(this.options.selectors.freeShippingProgress);
            
            if ($container.length) {
                this.progressIndicator = {
                    container: $container,
                    
                    update: function(current, target) {
                        const percentage = Math.min((current / target) * 100, 100);
                        const $bar = this.container.find('.progress-bar');
                        
                        $bar.css('width', percentage + '%');
                        
                        if (percentage >= 100) {
                            this.container.addClass('achieved');
                            this.showAchievedMessage();
                        }
                    },
                    
                    showAchievedMessage: function() {
                        const message = $t('Congratulations! You qualify for free shipping!');
                        this.container.find('.progress-message').text(message);
                    }
                };
            }
        },

        /**
         * Update progress indicators
         */
        updateProgressIndicators: function() {
            if (this.progressIndicator) {
                // Get current cart total and free shipping threshold
                this.getCurrentCartData().then(function(data) {
                    this.progressIndicator.update(data.total, data.freeShippingThreshold);
                }.bind(this));
            }
        },

        /**
         * Get current cart data
         */
        getCurrentCartData: function() {
            return storage.get(
                urlBuilder.build('mab_delivery/ajax/cartdata')
            ).then(function(response) {
                return {
                    total: response.total || 0,
                    freeShippingThreshold: response.free_shipping_threshold || 0,
                    items: response.items || []
                };
            }).catch(function(error) {
                console.error('[MAB Delivery] Failed to get cart data:', error);
                return { total: 0, freeShippingThreshold: 0, items: [] };
            });
        },

        /**
         * Trigger visual effects
         */
        triggerVisualEffects: function(methodData) {
            if (methodData.carrier === 'yalidine' && this.isFreeShipping(methodData)) {
                this.triggerCelebrationEffect();
            }
        },

        /**
         * Trigger celebration effect
         */
        triggerCelebrationEffect: function() {
            const $container = $(this.options.selectors.celebrationContainer);
            
            if ($container.length && window.MabVisualEffects) {
                window.MabVisualEffects.celebrate({
                    container: $container[0],
                    type: 'confetti',
                    duration: 3000
                });
            }
        },

        /**
         * Check if shipping method is free
         */
        isFreeShipping: function(methodData) {
            const $selectedMethod = $('input[name="shipping_method"]:checked');
            const priceText = $selectedMethod.closest('tr').find('.price').text();
            
            return priceText.includes('0') || priceText.toLowerCase().includes('free');
        },

        /**
         * Show free shipping message
         */
        showFreeShippingMessage: function() {
            const message = $t('Free shipping applied!');
            
            // Create or update message element
            let $message = $('.mab-free-shipping-message');
            if (!$message.length) {
                $message = $('<div class="mab-free-shipping-message"></div>');
                $(this.options.selectors.shippingMethodsContainer).after($message);
            }
            
            $message.html('<i class="fa fa-check-circle"></i> ' + message)
                   .addClass('success')
                   .fadeIn();
        },

        /**
         * Handle cart update
         */
        onCartUpdate: function(data) {
            console.log('[MAB Delivery] Cart updated:', data);
            this.updateProgressIndicators();
            this.clearCache();
        },

        /**
         * Handle address change
         */
        onAddressChange: function(data) {
            console.log('[MAB Delivery] Address changed:', data);
            this.clearCache();
            
            // Recalculate shipping rates
            this.recalculateShipping(data.address);
        },

        /**
         * Recalculate shipping rates
         */
        recalculateShipping: function(address) {
            if (this.shippingCalculator) {
                this.getCurrentCartData().then(function(cartData) {
                    return this.shippingCalculator.calculate(address, cartData.items);
                }.bind(this)).then(function(rates) {
                    this.updateShippingMethods(rates);
                }.bind(this));
            }
        },

        /**
         * Update shipping methods display
         */
        updateShippingMethods: function(rates) {
            // Update the shipping methods table with new rates
            const $container = $(this.options.selectors.shippingMethodsContainer);
            
            if (rates && rates.methods) {
                rates.methods.forEach(function(method) {
                    const $method = $container.find(`input[value="${method.code}"]`);
                    const $priceCell = $method.closest('tr').find('.price');
                    
                    if ($priceCell.length) {
                        $priceCell.text(method.price_formatted);
                    }
                });
            }
        },

        /**
         * Apply Mageplaza customizations
         */
        applyMageplazaCustomizations: function(methodData) {
            // Custom logic for Mageplaza methods
            if (methodData.method === '2' || methodData.method === '24') {
                // These are Yalidine methods in Mageplaza
                this.handleYalidineMethod(methodData);
            }
        },

        /**
         * Cache management
         */
        getFromCache: function(key) {
            if (!this.options.cache.enabled) {
                return null;
            }
            
            const cached = localStorage.getItem('mab_delivery_' + key);
            if (cached) {
                const data = JSON.parse(cached);
                if (Date.now() - data.timestamp < this.options.cache.ttl) {
                    return data.value;
                }
                localStorage.removeItem('mab_delivery_' + key);
            }
            return null;
        },

        setCache: function(key, value) {
            if (this.options.cache.enabled) {
                localStorage.setItem('mab_delivery_' + key, JSON.stringify({
                    value: value,
                    timestamp: Date.now()
                }));
            }
        },

        clearCache: function() {
            if (this.options.cache.enabled) {
                Object.keys(localStorage).forEach(function(key) {
                    if (key.startsWith('mab_delivery_')) {
                        localStorage.removeItem(key);
                    }
                });
            }
            
            if (this.shippingCalculator && this.shippingCalculator.cache) {
                this.shippingCalculator.cache.clear();
            }
        }
    };
});