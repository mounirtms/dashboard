<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
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
        showCredit: false
      },
      promptOptions: {
        slidedown: {
          enabled: true,
          actionMessage: "هل ترغب في الحصول على أحدث العروض والتنبيهات من موقعنا؟",
          acceptButtonText: "نعم، بالتأكيد",
          cancelButtonText: "لاحقًا"
        },
        native: {
          enabled: true,
          autoPrompt: true
        }
      },
      welcomeNotification: {
        title: "مرحباً بك في Techno Stationery",
        message: "شكرًا للاشتراك في تنبيهاتنا!"
      }
    });
  });
</script>