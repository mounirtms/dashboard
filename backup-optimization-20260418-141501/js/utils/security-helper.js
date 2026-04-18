/**
 * Security Helper Utilities
 * Provides sanitization and validation functions for checkout
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return {
        /**
         * Sanitize HTML to prevent XSS
         * @param {string} html - The HTML string to sanitize
         * @return {string} - Sanitized HTML
         */
        sanitizeHtml: function(html) {
            if (typeof html !== 'string') {
                return '';
            }
            
            // Create a temporary div to use browser's built-in sanitization
            var temp = document.createElement('div');
            temp.textContent = html;
            return temp.innerHTML;
        },

        /**
         * Escape HTML entities
         * @param {string} text - The text to escape
         * @return {string} - Escaped text
         */
        escapeHtml: function(text) {
            if (typeof text !== 'string') {
                return '';
            }
            
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
                '/': '&#x2F;'
            };
            
            return text.replace(/[&<>"'\/]/g, function(m) {
                return map[m];
            });
        },

        /**
         * Create safe HTML element
         * @param {string} tag - The HTML tag name
         * @param {object} attributes - The element attributes
         * @param {string} content - The text content (will be sanitized)
         * @return {jQuery} - jQuery wrapped element
         */
        createSafeElement: function(tag, attributes, content) {
            var $el = $('<' + tag + '>');
            
            if (attributes) {
                $.each(attributes, function(key, value) {
                    if (key === 'class') {
                        $el.addClass(value);
                    } else if (key === 'data') {
                        $.each(value, function(dataKey, dataValue) {
                            $el.data(dataKey, dataValue);
                        });
                    } else {
                        $el.attr(key, value);
                    }
                });
            }
            
            if (content) {
                $el.text(content); // Use text() instead of html() for safety
            }
            
            return $el;
        },

        /**
         * Validate input against allowed characters
         * @param {string} input - The input to validate
         * @param {string} type - The validation type (alphanumeric, numeric, etc.)
         * @return {boolean} - True if valid
         */
        validateInput: function(input, type) {
            if (typeof input !== 'string') {
                return false;
            }
            
            var patterns = {
                alphanumeric: /^[a-zA-Z0-9\s\-]+$/,
                numeric: /^[0-9]+$/,
                alpha: /^[a-zA-ZÀ-ÿ\s\-']+$/,  // Supports French characters
                email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                phone: /^[\d\s\+\-\(\)]+$/
            };
            
            return patterns[type] ? patterns[type].test(input) : false;
        },

        /**
         * Sanitize user input for safe display
         * @param {string} input - The input to sanitize
         * @return {string} - Sanitized input
         */
        sanitizeInput: function(input) {
            if (typeof input !== 'string') {
                return '';
            }
            
            // Remove script tags and event handlers
            input = input.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            input = input.replace(/on\w+\s*=\s*["'][^"']*["']/gi, '');
            input = input.replace(/javascript:/gi, '');
            
            return this.escapeHtml(input.trim());
        },

        /**
         * Validate Algerian wilaya ID
         * @param {number} wilayaId - The wilaya ID to validate
         * @return {boolean} - True if valid
         */
        isValidWilayaId: function(wilayaId) {
            var id = parseInt(wilayaId, 10);
            return !isNaN(id) && id >= 1 && id <= 58;
        },

        /**
         * Validate Algerian commune ID
         * @param {number} communeId - The commune ID to validate
         * @return {boolean} - True if valid
         */
        isValidCommuneId: function(communeId) {
            var id = parseInt(communeId, 10);
            return !isNaN(id) && id > 0;
        },

        /**
         * Create safe HTML content with mixed text and HTML
         * @param {object} parts - Object with safe text and allowed HTML
         * @return {string} - Safe HTML string
         */
        createSafeHtml: function(parts) {
            var html = '';
            
            $.each(parts, function(index, part) {
                if (part.type === 'text') {
                    html += this.escapeHtml(part.content);
                } else if (part.type === 'element') {
                    // Only allow specific safe elements
                    var allowedTags = ['span', 'div', 'strong', 'em', 'br'];
                    if (allowedTags.indexOf(part.tag) !== -1) {
                        html += '<' + part.tag;
                        if (part.class) {
                            html += ' class="' + this.escapeHtml(part.class) + '"';
                        }
                        html += '>' + (part.content ? this.escapeHtml(part.content) : '') + '</' + part.tag + '>';
                    }
                }
            }.bind(this));
            
            return html;
        },

        /**
         * Log message safely (production mode)
         * @param {string} level - Log level (info, warn, error)
         * @param {string} message - The message to log
         * @param {object} data - Additional data
         */
        log: function(level, message, data) {
            // In production, only log errors
            if (window.checkoutConfig && window.checkoutConfig.isProductionMode) {
                if (level === 'error') {
                    console.error('[Checkout]', message, data || '');
                }
            } else {
                // In development, log everything
                var logFn = console[level] || console.log;
                logFn.call(console, '[Checkout]', message, data || '');
            }
        }
    };
});
