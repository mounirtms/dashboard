define([
    'jquery'
], function ($) {
    'use strict';

    return function (target) {
        // Ensure fancybox is properly initialized and has all required methods
        if (typeof $.fancybox === 'undefined') {
            console.warn('Fancybox not loaded, skipping compatibility fixes');
            return target;
        }

        // Add getInstance method if missing (common issue with fancybox v3.5.7)
        if (typeof $.fancybox.getInstance !== 'function') {
            $.fancybox.getInstance = function() {
                var instance = $('.fancybox-container:not(".fancybox-is-closing"):last').data('FancyBox');
                return instance || null;
            };
        }

        // Ensure proper close method exists
        if (typeof $.fancybox.close !== 'function') {
            $.fancybox.close = function(force) {
                var instance = $.fancybox.getInstance();
                if (instance) {
                    instance.close(force);
                }
            };
        }

        // Add compatibility for older fancybox calls
        if (typeof $.fancybox.open !== 'function') {
            $.fancybox.open = function(items, opts, index) {
                return new $.fancybox(items, opts, index);
            };
        }

        // Ensure jQuery plugin method exists
        if (typeof $.fn.fancybox !== 'function') {
            $.fn.fancybox = function(opts) {
                var selector = this.selector || this;
                
                return this.each(function() {
                    var $this = $(this);
                    var data = $this.data('fancybox-options') || {};
                    var options = $.extend({}, data, opts);
                    
                    $this.off('click.fb-start').on('click.fb-start', function(e) {
                        e.preventDefault();
                        
                        var items = [];
                        var index = 0;
                        
                        if (options.selector) {
                            items = $(options.selector);
                            index = items.index(this);
                        } else {
                            items = [this];
                        }
                        
                        $.fancybox.open(items, options, index);
                    });
                });
            };
        }

        // Initialize fancybox for store locator when DOM is ready
        $(document).ready(function() {
            // Auto-initialize gallery images in store locator
            $('[data-amlocator-js="locator-gallery"] img').each(function(index) {
                var $img = $(this);
                var src = $img.attr('src') || $img.attr('data-src');
                
                if (src && !$img.parent('a[data-fancybox]').length) {
                    $img.wrap('<a href="' + src + '" data-fancybox="store-gallery" data-caption="' + ($img.attr('alt') || '') + '"></a>');
                }
            });
            
            // Initialize fancybox for store gallery
            if ($('[data-fancybox="store-gallery"]').length) {
                $('[data-fancybox="store-gallery"]').fancybox({
                    buttons: ['zoom', 'slideShow', 'close'],
                    loop: true,
                    protect: true,
                    animationEffect: 'zoom',
                    transitionEffect: 'slide',
                    image: {
                        preload: true
                    },
                    toolbar: true,
                    infobar: true,
                    arrows: true
                });
            }
        });

        // Return the original target (fancybox library)
        return target;
    };
});
