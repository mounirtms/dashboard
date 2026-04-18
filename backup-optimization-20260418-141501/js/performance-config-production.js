/**
 * Production Performance Configuration
 */
define([], function() {
    'use strict';
    
    return {
        cache: {
            enabled: true,
            ttl: 600000, // 10 minutes for production
            maxSize: 50, // Max cached regions
            strategy: 'lru' // Least Recently Used
        },
        
        optimization: {
            preloadImages: true,
            lazyLoad: true,
            batchUpdates: true,
            useWebP: true,
            compressionLevel: 'high'
        },
        
        monitoring: {
            enabled: false, // Disable verbose monitoring in production
            metricsOnly: true,
            reportInterval: 300000 // 5 minutes
        },
        
        debug: {
            enabled: false,
            verbose: false,
            logErrors: true
        },
        
        network: {
            timeout: 10000,
            retries: 3,
            retryDelay: 1000
        }
    };
});
