/**
 * Mab_CheckoutCustomization - Performance Configuration
 * Optimized settings for production deployment
 */

// Performance tuning constants
var CheckoutPerformanceConfig = {
    // Debounce timers (in milliseconds)
    DEBOUNCE: {
        REGION_CHANGE: 300,      // Region/wilaya selection
        RATES_UPDATE: 150,       // Shipping rates update
        CARD_UPDATE: 100,        // Individual card update
        FORM_INPUT: 250,         // Form field input
        RESIZE: 200              // Window resize events
    },
    
    // Animation delays
    ANIMATION: {
        CARD_RENDER: 800,        // Initial card render delay
        REGION_RESPONSE: 800,    // Response to region change
        RETRY_INTERVAL: 500,     // Retry interval for missing elements
        TRANSITION_DURATION: 300 // CSS transition duration
    },
    
    // Cache settings
    CACHE: {
        ENABLE_DOM_CACHE: true,  // Cache jQuery DOM queries
        CLEAR_ON_RENDER: true    // Clear cache when re-rendering
    },
    
    // Feature flags
    FEATURES: {
        USE_RAF: true,                    // Use requestAnimationFrame
        USE_EVENT_DELEGATION: true,       // Use event delegation
        USE_WILL_CHANGE: true,            // Use CSS will-change hints
        ENABLE_CONSOLE_LOGS: true,        // Enable console debugging
        USE_PASSIVE_LISTENERS: false      // Use passive event listeners (future)
    },
    
    // Performance monitoring
    MONITORING: {
        TRACK_RENDER_TIME: false,         // Track card render performance
        TRACK_INTERACTION_TIME: false,    // Track user interactions
        LOG_PERFORMANCE_MARKS: false      // Use Performance API
    }
};

// Export for RequireJS
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return CheckoutPerformanceConfig;
    });
}

// Export for Node/CommonJS
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CheckoutPerformanceConfig;
}

// Global fallback
if (typeof window !== 'undefined') {
    window.CheckoutPerformanceConfig = CheckoutPerformanceConfig;
}
