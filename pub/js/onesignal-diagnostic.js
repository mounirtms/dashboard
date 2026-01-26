/**
 * OneSignal Diagnostic Tool
 * Helps debug push notification issues
 */
(function() {
    'use strict';
    
    // Diagnostic logger
    const log = function(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `[${timestamp}] ${message}`;
        
        switch(type) {
            case 'error':
                console.error(logEntry);
                break;
            case 'warn':
                console.warn(logEntry);
                break;
            case 'success':
                console.log('%c' + logEntry, 'color: green; font-weight: bold;');
                break;
            default:
                console.log(logEntry);
        }
        
        // Store in diagnostics array
        if (!window.onesignalDiagnostics) {
            window.onesignalDiagnostics = [];
        }
        window.onesignalDiagnostics.push({
            timestamp: new Date().toISOString(),
            message: message,
            type: type
        });
    };
    
    // Check browser support
    const checkBrowserSupport = function() {
        log('Checking browser support for push notifications...');
        
        if (!('serviceWorker' in navigator)) {
            log('❌ Service Worker not supported', 'error');
            return false;
        }
        
        if (!('PushManager' in window)) {
            log('❌ Push Manager not supported', 'error');
            return false;
        }
        
        if (!('Notification' in window)) {
            log('❌ Notifications not supported', 'error');
            return false;
        }
        
        log('✅ Browser supports push notifications', 'success');
        return true;
    };
    
    // Check service worker registration
    const checkServiceWorker = async function() {
        log('Checking OneSignal Service Worker registration...');
        
        try {
            const registrations = await navigator.serviceWorker.getRegistrations();
            const oneSignalSW = registrations.find(reg => 
                reg.active && reg.active.scriptURL.includes('OneSignal')
            );
            
            if (oneSignalSW) {
                log(`✅ OneSignal Service Worker registered: ${oneSignalSW.scope}`, 'success');
                log(`Service Worker state: ${oneSignalSW.active.state}`);
                
                // Check service worker status
                if (oneSignalSW.active.state === 'activated') {
                    log('✅ Service Worker is activated', 'success');
                } else {
                    log(`⚠️ Service Worker state: ${oneSignalSW.active.state}`, 'warn');
                }
                
                return true;
            } else {
                log('❌ OneSignal Service Worker not registered', 'error');
                
                // Try to register manually
                try {
                    log('Attempting to register OneSignal Service Worker manually...');
                    const registration = await navigator.serviceWorker.register('/OneSignalSDKWorker.js', {
                        scope: '/'
                    });
                    log(`✅ Successfully registered OneSignal Service Worker: ${registration.scope}`, 'success');
                    return true;
                } catch (registerError) {
                    log(`❌ Failed to register Service Worker: ${registerError.message}`, 'error');
                    return false;
                }
            }
        } catch (error) {
            log(`❌ Error checking Service Worker: ${error.message}`, 'error');
            return false;
        }
    };
    
    // Check OneSignal initialization
    const checkOneSignalInitialization = function() {
        log('Checking OneSignal initialization...');
        
        if (typeof window.OneSignal === 'undefined') {
            log('❌ OneSignal SDK not loaded', 'error');
            return false;
        }
        
        if (window.OneSignal.initialized) {
            log('✅ OneSignal is initialized', 'success');
            return true;
        } else {
            log('⚠️ OneSignal not yet initialized', 'warn');
            return false;
        }
    };
    
    // Check notification permissions
    const checkPermissions = function() {
        log('Checking notification permissions...');
        
        if (!('Notification' in window)) {
            log('❌ Notification API not available', 'error');
            return false;
        }
        
        const permission = Notification.permission;
        log(`Current notification permission: ${permission}`);
        
        switch(permission) {
            case 'granted':
                log('✅ Notification permission granted', 'success');
                return true;
            case 'denied':
                log('❌ Notification permission denied', 'error');
                return false;
            case 'default':
                log('⚠️ Notification permission not yet requested', 'warn');
                return null;
        }
    };
    
    // Check subscription status
    const checkSubscription = async function() {
        log('Checking push subscription status...');
        
        if (typeof window.OneSignal === 'undefined') {
            log('❌ OneSignal not available for subscription check', 'error');
            return false;
        }
        
        try {
            // Try modern OneSignal v16 API
            if (window.OneSignal.User && window.OneSignal.User.PushSubscription) {
                const subscription = window.OneSignal.User.PushSubscription;
                const isSubscribed = await subscription.optedIn;
                
                if (isSubscribed) {
                    log('✅ User is subscribed to push notifications', 'success');
                    log(`Subscription ID: ${subscription.id || 'N/A'}`);
                    return true;
                } else {
                    log('❌ User is not subscribed to push notifications', 'warn');
                    return false;
                }
            }
            
            // Fallback to older API
            if (window.OneSignal.isPushNotificationsEnabled) {
                const isEnabled = await window.OneSignal.isPushNotificationsEnabled();
                if (isEnabled) {
                    log('✅ Push notifications are enabled', 'success');
                    return true;
                } else {
                    log('❌ Push notifications are disabled', 'warn');
                    return false;
                }
            }
            
            log('⚠️ Unable to determine subscription status', 'warn');
            return null;
            
        } catch (error) {
            log(`❌ Error checking subscription: ${error.message}`, 'error');
            return false;
        }
    };
    
    // Request notification permission
    const requestPermission = async function() {
        log('Requesting notification permission...');
        
        try {
            const permission = await Notification.requestPermission();
            log(`Permission request result: ${permission}`);
            
            if (permission === 'granted') {
                log('✅ Notification permission granted', 'success');
                return true;
            } else {
                log('❌ Notification permission denied', 'error');
                return false;
            }
        } catch (error) {
            log(`❌ Error requesting permission: ${error.message}`, 'error');
            return false;
        }
    };
    
    // Test sending a notification
    const testNotification = async function() {
        log('Testing notification capability...');
        
        if (typeof window.OneSignal === 'undefined') {
            log('❌ OneSignal not available for testing', 'error');
            return false;
        }
        
        try {
            // Try to show a test notification
            if (window.OneSignal.Notifications && window.OneSignal.Notifications.requestPermission) {
                const result = await window.OneSignal.Notifications.requestPermission();
                log(`Notification request result: ${result}`);
                return result === 'granted';
            }
            
            // Fallback method
            if (Notification.permission === 'granted') {
                new Notification('Test Notification', {
                    body: 'This is a test notification from OneSignal diagnostic tool',
                    icon: '/favicon.ico'
                });
                log('✅ Test notification sent successfully', 'success');
                return true;
            } else {
                log('❌ Cannot send test notification - permission not granted', 'error');
                return false;
            }
            
        } catch (error) {
            log(`❌ Error sending test notification: ${error.message}`, 'error');
            return false;
        }
    };
    
    // Run complete diagnostic
    const runDiagnostic = async function() {
        log('=== OneSignal Push Notification Diagnostic Started ===', 'info');
        
        // Check prerequisites
        if (!checkBrowserSupport()) {
            log('=== Diagnostic Failed: Browser not supported ===', 'error');
            return;
        }
        
        // Check service worker
        const swOk = await checkServiceWorker();
        
        // Check OneSignal
        const osOk = checkOneSignalInitialization();
        
        // Check permissions
        const permStatus = checkPermissions();
        
        // Check subscription
        const subStatus = await checkSubscription();
        
        // Summary
        log('=== Diagnostic Summary ===', 'info');
        log(`Service Worker: ${swOk ? '✅ OK' : '❌ FAILED'}`);
        log(`OneSignal Init: ${osOk ? '✅ OK' : '❌ FAILED'}`);
        log(`Permissions: ${permStatus === true ? '✅ GRANTED' : permStatus === false ? '❌ DENIED' : '⚠️ NOT REQUESTED'}`);
        log(`Subscription: ${subStatus === true ? '✅ ACTIVE' : subStatus === false ? '❌ INACTIVE' : '⚠️ UNKNOWN'}`);
        
        // Recommendations
        log('=== Recommendations ===', 'info');
        if (!swOk) {
            log('• Check if OneSignalSDKWorker.js is accessible at root level', 'warn');
            log('• Verify CDN connectivity for OneSignal SDK', 'warn');
        }
        if (permStatus === null || permStatus === false) {
            log('• Consider prompting user for notification permission', 'warn');
        }
        if (subStatus === false) {
            log('• User needs to subscribe to notifications', 'warn');
        }
        
        log('=== Diagnostic Complete ===', 'info');
    };
    
    // Expose diagnostic functions globally
    window.onesignalDiagnostic = {
        run: runDiagnostic,
        checkBrowserSupport: checkBrowserSupport,
        checkServiceWorker: checkServiceWorker,
        checkOneSignal: checkOneSignalInitialization,
        checkPermissions: checkPermissions,
        checkSubscription: checkSubscription,
        requestPermission: requestPermission,
        testNotification: testNotification,
        log: log
    };
    
    // Auto-run diagnostic on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(runDiagnostic, 2000); // Wait for OneSignal to initialize
        });
    } else {
        setTimeout(runDiagnostic, 2000);
    }
    
})();