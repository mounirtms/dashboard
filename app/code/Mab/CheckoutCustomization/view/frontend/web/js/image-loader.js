/**
 * Optimized Image Loader for Carrier Logos
 * Features:
 * - Lazy loading with IntersectionObserver
 * - WebP support detection and fallback
 * - Loading placeholders with blur-up effect
 * - Error handling with retry logic
 * - Size optimization hints
 */
define([
    'jquery'
], function ($) {
    'use strict';

    var ImageLoader = {
        
        /**
         * Configuration
         */
        config: {
            rootMargin: '50px',
            threshold: 0.01,
            retryAttempts: 2,
            retryDelay: 1000,
            loadedClass: 'image-loaded',
            errorClass: 'image-error',
            loadingClass: 'image-loading',
            placeholderSVG: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"%3E%3Crect fill="%23f5f5f5" width="64" height="64"/%3E%3C/svg%3E'
        },

        /**
         * Check WebP support
         */
        supportsWebP: null,

        /**
         * Initialize image loader
         */
        init: function() {
            var self = this;
            
            // Check WebP support
            self.checkWebPSupport().then(function(supported) {
                self.supportsWebP = supported;
                console.log('WebP support:', supported);
            });
            
            // Setup lazy loading
            if ('IntersectionObserver' in window) {
                self.setupLazyLoading();
            } else {
                // Fallback: load all images immediately
                self.loadAllImages();
            }
        },

        /**
         * Check WebP support
         */
        checkWebPSupport: function() {
            return new Promise(function(resolve) {
                var webP = new Image();
                webP.onload = webP.onerror = function() {
                    resolve(webP.height === 2);
                };
                webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
            });
        },

        /**
         * Setup lazy loading with IntersectionObserver
         */
        setupLazyLoading: function() {
            var self = this;
            
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        self.loadImage(img);
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: self.config.rootMargin,
                threshold: self.config.threshold
            });
            
            // Observe all carrier logo images
            $('.carrier-img[data-src], .carrier-img[data-lazy]').each(function() {
                // Add loading placeholder
                $(this).addClass(self.config.loadingClass);
                observer.observe(this);
            });
            
            console.log('Lazy loading initialized for', $('.carrier-img[data-src], .carrier-img[data-lazy]').length, 'images');
        },

        /**
         * Load image with error handling and retry
         */
        loadImage: function(imgElement, attempt) {
            var self = this;
            var $img = $(imgElement);
            attempt = attempt || 0;
            
            var src = $img.attr('data-src') || $img.attr('src');
            
            if (!src || $img.hasClass(self.config.loadedClass)) {
                return;
            }
            
            // Create new image for loading
            var tempImg = new Image();
            
            tempImg.onload = function() {
                // Successful load
                $img.attr('src', src);
                $img.removeClass(self.config.loadingClass);
                $img.addClass(self.config.loadedClass);
                
                // Blur-up effect
                $img.css({
                    opacity: 0,
                    transition: 'opacity 0.3s ease'
                });
                
                setTimeout(function() {
                    $img.css('opacity', 1);
                }, 50);
                
                console.log('Image loaded:', src);
            };
            
            tempImg.onerror = function() {
                console.warn('Failed to load image:', src, 'Attempt:', attempt + 1);
                
                if (attempt < self.config.retryAttempts) {
                    // Retry after delay
                    setTimeout(function() {
                        self.loadImage(imgElement, attempt + 1);
                    }, self.config.retryDelay * (attempt + 1));
                } else {
                    // Max retries reached, show error placeholder
                    $img.removeClass(self.config.loadingClass);
                    $img.addClass(self.config.errorClass);
                    $img.attr('src', self.config.placeholderSVG);
                    $img.attr('alt', 'Image non disponible');
                }
            };
            
            tempImg.src = src;
        },

        /**
         * Load all images immediately (fallback)
         */
        loadAllImages: function() {
            var self = this;
            
            $('.carrier-img[data-src]').each(function() {
                var $img = $(this);
                var src = $img.attr('data-src');
                
                if (src) {
                    $img.attr('src', src);
                    $img.removeAttr('data-src');
                }
            });
        },

        /**
         * Preload critical images
         */
        preloadCriticalImages: function(urls) {
            var self = this;
            
            urls.forEach(function(url) {
                var link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = url;
                document.head.appendChild(link);
            });
        },

        /**
         * Get optimized image URL (convert to WebP if supported)
         */
        getOptimizedUrl: function(url) {
            var self = this;
            
            if (self.supportsWebP && url) {
                // Replace .jpg/.png with .webp if available
                // This assumes WebP versions are available on server
                var webpUrl = url.replace(/\.(jpg|jpeg|png)$/i, '.webp');
                
                // Check if WebP version exists
                return self.checkImageExists(webpUrl).then(function(exists) {
                    return exists ? webpUrl : url;
                });
            }
            
            return Promise.resolve(url);
        },

        /**
         * Check if image URL exists
         */
        checkImageExists: function(url) {
            return new Promise(function(resolve) {
                var img = new Image();
                img.onload = function() { resolve(true); };
                img.onerror = function() { resolve(false); };
                img.src = url;
            });
        },

        /**
         * Add responsive image hints
         */
        addResponsiveHints: function($img, sizes) {
            if (!sizes || !Array.isArray(sizes)) {
                return;
            }
            
            var srcset = sizes.map(function(size) {
                var url = size.url;
                var width = size.width;
                return url + ' ' + width + 'w';
            }).join(', ');
            
            $img.attr('srcset', srcset);
            $img.attr('sizes', '(max-width: 768px) 48px, 64px');
        }
    };

    // Auto-initialize on DOM ready
    $(document).ready(function() {
        ImageLoader.init();
    });

    return ImageLoader;
});
