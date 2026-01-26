// Simple service worker debug script
self.addEventListener('install', function(event) {
    console.log('Service Worker installing...');
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker activating...');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function(event) {
    console.log('Push event received:', event);
    
    const title = 'Test Notification';
    const options = {
        body: 'This is a test push notification',
        icon: '/media/favicon.ico',
        badge: '/media/favicon.ico'
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    console.log('Notification clicked:', event);
    event.notification.close();
    
    event.waitUntil(
        clients.openWindow('/')
    );
});
