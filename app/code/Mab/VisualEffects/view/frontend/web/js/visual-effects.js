/**
 * MAB Visual Effects - Main JavaScript Module
 * 
 * @author Mounir AB
 * @organization Techno DZ
 */
define([
    'jquery',
    'mage/storage',
    'mage/url',
    'Magento_Customer/js/customer-data'
], function ($, storage, urlBuilder, customerData) {
    'use strict';

    var VisualEffects = {
        config: {},
        initialized: false,
        effectQueue: [],
        activeEffects: new Set(),
        
        /**
         * Initialize visual effects
         */
        init: function(config) {
            this.config = $.extend({
                enabled: false,
                debug: false,
                performance_mode: false,
                mobile_optimized: true,
                animation_duration: 1000,
                effect_intensity: 'moderate'
            }, config);

            if (!this.config.enabled) {
                this.log('Visual effects disabled');
                return;
            }

            this.initialized = true;
            this.log('Visual effects initialized', this.config);
            
            this.setupEventListeners();
            this.loadEffectLibraries();
            this.initializeProgressBar();
            
            return this;
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            var self = this;
            
            // Cart update events
            $(document).on('ajax:addToCart', function(event, data) {
                self.triggerEffect('add_to_cart', data);
            });
            
            // Checkout step completion
            $(document).on('checkout:stepComplete', function(event, step) {
                self.triggerEffect('step_completion', {step: step});
            });
            
            // Free shipping achievement
            $(document).on('shipping:freeShippingAchieved', function(event, data) {
                self.triggerEffect('free_shipping_celebration', data);
            });
            
            // Cart milestone events
            $(document).on('cart:milestoneReached', function(event, data) {
                self.triggerEffect('milestone', data);
            });
            
            // Monitor cart data changes
            var cartData = customerData.get('cart');
            cartData.subscribe(function(updatedCart) {
                self.handleCartUpdate(updatedCart);
            });
        },

        /**
         * Load effect libraries dynamically
         */
        loadEffectLibraries: function() {
            var self = this;
            
            // Load confetti library
            if (this.needsEffect('confetti') || this.needsEffect('celebration')) {
                this.loadScript('https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js', function() {
                    self.log('Confetti library loaded');
                });
            }
            
            // Load particles library for advanced effects
            if (this.needsEffect('fireworks') || this.needsEffect('sparkles')) {
                this.loadScript('https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js', function() {
                    self.log('Particles library loaded');
                });
            }
        },

        /**
         * Check if a specific effect is needed
         */
        needsEffect: function(effectType) {
            var effects = this.config.shipping_effects || {};
            var cartEffects = this.config.cart_effects || {};
            var checkoutEffects = this.config.checkout_effects || {};
            
            return Object.values(effects).includes(effectType) ||
                   Object.values(cartEffects).includes(effectType) ||
                   Object.values(checkoutEffects).includes(effectType);
        },

        /**
         * Load external script
         */
        loadScript: function(src, callback) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = callback;
            document.head.appendChild(script);
        },

        /**
         * Initialize progress bar
         */
        initializeProgressBar: function() {
            if (!this.config.shipping_effects.progress_bar_enabled) {
                return;
            }
            
            var progressBarHtml = this.createProgressBarHtml();
            this.insertProgressBar(progressBarHtml);
            this.updateProgressBar();
        },

        /**
         * Create progress bar HTML
         */
        createProgressBarHtml: function() {
            var style = this.config.shipping_effects.progress_bar_style || 'modern';
            var styleClass = 'mab-progress-' + style;
            
            return `
                <div class="mab-free-shipping-progress ${styleClass}" id="mab-shipping-progress">
                    <div class="progress-container">
                        <div class="progress-bar" id="mab-progress-bar">
                            <div class="progress-fill" id="mab-progress-fill"></div>
                        </div>
                        <div class="progress-text" id="mab-progress-text">
                            Loading...
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Insert progress bar into DOM
         */
        insertProgressBar: function(html) {
            // Try different locations for the progress bar
            var targets = [
                '.checkout-cart-index .cart-summary',
                '.minicart-wrapper',
                '.page-header',
                '.page-main'
            ];
            
            for (var i = 0; i < targets.length; i++) {
                var $target = $(targets[i]);
                if ($target.length) {
                    $target.prepend(html);
                    this.log('Progress bar inserted at: ' + targets[i]);
                    break;
                }
            }
        },

        /**
         * Update progress bar
         */
        updateProgressBar: function() {
            var self = this;
            
            // Get shipping conditions
            this.getShippingConditions().then(function(conditions) {
                self.renderProgressBar(conditions);
            }).catch(function(error) {
                self.log('Error updating progress bar:', error);
            });
        },

        /**
         * Render progress bar with conditions
         */
        renderProgressBar: function(conditions) {
            var $progressFill = $('#mab-progress-fill');
            var $progressText = $('#mab-progress-text');
            
            if (!$progressFill.length || !$progressText.length) {
                return;
            }
            
            var percentage = Math.min(100, Math.max(0, conditions.progress_percentage || 0));
            var text = this.getProgressText(conditions);
            
            // Animate progress bar
            $progressFill.animate({
                width: percentage + '%'
            }, this.config.animation_duration);
            
            $progressText.text(text);
            
            // Add visual effects based on progress
            this.handleProgressEffects(conditions);
        },

        /**
         * Get progress text
         */
        getProgressText: function(conditions) {
            if (conditions.eligible) {
                return 'Free shipping unlocked! 🎉';
            } else if (conditions.amount_needed > 0) {
                return `Add ${this.formatCurrency(conditions.amount_needed)} for free shipping`;
            } else {
                return 'Free shipping available';
            }
        },

        /**
         * Handle progress-based effects
         */
        handleProgressEffects: function(conditions) {
            // Trigger celebration if free shipping is achieved
            if (conditions.eligible && conditions.visual_effects) {
                conditions.visual_effects.forEach(effect => {
                    if (effect.trigger === 'free_shipping_achieved') {
                        this.executeEffect(effect);
                    }
                });
            }
            
            // Handle threshold notifications
            if (conditions.notifications) {
                conditions.notifications.forEach(notification => {
                    this.showNotification(notification);
                });
            }
        },

        /**
         * Get shipping conditions from server
         */
        getShippingConditions: function() {
            var url = urlBuilder.build('mab_visual_effects/ajax/shippingConditions');
            
            return storage.get(url).then(function(response) {
                return response;
            });
        },

        /**
         * Handle cart update
         */
        handleCartUpdate: function(cartData) {
            this.log('Cart updated', cartData);
            
            // Update progress bar
            this.updateProgressBar();
            
            // Trigger cart update effect
            if (this.config.cart_effects.cart_update !== 'none') {
                this.triggerEffect('cart_update', cartData);
            }
            
            // Check for milestones
            this.checkMilestones(cartData);
        },

        /**
         * Check for milestone achievements
         */
        checkMilestones: function(cartData) {
            if (!this.config.cart_effects.milestone_effects) {
                return;
            }
            
            var cartTotal = parseFloat(cartData.subtotal_excl_tax) || 0;
            var milestones = this.config.cart_effects.milestones || [];
            
            milestones.forEach(milestone => {
                if (cartTotal >= milestone.amount && !this.isMilestoneTriggered(milestone.amount)) {
                    this.triggerMilestone(milestone);
                }
            });
        },

        /**
         * Check if milestone was already triggered
         */
        isMilestoneTriggered: function(amount) {
            var triggered = localStorage.getItem('mab_milestones_triggered') || '[]';
            var triggeredArray = JSON.parse(triggered);
            return triggeredArray.includes(amount);
        },

        /**
         * Mark milestone as triggered
         */
        markMilestoneTriggered: function(amount) {
            var triggered = localStorage.getItem('mab_milestones_triggered') || '[]';
            var triggeredArray = JSON.parse(triggered);
            triggeredArray.push(amount);
            localStorage.setItem('mab_milestones_triggered', JSON.stringify(triggeredArray));
        },

        /**
         * Trigger milestone effect
         */
        triggerMilestone: function(milestone) {
            this.markMilestoneTriggered(milestone.amount);
            this.executeEffect({
                type: 'milestone',
                effect: milestone.effect,
                amount: milestone.amount
            });
        },

        /**
         * Trigger effect
         */
        triggerEffect: function(effectType, data) {
            if (!this.initialized) {
                return;
            }
            
            var effect = this.getEffectConfig(effectType);
            if (!effect || effect === 'none') {
                return;
            }
            
            this.executeEffect({
                type: effectType,
                effect: effect,
                data: data
            });
        },

        /**
         * Get effect configuration
         */
        getEffectConfig: function(effectType) {
            var effects = {
                'add_to_cart': this.config.cart_effects.add_to_cart,
                'cart_update': this.config.cart_effects.cart_update,
                'step_completion': this.config.checkout_effects.step_completion,
                'free_shipping_celebration': this.config.shipping_effects.free_shipping_celebration,
                'order_success': this.config.checkout_effects.order_success
            };
            
            return effects[effectType] || 'none';
        },

        /**
         * Execute visual effect
         */
        executeEffect: function(effectConfig) {
            if (this.activeEffects.has(effectConfig.type)) {
                this.log('Effect already active:', effectConfig.type);
                return;
            }
            
            this.activeEffects.add(effectConfig.type);
            this.log('Executing effect:', effectConfig);
            
            var self = this;
            var duration = effectConfig.duration || this.config.animation_duration;
            
            switch (effectConfig.effect) {
                case 'confetti':
                    this.executeConfetti(effectConfig);
                    break;
                case 'fireworks':
                    this.executeFireworks(effectConfig);
                    break;
                case 'sparkles':
                    this.executeSparkles(effectConfig);
                    break;
                case 'bounce':
                    this.executeBounce(effectConfig);
                    break;
                case 'pulse':
                    this.executePulse(effectConfig);
                    break;
                case 'glow':
                    this.executeGlow(effectConfig);
                    break;
                case 'shake':
                    this.executeShake(effectConfig);
                    break;
                case 'zoom':
                    this.executeZoom(effectConfig);
                    break;
                case 'celebration':
                    this.executeCelebration(effectConfig);
                    break;
                default:
                    this.log('Unknown effect:', effectConfig.effect);
            }
            
            // Remove from active effects after duration
            setTimeout(function() {
                self.activeEffects.delete(effectConfig.type);
            }, duration);
        },

        /**
         * Execute confetti effect
         */
        executeConfetti: function(config) {
            if (typeof confetti === 'undefined') {
                this.log('Confetti library not loaded');
                return;
            }
            
            var intensity = this.getIntensityMultiplier();
            
            confetti({
                particleCount: Math.floor(100 * intensity),
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7']
            });
        },

        /**
         * Execute fireworks effect
         */
        executeFireworks: function(config) {
            if (typeof confetti === 'undefined') {
                this.log('Confetti library not loaded');
                return;
            }
            
            var intensity = this.getIntensityMultiplier();
            var count = Math.floor(3 * intensity);
            
            for (var i = 0; i < count; i++) {
                setTimeout(() => {
                    confetti({
                        particleCount: Math.floor(50 * intensity),
                        angle: 60,
                        spread: 55,
                        origin: { x: 0 },
                        colors: ['#ff6b6b', '#4ecdc4', '#45b7d1']
                    });
                    confetti({
                        particleCount: Math.floor(50 * intensity),
                        angle: 120,
                        spread: 55,
                        origin: { x: 1 },
                        colors: ['#96ceb4', '#ffeaa7', '#fd79a8']
                    });
                }, i * 200);
            }
        },

        /**
         * Execute sparkles effect
         */
        executeSparkles: function(config) {
            var $target = this.getEffectTarget(config);
            var sparkleCount = this.getIntensityMultiplier() * 10;
            
            for (var i = 0; i < sparkleCount; i++) {
                this.createSparkle($target);
            }
        },

        /**
         * Create sparkle element
         */
        createSparkle: function($target) {
            var $sparkle = $('<div class="mab-sparkle">✨</div>');
            var targetOffset = $target.offset();
            var targetWidth = $target.outerWidth();
            var targetHeight = $target.outerHeight();
            
            $sparkle.css({
                position: 'absolute',
                left: targetOffset.left + Math.random() * targetWidth,
                top: targetOffset.top + Math.random() * targetHeight,
                fontSize: Math.random() * 20 + 10 + 'px',
                zIndex: 9999,
                pointerEvents: 'none'
            });
            
            $('body').append($sparkle);
            
            $sparkle.animate({
                top: '-=50px',
                opacity: 0
            }, this.config.animation_duration, function() {
                $sparkle.remove();
            });
        },

        /**
         * Execute bounce effect
         */
        executeBounce: function(config) {
            var $target = this.getEffectTarget(config);
            
            $target.addClass('mab-bounce-effect');
            setTimeout(() => {
                $target.removeClass('mab-bounce-effect');
            }, this.config.animation_duration);
        },

        /**
         * Execute pulse effect
         */
        executePulse: function(config) {
            var $target = this.getEffectTarget(config);
            
            $target.addClass('mab-pulse-effect');
            setTimeout(() => {
                $target.removeClass('mab-pulse-effect');
            }, this.config.animation_duration);
        },

        /**
         * Execute glow effect
         */
        executeGlow: function(config) {
            var $target = this.getEffectTarget(config);
            
            $target.addClass('mab-glow-effect');
            setTimeout(() => {
                $target.removeClass('mab-glow-effect');
            }, this.config.animation_duration);
        },

        /**
         * Execute shake effect
         */
        executeShake: function(config) {
            var $target = this.getEffectTarget(config);
            
            $target.addClass('mab-shake-effect');
            setTimeout(() => {
                $target.removeClass('mab-shake-effect');
            }, this.config.animation_duration);
        },

        /**
         * Execute zoom effect
         */
        executeZoom: function(config) {
            var $target = this.getEffectTarget(config);
            
            $target.addClass('mab-zoom-effect');
            setTimeout(() => {
                $target.removeClass('mab-zoom-effect');
            }, this.config.animation_duration);
        },

        /**
         * Execute celebration effect (combination)
         */
        executeCelebration: function(config) {
            this.executeConfetti(config);
            setTimeout(() => {
                this.executeFireworks(config);
            }, 500);
            setTimeout(() => {
                this.executeSparkles(config);
            }, 1000);
        },

        /**
         * Get effect target element
         */
        getEffectTarget: function(config) {
            var selectors = [
                '.minicart-wrapper',
                '.cart-summary',
                '.checkout-progress',
                '.page-header',
                'body'
            ];
            
            for (var i = 0; i < selectors.length; i++) {
                var $target = $(selectors[i]);
                if ($target.length) {
                    return $target;
                }
            }
            
            return $('body');
        },

        /**
         * Get intensity multiplier based on configuration
         */
        getIntensityMultiplier: function() {
            var intensities = {
                'subtle': 0.5,
                'moderate': 1.0,
                'intense': 1.5,
                'extreme': 2.0
            };
            
            var intensity = intensities[this.config.effect_intensity] || 1.0;
            
            // Reduce intensity on mobile if optimized
            if (this.config.mobile_optimized && this.isMobile()) {
                intensity *= 0.7;
            }
            
            // Reduce intensity in performance mode
            if (this.config.performance_mode) {
                intensity *= 0.8;
            }
            
            return intensity;
        },

        /**
         * Check if device is mobile
         */
        isMobile: function() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },

        /**
         * Show notification
         */
        showNotification: function(notification) {
            var $notification = $(`
                <div class="mab-notification mab-notification-${notification.type}">
                    <span class="notification-icon">${this.getNotificationIcon(notification.icon)}</span>
                    <span class="notification-message">${notification.message}</span>
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.addClass('show');
            }, 100);
            
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Get notification icon
         */
        getNotificationIcon: function(iconType) {
            var icons = {
                'success': '✅',
                'info': 'ℹ️',
                'warning': '⚠️',
                'error': '❌'
            };
            
            return icons[iconType] || icons.info;
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('fr-DZ', {
                style: 'currency',
                currency: 'DZD'
            }).format(amount);
        },

        /**
         * Log debug messages
         */
        log: function(message, data) {
            if (this.config.debug) {
                console.log('[MAB Visual Effects]', message, data || '');
            }
        }
    };

    // Expose globally for testing and external access
    window.MabVisualEffects = VisualEffects;

    return VisualEffects;
});