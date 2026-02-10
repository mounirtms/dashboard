# OneSignal Push Notification Configuration Guide
## For Techno Stationery - Desktop and Mobile Unified Setup

### Complete Script for Admin Configuration

Copy and paste this complete script into your Magento admin panel:

```html
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    try {
      await OneSignal.init({
        appId: "ea60f1be-864c-4710-9437-3288e8e06cc4",
        serviceWorkerPath: "/OneSignalSDKWorker.js",
        serviceWorkerScope: "/",
        autoResubscribe: true,
        autoRegister: true,
        notifyButton: {
          enable: true,
          position: 'bottom-right',
          size: 'medium',
          showCredit: false,
          colors: {
            'circle.background': '#FF5500',
            'circle.foreground': 'white',
            'badge.background': '#FF5500',
            'badge.foreground': 'white',
            'badge.bordercolor': '#FF5500',
            'pulse.color': '#FF5500',
            'dialog.button.background.hovering': '#FF5500',
            'dialog.button.background.active': '#CC4400',
            'dialog.button.background': '#FF5500',
            'dialog.button.foreground': 'white'
          }
        },
        promptOptions: {
          slidedown: {
            enabled: true,
            actionMessage: "هل ترغب في الحصول على أحدث العروض والتنبيهات من موقعنا؟",
            acceptButtonText: "نعم، بالتأكيد",
            cancelButtonText: "لاحقًا",
            categories: {
              tags: [
                {
                  tag: "offers",
                  label: "العروض الخاصة"
                },
                {
                  tag: "news",
                  label: "الأخبار والتحديثات"
                },
                {
                  tag: "products",
                  label: "منتجات جديدة"
                }
              ]
            }
          },
          native: {
            enabled: true,
            autoPrompt: true
          }
        },
        welcomeNotification: {
          title: "مرحباً بك في Techno Stationery",
          message: "شكرًا للاشتراك في تنبيهاتنا!",
          url: "/"
        },
        notificationClickHandlerMatch: 'exact',
        notificationClickHandlerAction: 'focus',
        persistNotification: false,
        allowLocalhostAsSecureOrigin: true,
        requiresUserPrivacyConsent: false,
        webhooks: {
          'enable': false
        }
      });

      // Event tracking
      OneSignal.on('subscriptionChange', function (isSubscribed) {
        console.log('Subscription status changed:', isSubscribed);
      });

      OneSignal.on('notificationPermissionChange', function(permissionChange) {
        console.log('Notification permission changed:', permissionChange);
      });

    } catch (error) {
      console.error('OneSignal initialization error:', error);
    }
  });
</script>
```

### Where to Add This Script in Magento Admin:

1. **Content > Design > Configuration**
   - Select your store view
   - Go to "HTML Head" section
   - In "Scripts and Style Sheets" field, paste the script above

2. **Alternative Location:**
   - Marketing > Communications > OneSignal (if you have OneSignal module installed)
   - Configuration > OneSignal Settings

### Mobile-Specific Considerations:

The script above works for both desktop and mobile browsers. However, ensure:

1. **Responsive Design:** The notification button will automatically adapt to mobile screens
2. **Mobile Permissions:** iOS Safari requires user gesture for notifications
3. **Android Chrome:** Usually works seamlessly
4. **Service Workers:** Must be properly configured for mobile PWA support

### Testing Instructions:

1. **Desktop Testing:**
   - Visit technostationery.com
   - Check browser console for OneSignal initialization
   - Look for notification permission prompt

2. **Mobile Testing:**
   - Visit technostationery.com on mobile browser
   - Check for notification prompt
   - Test both Chrome and Safari (iOS)

3. **Verification:**
   - Check OneSignal dashboard for new subscribers
   - Send test notification from OneSignal panel
   - Monitor browser console for errors

### Troubleshooting:

**If notifications don't work on mobile:**
- Ensure HTTPS is enabled (required for push notifications)
- Check mobile browser support (Chrome, Firefox, Safari iOS 16.4+)
- Verify service worker registration in browser dev tools
- Clear browser cache and data

**Common Issues:**
- 403/404 errors: Check file permissions and paths
- MIME type errors: Verify server configuration
- Blocked by client: Check ad blockers/extensions
- Permission denied: User must manually allow notifications

### Required Files (should already exist):
- `/OneSignalSDKWorker.js`
- `/OneSignalSDKUpdaterWorker.js`
- `/manifest.json` (with proper GCM sender ID)

### Performance Monitoring:
Add this to track OneSignal performance:
```javascript
// Add to your analytics
if (typeof dataLayer !== 'undefined') {
  OneSignal.on('subscriptionChange', function(status) {
    dataLayer.push({
      event: 'onesignal_subscription',
      status: status,
      timestamp: new Date().toISOString()
    });
  });
}
```

This unified configuration will ensure push notifications work consistently across both desktop and mobile platforms for your Techno Stationery website.