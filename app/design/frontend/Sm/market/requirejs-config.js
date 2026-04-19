var config = {
    map: {
        '*': {
            'bootstrap.bundle.min': 'js/bootstrap/bootstrap.bundle.min',
            'slick': 'js/slick',
			'flipdown': 'js/flipdown'
        }
    },
    shim: {
        'bootstrap.bundle.min': {
            'deps': ['jquery']
        }
    },
    deps: [
        "js/bootstrap/bootstrap.bundle.min",
        "js/theme-js"
    ],
    paths: {
        // Ensure jQuery UI widgets are properly resolved to avoid compat fallback
        'jquery/ui-modules/widget': 'jquery/jquery-ui'
    }
};