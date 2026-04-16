/**
 * Performance Configuration - Optimized
 * Implements lazy loading, code splitting, and caching strategies
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return {
        /**
         * Configuration for module loading
         */
        config: {
            // Lazy load non-critical components
            lazyLoad: {
                enabled: true,
                threshold: 200, // pixels before viewport
                modules: [
                    'Mab_CheckoutCustomization/js/view/gift-card-form',
                    'Mab_CheckoutCustomization/js/view/discount'
                ]
            },
            
            // Cache configuration
            cache: {
                enabled: true,
                ttl: 3600000, // 1 hour in milliseconds
                keys: {
                    shippingMethods: 'mab_shipping_methods',
                    regions: 'mab_regions_cache',
                    communes: 'mab_communes_cache'
                }
            },
            
            // Performance settings
            performance: {
                debounceDelay: 300,
                throttleDelay: 150,
                animationDuration: 250,
                enableGPU: true,
                prefetchLinks: true
            },
            
            // Asset optimization
            assets: {
                // Preload critical resources
                preload: [
                    'Mab_CheckoutCustomization/css/checkout-critical',
                    'Mab_CheckoutCustomization/js/view/shipping-method-cards'
                ],
                // Defer non-critical resources
                defer: [
                    'Mab_CheckoutCustomization/css/gift-card-minimal',
                    'Mab_CheckoutCustomization/js/view/gift-card-form'
                ]
            }
        },

        /**
         * Initialize performance optimizations
         */
        init: function () {
            this.setupLazyLoading();
            this.setupCaching();
            this.setupPrefetch();
            this.optimizeAnimations();
            
            return this;
        },

        /**
         * Setup lazy loading for modules
         */
        setupLazyLoading: function () {
            if (!this.config.lazyLoad.enabled) {
                return;
            }

            var self = this;
            
            // Use Intersection Observer for lazy loading
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            var module = entry.target.dataset.lazyModule;
                            if (module) {
                                require([module], function (Component) {
                                    console.log('Lazy loaded:', module);
                                });
                                observer.unobserve(entry.target);
                            }
                        }
                    });
                }, {
                    rootMargin: self.config.lazyLoad.threshold + 'px'
                });

                // Observe elements with lazy-load attribute
                $('[data-lazy-module]').each(function () {
                    observer.observe(this);
                });
            }
        },

        /**
         * Setup localStorage caching
         */
        setupCaching: function () {
            if (!this.config.cache.enabled || !window.localStorage) {
                return;
            }

            var self = this;
            
            // Cache helper functions
            window.MabCache = {
                get: function (key) {
                    try {
                        var item = localStorage.getItem(key);
                        if (!item) return null;
                        
                        var data = JSON.parse(item);
                        var now = Date.now();
                        
                        // Check if expired
                        if (data.expiry && data.expiry < now) {
                            localStorage.removeItem(key);
                            return null;
                        }
                        
                        return data.value;
                    } catch (e) {
                        console.warn('Cache read error:', e);
                        return null;
                    }
                },
                
                set: function (key, value, ttl) {
                    try {
                        var data = {
                            value: value,
                            expiry: ttl ? Date.now() + ttl : null
                        };
                        localStorage.setItem(key, JSON.stringify(data));
                    } catch (e) {
                        console.warn('Cache write error:', e);
                    }
                },
                
                remove: function (key) {
                    try {
                        localStorage.removeItem(key);
                    } catch (e) {
                        console.warn('Cache remove error:', e);
                    }
                },
                
                clear: function () {
                    try {
                        // Clear only Mab-prefixed keys
                        Object.keys(localStorage).forEach(function (key) {
                            if (key.startsWith('mab_')) {
                                localStorage.removeItem(key);
                            }
                        });
                    } catch (e) {
                        console.warn('Cache clear error:', e);
                    }
                }
            };

            console.log('Caching system initialized');
        },

        /**
         * Setup prefetch for next likely navigation
         */
        setupPrefetch: function () {
            if (!this.config.performance.prefetchLinks) {
                return;
            }

            // Prefetch payment methods when on shipping step
            if ($('.checkout-shipping-method').length) {
                var link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = '/checkout/#payment';
                document.head.appendChild(link);
            }
        },

        /**
         * Optimize animations for better performance
         */
        optimizeAnimations: function () {
            if (!this.config.performance.enableGPU) {
                return;
            }

            // Add will-change hints for animated elements
            var style = document.createElement('style');
            style.textContent = `
                .shipping-card {
                    will-change: transform, box-shadow;
                }
                .shipping-card:not(:hover) {
                    will-change: auto;
                }
                .check-indicator {
                    will-change: transform, opacity;
                }
            `;
            document.head.appendChild(style);
        },

        /**
         * Get debounced function
         */
        debounce: function (func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function () {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait || this.config.performance.debounceDelay);
            };
        },

        /**
         * Get throttled function
         */
        throttle: function (func, limit) {
            var inThrottle;
            return function () {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function () {
                        inThrottle = false;
                    }, limit || this.config.performance.throttleDelay);
                }
            };
        },

        /**
         * Measure performance
         */
        measure: function (name, fn) {
            if (!window.performance || !window.performance.mark) {
                return fn();
            }

            var startMark = 'start-' + name;
            var endMark = 'end-' + name;
            
            performance.mark(startMark);
            var result = fn();
            performance.mark(endMark);
            
            try {
                performance.measure(name, startMark, endMark);
                var measure = performance.getEntriesByName(name)[0];
                console.log('[Performance] ' + name + ':', measure.duration.toFixed(2) + 'ms');
            } catch (e) {
                console.warn('Performance measure error:', e);
            }
            
            return result;
        }
    };
});
