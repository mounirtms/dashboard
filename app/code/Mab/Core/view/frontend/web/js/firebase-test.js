// Simple JS test for Firebase config presence
require(['jquery'], function($) {
    if (window.firebase && window.firebase.app) {
        console.log('Firebase JS SDK loaded.');
        if (firebase.apps.length) {
            console.log('Firebase app initialized:', firebase.apps[0].name);
        } else {
            console.warn('Firebase app not initialized.');
        }
    } else {
        console.warn('Firebase JS SDK not found on page.');
    }
});
