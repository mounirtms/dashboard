# OneSignal Push Notification Troubleshooting Guide

## Common Issues and Solutions

### 1. Notifications Not Reaching Subscribers

**Symptoms:** 
- Users subscribe successfully but don't receive notifications
- No errors in browser console
- Subscriptions appear in OneSignal dashboard

**Solutions:**
- ✅ **Check HTTPS**: Ensure your site uses HTTPS in production
- ✅ **Verify Service Worker**: Confirm OneSignalSDKWorker.js is accessible
- ✅ **Test Different Browsers**: Some browsers handle push differently
- ✅ **Check User Permission**: Verify users haven't blocked notifications
- ✅ **Review Message Targeting**: Ensure your audience targeting is correct

### 2. Subscription Failures

**Symptoms:**
- Permission prompt doesn't appear
- Users see "blocked" status
- Console errors about service workers

**Solutions:**
```javascript
// Enhanced subscription handling
OneSignal.showNativePrompt().then(function(result) {
    console.log('Prompt result:', result);
}).catch(function(error) {
    console.error('Prompt error:', error);
});
```

### 3. Service Worker Issues

**Symptoms:**
- "Service worker failed to register" errors
- Notifications work intermittently
- Delayed notification delivery

**Solutions:**
- ✅ Clear browser cache and data
- ✅ Check service worker path configuration
- ✅ Verify file permissions on server
- ✅ Test with different devices/browsers

## Testing Checklist

### Server-Side Validation
- [ ] OneSignal App ID is correct
- [ ] REST API key configured (for sending notifications)
- [ ] Service worker file accessible at expected path
- [ ] HTTPS certificate valid and properly configured
- [ ] CORS headers properly set

### Client-Side Validation
- [ ] Browser supports push notifications
- [ ] Service worker registers successfully
- [ ] Notification permission granted
- [ ] User is properly subscribed
- [ ] No JavaScript errors in console

### Network Validation
- [ ] OneSignal domains accessible (onesignal.com, onsignalusercontent.com)
- [ ] WebSocket connections working (wss://*.onesignal.com)
- [ ] CDN resources loading properly
- [ ] No firewall/proxy blocking requests

## Debugging Tools

### 1. Browser Developer Tools
```javascript
// Check OneSignal status
OneSignal.isPushNotificationsEnabled().then(enabled => {
    console.log('Notifications enabled:', enabled);
});

OneSignal.getNotificationPermission().then(permission => {
    console.log('Permission status:', permission);
});
```

### 2. OneSignal Dashboard
- Check delivery statistics
- Review recent sends and their status
- Monitor player/device activity
- Check for delivery errors

### 3. Server Logs
Monitor your server logs for:
- Service worker registration failures
- OneSignal API call errors
- Network connectivity issues

## Best Practices

### 1. Timing and Frequency
- Send notifications during peak engagement hours
- Avoid overwhelming users with too many notifications
- Segment audiences for targeted messaging

### 2. Content Optimization
- Use compelling titles and messages
- Include relevant icons/images
- Provide clear value proposition
- Add appropriate call-to-action buttons

### 3. Technical Implementation
- Implement proper error handling
- Use category-based subscription preferences
- Track engagement metrics
- Regular testing across different platforms

## Emergency Fixes

If notifications stop working suddenly:

1. **Immediate Steps:**
   - Check OneSignal service status
   - Verify your account is in good standing
   - Test with a simple notification send

2. **Quick Diagnostics:**
   ```bash
   # Check service worker accessibility
   curl -I https://yourdomain.com/OneSignalSDKWorker.js
   
   # Test HTTPS certificate
   openssl s_client -connect yourdomain.com:443
   ```

3. **Contact Support:**
   - Document the issue with timestamps
   - Include browser/console error details
   - Provide affected user examples

## Performance Monitoring

Set up monitoring for:
- Subscription conversion rates
- Notification delivery success rates
- Click-through rates
- Unsubscribe rates
- Device/browser distribution

Regular monitoring helps identify issues before they impact users significantly.