/**
 * Checkout Analytics Tracker
 * Tracks user behavior and conversion funnel in checkout
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote'
], function ($, quote) {
    'use strict';

    var CheckoutAnalytics = {
        
        /**
         * Configuration
         */
        config: {
            enabled: true,
            debug: false,
            sessionKey: 'mab_checkout_session',
            events: []
        },

        /**
         * Session data
         */
        session: {
            sessionId: null,
            startTime: null,
            currentStep: null,
            events: [],
            errors: []
        },

        /**
         * Initialize analytics
         */
        init: function() {
            var self = this;
            
            if (!self.config.enabled) {
                return;
            }
            
            // Create or restore session
            self.initSession();
            
            // Track page view
            self.trackPageView();
            
            // Setup event listeners
            self.setupEventListeners();
            
            // Track checkout start
            self.track('checkout_started', {
                cart_items: quote.getItems().length,
                cart_total: quote.getTotals()().grand_total
            });
            
            console.log('Checkout analytics initialized');
        },

        /**
         * Initialize session
         */
        initSession: function() {
            var self = this;
            var stored = sessionStorage.getItem(self.config.sessionKey);
            
            if (stored) {
                self.session = JSON.parse(stored);
            } else {
                self.session = {
                    sessionId: self.generateSessionId(),
                    startTime: new Date().toISOString(),
                    currentStep: 'shipping',
                    events: [],
                    errors: []
                };
                self.saveSession();
            }
        },

        /**
         * Generate unique session ID
         */
        generateSessionId: function() {
            return 'checkout_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Save session to storage
         */
        saveSession: function() {
            try {
                sessionStorage.setItem(this.config.sessionKey, JSON.stringify(this.session));
            } catch (error) {
                console.warn('Failed to save analytics session:', error);
            }
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            var self = this;
            
            // Track step changes
            quote.shippingAddress.subscribe(function(address) {
                if (address && address.regionId) {
                    self.track('shipping_address_entered', {
                        region_id: address.regionId,
                        region: address.region,
                        city: address.city
                    });
                }
            });
            
            // Track shipping method selection
            quote.shippingMethod.subscribe(function(method) {
                if (method) {
                    self.track('shipping_method_selected', {
                        carrier_code: method.carrier_code,
                        method_code: method.method_code,
                        amount: method.amount,
                        method_title: method.method_title
                    });
                }
            });
            
            // Track payment method selection
            quote.paymentMethod.subscribe(function(method) {
                if (method) {
                    self.track('payment_method_selected', {
                        method: method.method,
                        title: method.title
                    });
                }
            });
            
            // Track field interactions
            self.trackFieldInteractions();
            
            // Track errors
            self.trackErrors();
            
            // Track time spent
            self.trackTimeSpent();
        },

        /**
         * Track field interactions
         */
        trackFieldInteractions: function() {
            var self = this;
            var fieldTimings = {};
            
            $('.checkout-index-index input, .checkout-index-index select').on('focus', function() {
                var fieldName = $(this).attr('name');
                if (fieldName) {
                    fieldTimings[fieldName] = Date.now();
                }
            });
            
            $('.checkout-index-index input, .checkout-index-index select').on('blur', function() {
                var fieldName = $(this).attr('name');
                if (fieldName && fieldTimings[fieldName]) {
                    var duration = Date.now() - fieldTimings[fieldName];
                    
                    self.track('field_interaction', {
                        field_name: fieldName,
                        duration_ms: duration,
                        has_value: !!$(this).val()
                    });
                    
                    delete fieldTimings[fieldName];
                }
            });
        },

        /**
         * Track errors
         */
        trackErrors: function() {
            var self = this;
            
            // Track validation errors
            $(document).on('ajaxError', function(event, jqXHR, settings, error) {
                self.track('ajax_error', {
                    url: settings.url,
                    status: jqXHR.status,
                    error: error
                });
            });
            
            // Track form validation errors
            $('.checkout-index-index').on('invalid-form.validate', function() {
                var errors = [];
                $('.field._error').each(function() {
                    var fieldName = $(this).find('input, select').attr('name');
                    var errorMsg = $(this).find('.mage-error').text();
                    errors.push({
                        field: fieldName,
                        message: errorMsg
                    });
                });
                
                self.track('validation_errors', {
                    errors: errors,
                    count: errors.length
                });
            });
        },

        /**
         * Track time spent on each step
         */
        trackTimeSpent: function() {
            var self = this;
            var stepStartTime = Date.now();
            var currentStep = 'shipping';
            
            // Track when user leaves page
            $(window).on('beforeunload', function() {
                var duration = Date.now() - stepStartTime;
                self.track('step_time', {
                    step: currentStep,
                    duration_ms: duration,
                    duration_sec: Math.round(duration / 1000)
                });
                
                // Track session end
                var sessionDuration = Date.now() - new Date(self.session.startTime).getTime();
                self.track('checkout_session_end', {
                    total_duration_ms: sessionDuration,
                    total_duration_min: Math.round(sessionDuration / 60000),
                    total_events: self.session.events.length
                });
            });
        },

        /**
         * Track page view
         */
        trackPageView: function() {
            this.track('page_view', {
                page: window.location.pathname,
                referrer: document.referrer,
                timestamp: new Date().toISOString()
            });
        },

        /**
         * Main track method
         */
        track: function(eventName, data) {
            var self = this;
            
            if (!self.config.enabled) {
                return;
            }
            
            var event = {
                event: eventName,
                data: data || {},
                timestamp: new Date().toISOString(),
                session_id: self.session.sessionId
            };
            
            // Add to session events
            self.session.events.push(event);
            self.saveSession();
            
            // Send to analytics platforms
            self.sendToAnalytics(eventName, data);
            
            if (self.config.debug) {
                console.log('Analytics event:', eventName, data);
            }
        },

        /**
         * Send to analytics platforms
         */
        sendToAnalytics: function(eventName, data) {
            var self = this;
            
            // Google Analytics 4
            if (window.gtag) {
                try {
                    window.gtag('event', eventName, data);
                } catch (error) {
                    console.warn('GA4 tracking error:', error);
                }
            }
            
            // Facebook Pixel
            if (window.fbq) {
                try {
                    window.fbq('trackCustom', eventName, data);
                } catch (error) {
                    console.warn('Facebook Pixel tracking error:', error);
                }
            }
            
            // Google Tag Manager
            if (window.dataLayer) {
                try {
                    window.dataLayer.push({
                        event: eventName,
                        eventData: data
                    });
                } catch (error) {
                    console.warn('GTM tracking error:', error);
                }
            }
            
            // Custom analytics endpoint
            self.sendToCustomEndpoint(eventName, data);
        },

        /**
         * Send to custom analytics endpoint
         */
        sendToCustomEndpoint: function(eventName, data) {
            // Only send important events to reduce server load
            var importantEvents = [
                'checkout_started',
                'shipping_method_selected',
                'payment_method_selected',
                'checkout_completed',
                'checkout_abandoned'
            ];
            
            if (importantEvents.indexOf(eventName) === -1) {
                return;
            }
            
            $.ajax({
                url: '/rest/V1/checkout-analytics/track',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    event: eventName,
                    data: data,
                    session_id: this.session.sessionId,
                    timestamp: new Date().toISOString()
                }),
                success: function() {
                    console.log('Analytics sent to server:', eventName);
                },
                error: function(error) {
                    console.warn('Failed to send analytics to server:', error);
                }
            });
        },

        /**
         * Get session summary
         */
        getSessionSummary: function() {
            var self = this;
            var now = Date.now();
            var startTime = new Date(self.session.startTime).getTime();
            var duration = now - startTime;
            
            return {
                session_id: self.session.sessionId,
                start_time: self.session.startTime,
                duration_ms: duration,
                duration_min: Math.round(duration / 60000),
                total_events: self.session.events.length,
                current_step: self.session.currentStep,
                errors: self.session.errors
            };
        },

        /**
         * Export session data for debugging
         */
        exportSession: function() {
            console.log('=== Checkout Analytics Session ===');
            console.log('Summary:', this.getSessionSummary());
            console.log('Events:', this.session.events);
            console.log('Full session:', this.session);
            
            return this.session;
        }
    };

    // Auto-initialize on checkout page
    if ($('body').hasClass('checkout-index-index')) {
        $(document).ready(function() {
            CheckoutAnalytics.init();
        });
    }
    
    // Make available globally for debugging
    window.CheckoutAnalytics = CheckoutAnalytics;

    return CheckoutAnalytics;
});
