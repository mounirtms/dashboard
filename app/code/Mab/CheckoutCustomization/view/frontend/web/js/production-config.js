/**
 * Production Build Configuration
 * Removes debug code and optimizes for production
 */
define([], function() {
    'use strict';

    return {
        /**
         * Check if running in production mode
         * @return {boolean}
         */
        isProduction: function() {
            // Check if explicitly set
            if (window.checkoutConfig && typeof window.checkoutConfig.isProductionMode !== 'undefined') {
                return window.checkoutConfig.isProductionMode;
            }
            
            // Auto-detect: production if hostname doesn't include 'dev', 'test', 'staging', 'localhost'
            var hostname = window.location.hostname.toLowerCase();
            var isDevEnvironment = hostname.includes('dev') || 
                                  hostname.includes('test') || 
                                  hostname.includes('staging') || 
                                  hostname.includes('localhost') ||
                                  hostname === '127.0.0.1';
            
            return !isDevEnvironment;
        },

        /**
         * Safe console wrapper that only logs in development
         * @param {string} level - log level (log, info, warn, error)
         * @param {*} args - arguments to log
         */
        log: function(level) {
            if (this.isProduction() && level !== 'error') {
                return; // Only log errors in production
            }

            var args = Array.prototype.slice.call(arguments, 1);
            var consoleFn = console[level] || console.log;
            
            if (consoleFn && consoleFn.apply) {
                consoleFn.apply(console, args);
            }
        },

        /**
         * Get optimization config
         * @return {object}
         */
        getConfig: function() {
            return {
                isProduction: this.isProduction(),
                enableDebugLogging: !this.isProduction(),
                enablePerformanceTracking: true,
                enableErrorReporting: this.isProduction(),
                
                // Cache settings
                cache: {
                    enabled: true,
                    ttl: this.isProduction() ? 3600000 : 300000, // 1 hour prod, 5 min dev
                    version: '2.0.0'
                },
                
                // Performance thresholds
                performance: {
                    slowOperationThreshold: 1000, // ms
                    memoryWarningThreshold: 90, // percent
                    domMutationThreshold: 100
                },
                
                // Error handling
                errorHandling: {
                    throttleWindow: 5000, // ms
                    maxErrorCount: 10,
                    reportToBackend: this.isProduction()
                }
            };
        }
    };
});
