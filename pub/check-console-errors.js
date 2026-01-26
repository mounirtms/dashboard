// Simulate browser console error checking
console.log("Starting OneSignal console error simulation...");

// Check for common OneSignal errors
const commonErrors = [
    "OneSignal not initialized",
    "ServiceWorker registration failed",
    "PushManager not supported",
    "Notification permission denied",
    "OneSignalSDKWorker.js not found"
];

console.log("Common OneSignal issues to watch for:");
commonErrors.forEach(error => {
    console.log("- " + error);
});

// Simulate service worker registration check
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(registrations => {
        const oneSignalSW = registrations.find(reg => reg.active && reg.active.scriptURL.includes('OneSignal'));
        if (oneSignalSW) {
            console.log("✅ OneSignal Service Worker registered:", oneSignalSW.scope);
        } else {
            console.warn("⚠️ OneSignal Service Worker not found");
        }
    }).catch(error => {
        console.error("❌ Error checking service workers:", error);
    });
} else {
    console.error("❌ Service Workers not supported");
}

console.log("Console error check complete.");
