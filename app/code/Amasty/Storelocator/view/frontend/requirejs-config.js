var config = {
    map: {
        "*": {
            // Create a consistent mapping for fancybox across all Amasty modules
            amfancybox: 'Amasty_Storelocator/vendor/fancybox/jquery.fancybox.min'
        }
    },
    shim: {
        'Amasty_Storelocator/vendor/fancybox/jquery.fancybox.min': {
            deps: ['jquery'],
            exports: 'jQuery.fn.fancybox'
        }
    },
    config: {
        mixins: {
            'Amasty_Storelocator/vendor/fancybox/jquery.fancybox.min': {
                'Amasty_Storelocator/js/fancybox-compatibility': true
            }
        }
    }
};
