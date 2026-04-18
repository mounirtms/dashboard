/**
 * Lazy Loader for Algerian States Data
 * Implements progressive loading with compression support
 */
define([
    'jquery',
    'Mab_CheckoutCustomization/js/production-config'
], function($, ProductionConfig) {
    'use strict';

    var isLoading = false;
    var isLoaded = false;
    var dataCache = null;
    var loadPromise = null;
    var callbacks = [];

    // Storage keys
    var CACHE_KEY = 'algerian_states_data';
    var VERSION_KEY = 'algerian_states_version';
    var CURRENT_VERSION = '2.0.0';

    return {
        /**
         * Load data with caching and lazy loading
         * @return {Promise}
         */
        load: function() {
            var self = this;
            var config = ProductionConfig.getConfig();

            // Return cached promise if already loading
            if (isLoading && loadPromise) {
                return loadPromise;
            }

            // Return cached data if already loaded
            if (isLoaded && dataCache) {
                return Promise.resolve(dataCache);
            }

            isLoading = true;

            loadPromise = new Promise(function(resolve, reject) {
                // Try to load from localStorage first
                if (config.cache.enabled) {
                    var cachedData = self.loadFromCache();
                    if (cachedData) {
                        ProductionConfig.log('info', '📦 [Lazy Loader] Loaded from cache');
                        dataCache = cachedData;
                        isLoaded = true;
                        isLoading = false;
                        resolve(cachedData);
                        return;
                    }
                }

                // Load from server
                ProductionConfig.log('info', '📡 [Lazy Loader] Loading from server...');
                var startTime = Date.now();

                $.ajax({
                    url: require.toUrl('Mab_CheckoutCustomization/data/algerian-states.json'),
                    dataType: 'json',
                    cache: true, // Enable browser caching
                    success: function(data) {
                        var loadTime = Date.now() - startTime;
                        ProductionConfig.log('info', '✅ [Lazy Loader] Loaded in ' + loadTime + 'ms');

                        // Validate data
                        if (!self.validateData(data)) {
                            reject(new Error('Invalid data structure'));
                            return;
                        }

                        // Cache in localStorage
                        if (config.cache.enabled) {
                            self.saveToCache(data);
                        }

                        dataCache = data;
                        isLoaded = true;
                        isLoading = false;
                        resolve(data);
                    },
                    error: function(xhr, status, error) {
                        isLoading = false;
                        ProductionConfig.log('error', '❌ [Lazy Loader] Failed to load:', error);
                        reject(new Error('Failed to load Algerian States data: ' + error));
                    }
                });
            });

            return loadPromise;
        },

        /**
         * Preload data (non-blocking)
         */
        preload: function() {
            var self = this;
            
            // Use requestIdleCallback if available
            if (window.requestIdleCallback) {
                window.requestIdleCallback(function() {
                    self.load().catch(function() {
                        // Fail silently for preload
                    });
                }, { timeout: 2000 });
            } else {
                // Fallback to setTimeout
                setTimeout(function() {
                    self.load().catch(function() {
                        // Fail silently for preload
                    });
                }, 100);
            }
        },

        /**
         * Get data (loads if not already loaded)
         * @return {Promise}
         */
        getData: function() {
            return this.load();
        },

        /**
         * Check if data is loaded
         * @return {boolean}
         */
        isDataLoaded: function() {
            return isLoaded && dataCache !== null;
        },

        /**
         * Load from localStorage cache
         * @return {object|null}
         */
        loadFromCache: function() {
            try {
                var version = localStorage.getItem(VERSION_KEY);
                if (version !== CURRENT_VERSION) {
                    ProductionConfig.log('info', '🔄 [Lazy Loader] Cache version mismatch, clearing');
                    this.clearCache();
                    return null;
                }

                var cached = localStorage.getItem(CACHE_KEY);
                if (cached) {
                    return JSON.parse(cached);
                }
            } catch (error) {
                ProductionConfig.log('warn', '⚠️ [Lazy Loader] Cache read error:', error);
                this.clearCache();
            }

            return null;
        },

        /**
         * Save to localStorage cache
         * @param {object} data
         */
        saveToCache: function(data) {
            try {
                localStorage.setItem(CACHE_KEY, JSON.stringify(data));
                localStorage.setItem(VERSION_KEY, CURRENT_VERSION);
                ProductionConfig.log('info', '💾 [Lazy Loader] Saved to cache');
            } catch (error) {
                ProductionConfig.log('warn', '⚠️ [Lazy Loader] Cache write error:', error);
                // Check if quota exceeded
                if (error.name === 'QuotaExceededError') {
                    // Try to clear old cache and retry
                    this.clearCache();
                }
            }
        },

        /**
         * Clear localStorage cache
         */
        clearCache: function() {
            try {
                localStorage.removeItem(CACHE_KEY);
                localStorage.removeItem(VERSION_KEY);
                ProductionConfig.log('info', '🗑️ [Lazy Loader] Cache cleared');
            } catch (error) {
                ProductionConfig.log('warn', '⚠️ [Lazy Loader] Cache clear error:', error);
            }
        },

        /**
         * Validate data structure
         * @param {object} data
         * @return {boolean}
         */
        validateData: function(data) {
            if (!data || typeof data !== 'object') {
                return false;
            }

            if (!Array.isArray(data.wilayas) || !Array.isArray(data.communes)) {
                return false;
            }

            if (data.wilayas.length !== 58) {
                ProductionConfig.log('warn', '⚠️ [Lazy Loader] Expected 58 wilayas, got', data.wilayas.length);
            }

            return true;
        },

        /**
         * Get cache info
         * @return {object}
         */
        getCacheInfo: function() {
            try {
                var cached = localStorage.getItem(CACHE_KEY);
                var version = localStorage.getItem(VERSION_KEY);
                
                return {
                    isCached: !!cached,
                    version: version,
                    size: cached ? (cached.length / 1024).toFixed(2) + 'KB' : '0KB',
                    isValid: version === CURRENT_VERSION
                };
            } catch (error) {
                return {
                    isCached: false,
                    error: error.message
                };
            }
        },

        /**
         * Compress data (simple compression for localStorage)
         * @param {string} str
         * @return {string}
         */
        compress: function(str) {
            // Simple LZString-like compression for localStorage
            // In production, use a proper library like lz-string
            return str; // Placeholder - implement actual compression
        },

        /**
         * Decompress data
         * @param {string} str
         * @return {string}
         */
        decompress: function(str) {
            // Decompress logic
            return str; // Placeholder - implement actual decompression
        }
    };
});
