/**
 * MAB Delivery Options - JavaScript Compatibility Fix
 * 
 * Fixes the "locations.each is not a function" error by providing
 * compatibility layer for different JavaScript frameworks
 * 
 * @category    Mab
 * @package     Mab_DeliveryOptions
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 */

define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    /**
     * Locations Compatibility Fix
     * 
     * Provides .each() method compatibility for locations objects
     * that might be arrays or other iterable structures
     */
    return {
        /**
         * Initialize the compatibility fix
         */
        init: function() {
            this.fixLocationsEach();
            this.fixArrayPrototype();
            this.addGlobalHelpers();
        },

        /**
         * Fix locations.each() method calls
         */
        fixLocationsEach: function() {
            // Override global locations if it exists and lacks .each method
            if (window.locations && typeof window.locations.each !== 'function') {
                this.addEachMethod(window.locations);
            }

            // Monitor for dynamically created locations objects
            const originalDefineProperty = Object.defineProperty;
            Object.defineProperty = function(obj, prop, descriptor) {
                if (prop === 'locations' && obj === window) {
                    if (descriptor.value && typeof descriptor.value.each !== 'function') {
                        this.addEachMethod(descriptor.value);
                    }
                }
                return originalDefineProperty.call(this, obj, prop, descriptor);
            }.bind(this);
        },

        /**
         * Add .each() method to an object
         * 
         * @param {Object|Array} obj - Object to add .each method to
         */
        addEachMethod: function(obj) {
            if (!obj || typeof obj.each === 'function') {
                return;
            }

            try {
                // For arrays
                if (Array.isArray(obj)) {
                    obj.each = function(callback) {
                        if (typeof callback === 'function') {
                            for (let i = 0; i < this.length; i++) {
                                callback.call(this[i], i, this[i]);
                            }
                        }
                        return this;
                    };
                }
                // For objects with numeric keys (array-like)
                else if (obj && typeof obj === 'object' && obj.length !== undefined) {
                    obj.each = function(callback) {
                        if (typeof callback === 'function') {
                            for (let i = 0; i < this.length; i++) {
                                if (this[i] !== undefined) {
                                    callback.call(this[i], i, this[i]);
                                }
                            }
                        }
                        return this;
                    };
                }
                // For regular objects
                else if (obj && typeof obj === 'object') {
                    obj.each = function(callback) {
                        if (typeof callback === 'function') {
                            Object.keys(this).forEach(function(key) {
                                if (key !== 'each') {
                                    callback.call(this[key], key, this[key]);
                                }
                            }.bind(this));
                        }
                        return this;
                    };
                }

                console.log('[MAB Delivery] Added .each() method to locations object');
            } catch (error) {
                console.warn('[MAB Delivery] Could not add .each() method:', error);
            }
        },

        /**
         * Fix Array prototype if needed
         */
        fixArrayPrototype: function() {
            // Ensure Array.prototype.each exists for compatibility
            if (!Array.prototype.each) {
                Array.prototype.each = function(callback) {
                    if (typeof callback === 'function') {
                        for (let i = 0; i < this.length; i++) {
                            callback.call(this[i], i, this[i]);
                        }
                    }
                    return this;
                };
            }
        },

        /**
         * Add global helper functions
         */
        addGlobalHelpers: function() {
            // Global function to safely call .each() on any object
            window.safeEach = function(obj, callback) {
                if (!obj || typeof callback !== 'function') {
                    return;
                }

                if (typeof obj.each === 'function') {
                    obj.each(callback);
                } else if (Array.isArray(obj)) {
                    obj.forEach(callback);
                } else if (obj && typeof obj === 'object') {
                    Object.keys(obj).forEach(function(key) {
                        callback.call(obj[key], key, obj[key]);
                    });
                }
            };

            // jQuery-style each for compatibility
            if (window.$ && !window.$.each) {
                window.$.each = function(obj, callback) {
                    window.safeEach(obj, callback);
                };
            }
        },

        /**
         * Monitor and fix locations objects dynamically
         */
        monitorLocations: function() {
            // Use MutationObserver to watch for DOM changes that might add locations
            if (window.MutationObserver) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            // Check if any new scripts might have created locations
                            mutation.addedNodes.forEach(function(node) {
                                if (node.tagName === 'SCRIPT') {
                                    setTimeout(function() {
                                        if (window.locations && typeof window.locations.each !== 'function') {
                                            this.addEachMethod(window.locations);
                                        }
                                    }.bind(this), 100);
                                }
                            }.bind(this));
                        }
                    }.bind(this));
                }.bind(this));

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        },

        /**
         * Error handler for locations-related errors
         */
        handleLocationErrors: function() {
            const originalError = window.onerror;
            
            window.onerror = function(message, source, lineno, colno, error) {
                if (message && message.includes('locations.each is not a function')) {
                    console.warn('[MAB Delivery] Caught locations.each error, attempting fix...');
                    
                    // Try to fix the locations object
                    if (window.locations) {
                        this.addEachMethod(window.locations);
                    }
                    
                    // Prevent the error from propagating
                    return true;
                }
                
                // Call original error handler
                if (originalError) {
                    return originalError.apply(this, arguments);
                }
                
                return false;
            }.bind(this);
        }
    };
});