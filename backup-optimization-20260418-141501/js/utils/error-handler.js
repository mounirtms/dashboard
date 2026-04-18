/**
 * Error Handler Utility
 * Centralized error handling for checkout components
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    var errorCounts = {};
    var errorThrottles = {};

    return {
        /**
         * Handle component error
         * @param {string} component - Component name
         * @param {string} action - Action that failed
         * @param {Error} error - The error object
         * @param {object} options - Additional options
         */
        handleError: function(component, action, error, options) {
            options = options || {};
            
            var errorKey = component + ':' + action;
            
            // Throttle similar errors
            if (this.isThrottled(errorKey)) {
                return;
            }
            
            // Track error count
            errorCounts[errorKey] = (errorCounts[errorKey] || 0) + 1;
            
            // Log error
            console.error('[' + component + '] Error in ' + action + ':', error);
            
            // Show user-friendly message if not silent
            if (!options.silent) {
                this.showUserMessage(component, action, error, options);
            }
            
            // Send to error tracking service (if configured)
            this.reportError(component, action, error, options);
            
            // Execute callback if provided
            if (options.onError && typeof options.onError === 'function') {
                options.onError(error);
            }
        },

        /**
         * Check if error should be throttled
         * @param {string} errorKey - The error identifier
         * @return {boolean} - True if throttled
         */
        isThrottled: function(errorKey) {
            var now = Date.now();
            var lastError = errorThrottles[errorKey];
            
            if (lastError && (now - lastError < 5000)) {
                return true; // Throttle errors within 5 seconds
            }
            
            errorThrottles[errorKey] = now;
            return false;
        },

        /**
         * Show user-friendly error message
         * @param {string} component - Component name
         * @param {string} action - Action that failed
         * @param {Error} error - The error object
         * @param {object} options - Additional options
         */
        showUserMessage: function(component, action, error, options) {
            var message = options.userMessage || this.getUserMessage(component, action);
            
            var $messageContainer = this.getOrCreateMessageContainer();
            
            var $message = $('<div>', {
                class: 'message message-error error',
                html: '<span class="error-icon">❌</span> ' + this.escapeHtml(message)
            });
            
            $messageContainer.html($message).show();
            
            // Auto-hide after 8 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 8000);
        },

        /**
         * Get user-friendly error message
         * @param {string} component - Component name
         * @param {string} action - Action that failed
         * @return {string} - User-friendly message
         */
        getUserMessage: function(component, action) {
            var messages = {
                'algerian-states:loadData': $t('Impossible de charger les données géographiques. Veuillez actualiser la page.'),
                'algerian-states:populate': $t('Erreur lors du chargement des options. Veuillez réessayer.'),
                'shipping-cards:loadRates': $t('Impossible de charger les méthodes de livraison. Veuillez vérifier votre adresse.'),
                'shipping-cards:selectMethod': $t('Erreur lors de la sélection de la méthode de livraison. Veuillez réessayer.'),
                'default': $t('Une erreur s\'est produite. Veuillez actualiser la page ou contacter le support.')
            };
            
            var key = component + ':' + action;
            return messages[key] || messages['default'];
        },

        /**
         * Get or create message container
         * @return {jQuery} - Message container element
         */
        getOrCreateMessageContainer: function() {
            var $container = $('.checkout-error-messages');
            
            if ($container.length === 0) {
                $container = $('<div>', {
                    class: 'checkout-error-messages'
                }).prependTo('.opc-wrapper');
            }
            
            return $container;
        },

        /**
         * Report error to tracking service
         * @param {string} component - Component name
         * @param {string} action - Action that failed
         * @param {Error} error - The error object
         * @param {object} options - Additional options
         */
        reportError: function(component, action, error, options) {
            // Only report if error tracking is configured
            if (window.checkoutConfig && window.checkoutConfig.errorTrackingEnabled) {
                var errorData = {
                    component: component,
                    action: action,
                    message: error.message,
                    stack: error.stack,
                    timestamp: new Date().toISOString(),
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    count: errorCounts[component + ':' + action]
                };
                
                // Send to tracking endpoint
                this.sendErrorReport(errorData);
            }
        },

        /**
         * Send error report to backend
         * @param {object} errorData - Error data to send
         */
        sendErrorReport: function(errorData) {
            $.ajax({
                url: '/rest/V1/checkout/error-report',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(errorData),
                success: function() {
                    // Error reported successfully
                },
                error: function() {
                    // Failed to report error (fail silently)
                }
            });
        },

        /**
         * Handle network error
         * @param {object} xhr - XMLHttpRequest object
         * @param {string} component - Component name
         */
        handleNetworkError: function(xhr, component) {
            var statusMessages = {
                0: $t('Impossible de se connecter au serveur. Vérifiez votre connexion Internet.'),
                404: $t('Ressource non trouvée. Veuillez contacter le support.'),
                500: $t('Erreur serveur. Veuillez réessayer dans quelques instants.'),
                503: $t('Service temporairement indisponible. Veuillez réessayer.')
            };
            
            var message = statusMessages[xhr.status] || $t('Erreur réseau. Veuillez réessayer.');
            
            this.showUserMessage(component, 'network', new Error(message), {
                userMessage: message
            });
        },

        /**
         * Escape HTML to prevent XSS
         * @param {string} text - Text to escape
         * @return {string} - Escaped text
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        },

        /**
         * Handle fallback when primary method fails
         * @param {string} component - Component name
         * @param {function} fallbackFn - Fallback function to execute
         */
        handleFallback: function(component, fallbackFn) {
            console.warn('[' + component + '] Using fallback method');
            
            try {
                fallbackFn();
            } catch (error) {
                this.handleError(component, 'fallback', error, {
                    userMessage: $t('Impossible de charger les données. Utilisation du mode simplifié.')
                });
            }
        },

        /**
         * Get error statistics
         * @return {object} - Error statistics
         */
        getStats: function() {
            return {
                totalErrors: Object.keys(errorCounts).length,
                errorCounts: errorCounts,
                topErrors: this.getTopErrors(5)
            };
        },

        /**
         * Get top errors by frequency
         * @param {number} limit - Number of errors to return
         * @return {array} - Top errors
         */
        getTopErrors: function(limit) {
            var errors = Object.keys(errorCounts).map(function(key) {
                return {
                    error: key,
                    count: errorCounts[key]
                };
            });
            
            errors.sort(function(a, b) {
                return b.count - a.count;
            });
            
            return errors.slice(0, limit);
        },

        /**
         * Clear error statistics
         */
        clearStats: function() {
            errorCounts = {};
            errorThrottles = {};
        }
    };
});
