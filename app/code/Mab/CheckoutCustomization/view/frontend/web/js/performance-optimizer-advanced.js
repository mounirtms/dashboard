/**
 * Performance Optimizer for Shipping Method Cards
 * Implements advanced caching, debouncing, and optimization techniques
 */
define([
    'jquery',
    'ko'
], function ($, ko) {
    'use strict';

    var PerformanceOptimizer = {
        // Cache configuration
        cache: {
            enabled: true,
            ttl: 300000, // 5 minutes
            storage: {},
            prefix: 'shipping_cards_'
        },

        // Performance metrics
        metrics: {
            loadTime: 0,
            renderTime: 0,
            cacheHits: 0,
            cacheMisses: 0
        },

        /**
         * Initialize performance optimizer
         */
        init: function() {
            console.log('🚀 [Performance] Optimizer initialized');
            this.setupPerformanceObserver();
            this.preloadCriticalAssets();
            return this;
        },

        /**
         * Setup Performance Observer
         */
        setupPerformanceObserver: function() {
            if ('PerformanceObserver' in window) {
                try {
                    var observer = new PerformanceObserver(function(list) {
                        list.getEntries().forEach(function(entry) {
                            if (entry.name.includes('shipping') || entry.name.includes('mptablerate')) {
                                console.log('⚡ [Performance]', entry.name, ':', entry.duration.toFixed(2) + 'ms');
                            }
                        });
                    });
                    observer.observe({ entryTypes: ['measure', 'navigation', 'resource'] });
                } catch (e) {
                    console.warn('⚠️ [Performance] PerformanceObserver not supported');
                }
            }
        },

        /**
         * Preload critical assets
         */
        preloadCriticalAssets: function() {
            var logos = [
                'https://dev.technostationery.com/media/mageplaza/tablerate/techno.png',
                'https://dev.technostationery.com/media/mageplaza/tablerate/yalidine-logo.jpg'
            ];

            logos.forEach(function(url) {
                var img = new Image();
                img.src = url;
                console.log('🖼️ [Performance] Preloading:', url);
            });
        },

        /**
         * Cache shipping rates
         */
        cacheRates: function(regionId, rates) {
            if (!this.cache.enabled) return;

            var key = this.cache.prefix + 'region_' + regionId;
            var data = {
                rates: rates,
                timestamp: Date.now(),
                ttl: this.cache.ttl
            };

            try {
                localStorage.setItem(key, JSON.stringify(data));
                sessionStorage.setItem(key, JSON.stringify(data));
                this.cache.storage[key] = data;
                console.log('💾 [Performance] Cached rates for region:', regionId);
            } catch (e) {
                console.warn('⚠️ [Performance] Cache write failed:', e.message);
            }
        },

        /**
         * Get cached rates
         */
        getCachedRates: function(regionId) {
            if (!this.cache.enabled) return null;

            var key = this.cache.prefix + 'region_' + regionId;
            var now = Date.now();

            // Try memory cache first (fastest)
            var memData = this.cache.storage[key];
            if (memData && (now - memData.timestamp) < memData.ttl) {
                this.metrics.cacheHits++;
                console.log('⚡ [Performance] Cache HIT (memory):', regionId);
                return memData.rates;
            }

            // Try sessionStorage (fast)
            try {
                var sessionData = sessionStorage.getItem(key);
                if (sessionData) {
                    var parsed = JSON.parse(sessionData);
                    if ((now - parsed.timestamp) < parsed.ttl) {
                        this.cache.storage[key] = parsed; // Promote to memory
                        this.metrics.cacheHits++;
                        console.log('⚡ [Performance] Cache HIT (session):', regionId);
                        return parsed.rates;
                    }
                }
            } catch (e) {
                console.warn('⚠️ [Performance] Session cache read failed');
            }

            // Try localStorage (slower but persistent)
            try {
                var localData = localStorage.getItem(key);
                if (localData) {
                    var parsedLocal = JSON.parse(localData);
                    if ((now - parsedLocal.timestamp) < parsedLocal.ttl) {
                        this.cache.storage[key] = parsedLocal; // Promote to memory
                        sessionStorage.setItem(key, localData); // Promote to session
                        this.metrics.cacheHits++;
                        console.log('⚡ [Performance] Cache HIT (local):', regionId);
                        return parsedLocal.rates;
                    }
                }
            } catch (e) {
                console.warn('⚠️ [Performance] Local cache read failed');
            }

            this.metrics.cacheMisses++;
            console.log('❌ [Performance] Cache MISS:', regionId);
            return null;
        },

        /**
         * Clear expired cache entries
         */
        clearExpiredCache: function() {
            var now = Date.now();
            var cleared = 0;

            // Clear memory cache
            Object.keys(this.cache.storage).forEach(function(key) {
                var data = this.cache.storage[key];
                if ((now - data.timestamp) >= data.ttl) {
                    delete this.cache.storage[key];
                    cleared++;
                }
            }.bind(this));

            // Clear localStorage
            try {
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && key.startsWith(this.cache.prefix)) {
                        var data = JSON.parse(localStorage.getItem(key));
                        if ((now - data.timestamp) >= data.ttl) {
                            localStorage.removeItem(key);
                            cleared++;
                        }
                    }
                }
            } catch (e) {
                console.warn('⚠️ [Performance] Cache cleanup failed');
            }

            if (cleared > 0) {
                console.log('🧹 [Performance] Cleared', cleared, 'expired cache entries');
            }
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Throttle function
         */
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() { inThrottle = false; }, limit);
                }
            };
        },

        /**
         * Measure execution time
         */
        measure: function(name, func) {
            var start = performance.now();
            var result = func();
            var duration = performance.now() - start;
            console.log('⏱️ [Performance]', name, ':', duration.toFixed(2) + 'ms');
            return result;
        },

        /**
         * Async measure
         */
        measureAsync: function(name, asyncFunc) {
            var start = performance.now();
            return asyncFunc().then(function(result) {
                var duration = performance.now() - start;
                console.log('⏱️ [Performance]', name, ':', duration.toFixed(2) + 'ms');
                return result;
            });
        },

        /**
         * Get performance metrics
         */
        getMetrics: function() {
            return {
                loadTime: this.metrics.loadTime,
                renderTime: this.metrics.renderTime,
                cacheHits: this.metrics.cacheHits,
                cacheMisses: this.metrics.cacheMisses,
                cacheHitRate: this.metrics.cacheHits / (this.metrics.cacheHits + this.metrics.cacheMisses) * 100
            };
        },

        /**
         * Log performance report
         */
        report: function() {
            var metrics = this.getMetrics();
            console.log('═══════════════════════════════════════════════════════════════');
            console.log('📊 PERFORMANCE REPORT');
            console.log('═══════════════════════════════════════════════════════════════');
            console.log('Load Time:', metrics.loadTime.toFixed(2) + 'ms');
            console.log('Render Time:', metrics.renderTime.toFixed(2) + 'ms');
            console.log('Cache Hits:', metrics.cacheHits);
            console.log('Cache Misses:', metrics.cacheMisses);
            console.log('Cache Hit Rate:', metrics.cacheHitRate.toFixed(1) + '%');
            console.log('═══════════════════════════════════════════════════════════════');
        },

        /**
         * Optimize image loading
         */
        optimizeImage: function(img, options) {
            options = options || {};
            
            // Lazy loading
            if ('loading' in HTMLImageElement.prototype) {
                img.loading = options.lazy !== false ? 'lazy' : 'eager';
            }

            // Decode async
            if ('decode' in img) {
                img.decode().catch(function(e) {
                    console.warn('⚠️ [Performance] Image decode failed:', e);
                });
            }

            // WebP support
            if (this.supportsWebP() && options.webp) {
                var webpSrc = img.src.replace(/\.(jpg|jpeg|png)$/, '.webp');
                img.src = webpSrc;
            }

            return img;
        },

        /**
         * Check WebP support
         */
        supportsWebP: function() {
            if (!this._webpSupport) {
                var elem = document.createElement('canvas');
                if (!!(elem.getContext && elem.getContext('2d'))) {
                    this._webpSupport = elem.toDataURL('image/webp').indexOf('data:image/webp') === 0;
                } else {
                    this._webpSupport = false;
                }
            }
            return this._webpSupport;
        },

        /**
         * Batch DOM updates
         */
        batchUpdate: function(updates) {
            if ('requestAnimationFrame' in window) {
                requestAnimationFrame(function() {
                    updates.forEach(function(update) {
                        update();
                    });
                });
            } else {
                updates.forEach(function(update) {
                    update();
                });
            }
        },

        /**
         * Intersection Observer for lazy rendering
         */
        observeVisibility: function(element, callback) {
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            callback(entry.target);
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px'
                });
                observer.observe(element);
            } else {
                // Fallback: execute immediately
                callback(element);
            }
        },

        /**
         * Memory cleanup
         */
        cleanup: function() {
            // Clear in-memory cache
            this.cache.storage = {};
            
            // Clear event listeners (if any)
            console.log('🧹 [Performance] Cleanup completed');
        }
    };

    // Auto-initialize
    $(document).ready(function() {
        PerformanceOptimizer.init();
        
        // Cleanup expired cache periodically
        setInterval(function() {
            PerformanceOptimizer.clearExpiredCache();
        }, 60000); // Every minute
    });

    return PerformanceOptimizer;
});
