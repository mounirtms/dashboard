/**
 * Mixin to avoid duplicating store code in REST URLs when baseUrl already contains the store code path.
 * Improvements:
 * - Do not override explicit `params.storeCode` passed by caller.
 * - Detect store code either from `window.checkoutConfig.storeCode` or the first path segment of `baseUrl`.
 * - Normalize slashes and fail silently on errors.
 */
define([], function () {
    'use strict';

    return function (target) {
        var originalCreateUrl = target.createUrl;

        target.createUrl = function (url, params) {
            params = params || {};

            try {
                // If caller explicitly provided storeCode, do not override it.
                if (Object.prototype.hasOwnProperty.call(params, 'storeCode')) {
                    return originalCreateUrl.call(this, url, params);
                }

                var checkoutConfig = window.checkoutConfig || {};
                var storeCode = checkoutConfig.storeCode || '';
                var baseUrl = checkoutConfig.baseUrl || window.BASE_URL || '';

                if (baseUrl) {
                    // Normalize base path (remove protocol+host, trim slashes)
                    var path = baseUrl.replace(/^https?:\/\/[^\/]+/i, '');
                    path = path.replace(/(^\/+|\/+$)/g, '');
                    var segments = path ? path.split('/') : [];
                    var firstSegment = segments.length ? segments[0] : '';
                    var candidate = storeCode || firstSegment;

                    if (candidate) {
                        var normalizedBase = '/' + path.replace(/\/+/, '/') + '/';
                        if (normalizedBase.indexOf('/' + candidate + '/') !== -1) {
                            // prevent the url-builder from adding the store code again
                            params.storeCode = '';
                        }
                    }
                }
            } catch (e) {
                // Fail silently and fall back to default behaviour
            }

            return originalCreateUrl.call(this, url, params);
        };

        return target;
    };
});
