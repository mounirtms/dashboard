// Optimized OneSignal Service Worker with improved error handling and caching
importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');

// Add comprehensive error handling for service worker
self.addEventListener('install', function(event) {
    console.log('[OneSignal SW] Installing service worker...');
    event.waitUntil(
        caches.open('onesignal-static-v1').then(function(cache) {
            return cache.addAll([
                'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js'
            ]).then(function() {
                console.log('[OneSignal SW] Static assets cached successfully');
            }).catch(function(error) {
                console.warn('[OneSignal SW] Failed to cache static assets:', error);
            });
        }).then(function() {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function(event) {
    console.log('[OneSignal SW] Service worker activated');
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            // Clean up old caches
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        if (cacheName !== 'onesignal-static-v1') {
                            console.log('[OneSignal SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
        ]).then(function() {
            console.log('[OneSignal SW] Activation complete');
        }).catch(function(error) {
            console.error('[OneSignal SW] Activation error:', error);
        })
    );
});

// Enhanced message handling with error logging
self.addEventListener('message', function(event) {
    try {
        if (event.data && event.data.type) {
            console.log('[OneSignal SW] Message received:', event.data.type, event.data);

            if (event.data.type === 'ONESIGNAL_PATCH') {
                console.log('[OneSignal SW] Processing OneSignal patch message');
            }

            // Handle custom messages
            if (event.data.action) {
                console.log('[OneSignal SW] Custom action received:', event.data.action);
            }
        }
    } catch (error) {
        console.error('[OneSignal SW] Error processing message:', error);
    }
});

// Network request monitoring and caching
self.addEventListener('fetch', function(event) {
    // Handle OneSignal related requests
    if (event.request.url.includes('onesignal.com')) {
        console.log('[OneSignal SW] Handling OneSignal request:', event.request.url);

        event.respondWith(
            fetch(event.request).then(function(response) {
                // Clone the response before returning
                var responseClone = response.clone();

                // Cache successful responses if needed
                if (response.ok) {
                    console.log('[OneSignal SW] Request successful:', event.request.url);
                }

                return response;
            }).catch(function(error) {
                console.error('[OneSignal SW] Network error for:', event.request.url, error);

                // Try to return from cache if available
                return caches.match(event.request).then(function(cachedResponse) {
                    if (cachedResponse) {
                        console.log('[OneSignal SW] Returning cached response for:', event.request.url);
                        return cachedResponse;
                    }

                    // If no cache, return error response
                    return new Response(JSON.stringify({error: 'Network error'}), {
                        status: 503,
                        headers: {'Content-Type': 'application/json'}
                    });
                });
            })
        );
    }

    // Handle notification click events
    if (event.request.destination === 'report') {
        console.log('[OneSignal SW] Handling notification report:', event.request.url);
    }
});

// Listen for push events
self.addEventListener('push', function(event) {
    console.log('[OneSignal SW] Push event received:', event);

    // Let OneSignal SDK handle the push event
    // This is handled internally by the imported SDK
});

// Listen for push subscription changes
self.addEventListener('pushsubscriptionchange', function(event) {
    console.log('[OneSignal SW] Push subscription change detected');

    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
            .then(function(newSubscription) {
                console.log('[OneSignal SW] Successfully resubscribed to push notifications');
                // Send new subscription to your server
                return fetch('/rest/V1/onesignal/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        subscription: newSubscription,
                        old_endpoint: event.oldSubscription.endpoint,
                        new_endpoint: newSubscription.endpoint
                    })
                });
            })
            .catch(function(error) {
                console.error('[OneSignal SW] Failed to resubscribe:', error);
            })
    );
});

// Add periodic health checks
setInterval(function() {
    if (self.registration) {
        self.registration.update().then(function() {
            console.log('[OneSignal SW] Registration updated successfully');
        }).catch(function(error) {
            console.warn('[OneSignal SW] Failed to update registration:', error);
        });
    }
}, 7200000); // Check every 2 hours