/**
 * Performance Monitor Utility
 * Tracks and reports performance metrics for checkout
 */
define([
    'jquery'
], function ($) {
    'use strict';

    var metrics = {};
    var marks = {};
    var isEnabled = true;

    return {
        /**
         * Initialize performance monitoring
         */
        init: function() {
            // Check if performance API is available
            if (!window.performance || !window.performance.mark) {
                console.warn('[Performance] Performance API not available');
                isEnabled = false;
                return;
            }

            console.log('[Performance] Monitor initialized');
            this.markPageLoad();
        },

        /**
         * Mark page load time
         */
        markPageLoad: function() {
            if (!isEnabled) return;

            try {
                var perfData = window.performance.timing;
                var pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
                var domReadyTime = perfData.domContentLoadedEventEnd - perfData.navigationStart;

                this.recordMetric('page-load', pageLoadTime);
                this.recordMetric('dom-ready', domReadyTime);
            } catch (error) {
                console.warn('[Performance] Failed to mark page load:', error);
            }
        },

        /**
         * Start timing an operation
         * @param {string} name - Name of the operation
         */
        start: function(name) {
            if (!isEnabled) return;

            marks[name] = {
                startTime: Date.now(),
                endTime: null,
                duration: null
            };

            if (window.performance && window.performance.mark) {
                window.performance.mark(name + '-start');
            }
        },

        /**
         * End timing an operation
         * @param {string} name - Name of the operation
         * @return {number} - Duration in milliseconds
         */
        end: function(name) {
            if (!isEnabled || !marks[name]) return 0;

            marks[name].endTime = Date.now();
            marks[name].duration = marks[name].endTime - marks[name].startTime;

            if (window.performance && window.performance.mark) {
                window.performance.mark(name + '-end');
                
                try {
                    window.performance.measure(name, name + '-start', name + '-end');
                } catch (error) {
                    // Ignore measure errors
                }
            }

            this.recordMetric(name, marks[name].duration);

            return marks[name].duration;
        },

        /**
         * Measure function execution time
         * @param {string} name - Name of the operation
         * @param {function} fn - Function to measure
         * @return {*} - Function return value
         */
        measure: function(name, fn) {
            if (!isEnabled) return fn();

            this.start(name);
            var result = fn();
            this.end(name);

            return result;
        },

        /**
         * Measure async function execution time
         * @param {string} name - Name of the operation
         * @param {function} fn - Async function to measure
         * @return {Promise} - Promise that resolves with function result
         */
        measureAsync: function(name, fn) {
            if (!isEnabled) return fn();

            this.start(name);

            return Promise.resolve(fn())
                .then(function(result) {
                    this.end(name);
                    return result;
                }.bind(this))
                .catch(function(error) {
                    this.end(name);
                    throw error;
                }.bind(this));
        },

        /**
         * Record a metric
         * @param {string} name - Metric name
         * @param {number} value - Metric value
         * @param {object} metadata - Additional metadata
         */
        recordMetric: function(name, value, metadata) {
            if (!metrics[name]) {
                metrics[name] = {
                    values: [],
                    count: 0,
                    total: 0,
                    min: Infinity,
                    max: -Infinity,
                    avg: 0
                };
            }

            var metric = metrics[name];
            metric.values.push({
                value: value,
                timestamp: Date.now(),
                metadata: metadata || {}
            });

            metric.count++;
            metric.total += value;
            metric.min = Math.min(metric.min, value);
            metric.max = Math.max(metric.max, value);
            metric.avg = metric.total / metric.count;

            // Keep only last 100 values to avoid memory issues
            if (metric.values.length > 100) {
                metric.values.shift();
            }

            // Log slow operations
            if (value > 1000) { // More than 1 second
                console.warn('[Performance] Slow operation detected:', name, value + 'ms');
            }
        },

        /**
         * Get metric statistics
         * @param {string} name - Metric name
         * @return {object} - Metric statistics
         */
        getMetric: function(name) {
            return metrics[name] || null;
        },

        /**
         * Get all metrics
         * @return {object} - All metrics
         */
        getAllMetrics: function() {
            return metrics;
        },

        /**
         * Get summary report
         * @return {object} - Summary of all metrics
         */
        getSummary: function() {
            var summary = {
                totalOperations: 0,
                slowOperations: 0,
                metrics: {}
            };

            $.each(metrics, function(name, metric) {
                summary.totalOperations += metric.count;
                summary.slowOperations += metric.values.filter(function(v) {
                    return v.value > 1000;
                }).length;

                summary.metrics[name] = {
                    count: metric.count,
                    avg: Math.round(metric.avg),
                    min: Math.round(metric.min),
                    max: Math.round(metric.max)
                };
            });

            return summary;
        },

        /**
         * Log performance summary to console
         */
        logSummary: function() {
            var summary = this.getSummary();

            console.group('📊 Performance Summary');
            console.log('Total Operations:', summary.totalOperations);
            console.log('Slow Operations (>1s):', summary.slowOperations);
            console.table(summary.metrics);
            console.groupEnd();
        },

        /**
         * Send metrics to backend
         */
        sendMetrics: function() {
            if (!window.checkoutConfig || !window.checkoutConfig.performanceTrackingEnabled) {
                return;
            }

            var summary = this.getSummary();

            $.ajax({
                url: '/rest/V1/checkout/performance-metrics',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    metrics: summary,
                    userAgent: navigator.userAgent,
                    url: window.location.href,
                    timestamp: new Date().toISOString()
                }),
                success: function() {
                    console.log('[Performance] Metrics sent successfully');
                },
                error: function() {
                    console.warn('[Performance] Failed to send metrics');
                }
            });
        },

        /**
         * Monitor DOM mutations (for detecting excessive reflows)
         */
        monitorDomMutations: function() {
            if (!isEnabled || !window.MutationObserver) return;

            var mutationCount = 0;
            var mutationThreshold = 100;

            var observer = new MutationObserver(function(mutations) {
                mutationCount += mutations.length;

                if (mutationCount > mutationThreshold) {
                    console.warn('[Performance] Excessive DOM mutations detected:', mutationCount);
                    mutationCount = 0; // Reset counter
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true
            });
        },

        /**
         * Monitor memory usage (if available)
         */
        checkMemoryUsage: function() {
            if (!window.performance || !window.performance.memory) {
                return null;
            }

            var memory = window.performance.memory;

            return {
                usedJSHeapSize: Math.round(memory.usedJSHeapSize / 1048576) + ' MB',
                totalJSHeapSize: Math.round(memory.totalJSHeapSize / 1048576) + ' MB',
                jsHeapSizeLimit: Math.round(memory.jsHeapSizeLimit / 1048576) + ' MB',
                percentage: Math.round((memory.usedJSHeapSize / memory.jsHeapSizeLimit) * 100) + '%'
            };
        },

        /**
         * Log memory usage
         */
        logMemoryUsage: function() {
            var memory = this.checkMemoryUsage();

            if (memory) {
                console.log('[Performance] Memory Usage:', memory);

                if (parseInt(memory.percentage) > 90) {
                    console.warn('[Performance] High memory usage detected:', memory.percentage);
                }
            }
        },

        /**
         * Clear all metrics
         */
        clear: function() {
            metrics = {};
            marks = {};
            console.log('[Performance] Metrics cleared');
        },

        /**
         * Enable/disable performance monitoring
         * @param {boolean} enabled - Enable or disable
         */
        setEnabled: function(enabled) {
            isEnabled = enabled;
            console.log('[Performance] Monitoring ' + (enabled ? 'enabled' : 'disabled'));
        }
    };
});
