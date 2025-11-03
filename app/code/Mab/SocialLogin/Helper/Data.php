<?php
namespace Mab\SocialLogin\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Header as HttpHeader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Mab\Core\Helper\ErrorHandler;
use Psr\Log\LoggerInterface;

/**
 * Social Login Helper with comprehensive session management and caching
 */
class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'mab_social_login/general/enabled';
    const XML_PATH_GOOGLE_ENABLED = 'mab_social_login/providers/google_enabled';
    const XML_PATH_FACEBOOK_ENABLED = 'mab_social_login/providers/facebook_enabled';
    const XML_PATH_FIREBASE_CONFIG = 'mab_social_login/firebase/config';
    const XML_PATH_FIREBASE_ENABLED = 'mab_social_login/firebase/firebase_enabled';
    
    // Session Management
    const XML_PATH_EXTENDED_LIFETIME_ENABLED = 'mab_social_login/session/extended_lifetime_enabled';
    const XML_PATH_SESSION_LIFETIME = 'mab_social_login/session/session_lifetime';
    const XML_PATH_MOBILE_EXTENDED_LIFETIME = 'mab_social_login/session/mobile_extended_lifetime';
    const XML_PATH_REMEMBER_ME_ENABLED = 'mab_social_login/session/remember_me_enabled';
    const XML_PATH_SESSION_RENEWAL = 'mab_social_login/session/session_renewal';
    
    // Google Configuration
    const XML_PATH_GOOGLE_CLIENT_ID = 'mab_social_login/google/client_id';
    const XML_PATH_GOOGLE_CLIENT_SECRET = 'mab_social_login/google/client_secret';
    
    // Display Configuration
    const XML_PATH_BUTTON_STYLE = 'mab_social_login/display/button_style';
    const XML_PATH_BUTTON_SIZE = 'mab_social_login/display/button_size';
    const XML_PATH_SHOW_ON_PAGES = 'mab_social_login/display/show_on_pages';
    
    // Cache Settings
    const CACHE_TAG = 'mab_social_login';
    const CACHE_LIFETIME = 3600; // 1 hour
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var HttpHeader
     */
    private $httpHeader;
    
    /**
     * @var Json
     */
    private $jsonSerializer;
    
    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    
    /**
     * @var CustomerSession
     */
    private $customerSession;
    
    /**
     * @var ErrorHandler
     */
    private $errorHandler;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @var array
     */
    private $configCache = [];

    /**
     * Constructor
     *
     * @param Context $context
     * @param CacheInterface $cache
     * @param HttpHeader $httpHeader
     * @param Json $jsonSerializer
     * @param CustomerFactory $customerFactory
     * @param CustomerSession $customerSession
     * @param ErrorHandler $errorHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CacheInterface $cache,
        HttpHeader $httpHeader,
        Json $jsonSerializer,
        CustomerFactory $customerFactory,
        CustomerSession $customerSession,
        ErrorHandler $errorHandler,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->cache = $cache;
        $this->httpHeader = $httpHeader;
        $this->jsonSerializer = $jsonSerializer;
        $this->customerFactory = $customerFactory;
        $this->customerSession = $customerSession;
        $this->errorHandler = $errorHandler;
        $this->logger = $logger;
    }

    /**
     * Get configuration value with error handling
     *
     * @param string $path
     * @param int|null $storeId
     * @param mixed $default
     * @return mixed
     */
    public function getConfigValue($path, $storeId = null, $default = null)
    {
        try {
            $value = $this->scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            return $value !== null ? $value : $default;
        } catch (\Exception $e) {
            $this->logger->error("Error getting config value for path: {$path}", ['exception' => $e]);
            return $default;
        }
    }

    /**
     * Check if social login is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->getConfigValue(self::XML_PATH_ENABLED, $storeId, true);
    }

    /**
     * Check if Google login is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGoogleEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && 
               $this->getConfigValue(self::XML_PATH_GOOGLE_ENABLED, $storeId, true);
    }

    /**
     * Check if Facebook login is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isFacebookEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && 
               $this->getConfigValue(self::XML_PATH_FACEBOOK_ENABLED, $storeId, true);
    }

    /**
     * Check if Firebase is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isFirebaseEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && 
               $this->getConfigValue(self::XML_PATH_FIREBASE_ENABLED, $storeId, true);
    }

    /**
     * Get Firebase configuration
     *
     * @param int|null $storeId
     * @return array
     */
    public function getFirebaseConfig($storeId = null): array
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                $config = $this->getConfigValue(self::XML_PATH_FIREBASE_CONFIG, $storeId);
                if (empty($config)) {
                    return [];
                }
                
                return $this->jsonSerializer->unserialize($config);
            },
            [],
            'Getting Firebase configuration'
        );
    }

    /**
     * Get Google Client ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGoogleClientId($storeId = null): string
    {
        return (string)$this->getConfigValue(self::XML_PATH_GOOGLE_CLIENT_ID, $storeId);
    }

    /**
     * Get Google Client Secret
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGoogleClientSecret($storeId = null): string
    {
        return (string)$this->getConfigValue(self::XML_PATH_GOOGLE_CLIENT_SECRET, $storeId);
    }

    /**
     * Check if extended session lifetime is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isExtendedLifetimeEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && 
               $this->getConfigValue(self::XML_PATH_EXTENDED_LIFETIME_ENABLED, $storeId, true);
    }

    /**
     * Get session lifetime in hours
     *
     * @param bool $isMobile
     * @param int|null $storeId
     * @return int
     */
    public function getSessionLifetimeInHours(bool $isMobile = false, $storeId = null): int
    {
        if ($isMobile) {
            return (int)$this->getConfigValue(self::XML_PATH_MOBILE_EXTENDED_LIFETIME, $storeId) ?: 2160;
        }
        
        return (int)$this->getConfigValue(self::XML_PATH_SESSION_LIFETIME, $storeId) ?: 720;
    }

    /**
     * Get session lifetime in seconds
     *
     * @param bool $isMobile
     * @param int|null $storeId
     * @return int
     */
    public function getSessionLifetimeInSeconds(bool $isMobile = false, $storeId = null): int
    {
        return $this->getSessionLifetimeInHours($isMobile, $storeId) * 3600;
    }

    /**
     * Check if remember me is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isRememberMeEnabled($storeId = null): bool
    {
        return $this->isExtendedLifetimeEnabled($storeId) && 
               $this->getConfigValue(self::XML_PATH_REMEMBER_ME_ENABLED, $storeId, true);
    }

    /**
     * Check if session renewal is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSessionRenewalEnabled($storeId = null): bool
    {
        return $this->isExtendedLifetimeEnabled($storeId) && 
               $this->getConfigValue(self::XML_PATH_SESSION_RENEWAL, $storeId, true);
    }

    /**
     * Check if device is mobile
     *
     * @return bool
     */
    public function isMobileDevice(): bool
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        return preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
    }

    /**
     * Get button style
     *
     * @param int|null $storeId
     * @return string
     */
    public function getButtonStyle($storeId = null): string
    {
        return (string)$this->getConfigValue(self::XML_PATH_BUTTON_STYLE, $storeId) ?: 'rounded';
    }

    /**
     * Get button size
     *
     * @param int|null $storeId
     * @return string
     */
    public function getButtonSize($storeId = null): string
    {
        return (string)$this->getConfigValue(self::XML_PATH_BUTTON_SIZE, $storeId) ?: 'medium';
    }

    /**
     * Get pages where social login should be shown
     *
     * @param int|null $storeId
     * @return array
     */
    public function getShowOnPages($storeId = null): array
    {
        $pages = $this->getConfigValue(self::XML_PATH_SHOW_ON_PAGES, $storeId);
        return $pages ? explode(',', $pages) : [];
    }

    /**
     * Check if social login should be shown on current page
     *
     * @param string $pageIdentifier
     * @param int|null $storeId
     * @return bool
     */
    public function shouldShowOnPage(string $pageIdentifier, $storeId = null): bool
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }
        
        $allowedPages = $this->getShowOnPages($storeId);
        return empty($allowedPages) || in_array($pageIdentifier, $allowedPages);
    }

    /**
     * Set extended session for social login user
     *
     * @param int $customerId
     * @return bool
     */
    public function setExtendedSession(int $customerId): bool
    {
        if (!$this->isExtendedLifetimeEnabled()) {
            return false;
        }
        
        try {
            $isMobile = $this->isMobileDevice();
            $sessionLifetime = $this->getSessionLifetimeInSeconds($isMobile);
            
            // Set session data to track social login
            $this->customerSession->setData('social_login_user', true);
            $this->customerSession->setData('social_login_time', time());
            $this->customerSession->setData('is_mobile_device', $isMobile);
            
            // Update session cookie parameters
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params(
                $sessionLifetime,
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
            
            $this->logger->info('[MAB Social Login] Extended session set for customer: ' . $customerId . ' (Mobile: ' . ($isMobile ? 'Yes' : 'No') . ')');
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Social Login] Error setting extended session: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if current user is a social login user
     *
     * @return bool
     */
    public function isSocialLoginUser(): bool
    {
        return (bool)$this->customerSession->getData('social_login_user');
    }

    /**
     * Clear configuration cache
     *
     * @return void
     */
    public function clearConfigCache(): void
    {
        $this->configCache = [];
        $this->cache->clean([self::CACHE_TAG]);
    }

    /**
     * Get comprehensive social login configuration
     *
     * @param int|null $storeId
     * @return array
     */
    public function getComprehensiveConfig(?int $storeId = null): array
    {
        return [
            'enabled' => $this->isEnabled($storeId),
            'google_enabled' => $this->isGoogleEnabled($storeId),
            'facebook_enabled' => $this->isFacebookEnabled($storeId),
            'extended_lifetime_enabled' => $this->isExtendedLifetimeEnabled($storeId),
            'session_lifetime' => $this->getSessionLifetimeInHours(false, $storeId),
            'mobile_extended_lifetime' => $this->getSessionLifetimeInHours(true, $storeId),
            'google_client_id' => $this->getGoogleClientId($storeId)
        ];
    }

    /**
     * Optimize session settings for social login user
     *
     * @param int $customerId
     * @param bool $rememberMe
     * @return bool
     */
    public function optimizeSessionSettings(int $customerId, bool $rememberMe = false): bool
    {
        if (!$this->isExtendedLifetimeEnabled()) {
            return false;
        }
        
        try {
            $isMobile = $this->isMobileDevice();
            $sessionLifetime = $this->getSessionLifetimeInSeconds($isMobile);
            
            // Extend for remember me
            if ($rememberMe && $this->isRememberMeEnabled()) {
                $sessionLifetime = $isMobile ? (4320 * 3600) : (2160 * 3600); // 180 days mobile, 90 days desktop
            }
            
            // Set comprehensive session data
            $this->customerSession->setData('social_login_user', true);
            $this->customerSession->setData('social_login_time', time());
            $this->customerSession->setData('is_mobile_device', $isMobile);
            $this->customerSession->setData('remember_me_enabled', $rememberMe);
            $this->customerSession->setData('session_optimized', true);
            $this->customerSession->setData('last_activity_time', time());
            
            // Configure session parameters
            $this->configureSessionCookie($sessionLifetime);
            
            $this->logger->info(sprintf(
                '[MAB Social Login] Session optimized for customer %d: Mobile=%s, RememberMe=%s, Lifetime=%d hours',
                $customerId,
                $isMobile ? 'Yes' : 'No',
                $rememberMe ? 'Yes' : 'No',
                $sessionLifetime / 3600
            ));
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Social Login] Session optimization error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Configure session cookie with optimized parameters
     *
     * @param int $lifetime
     * @return void
     */
    private function configureSessionCookie(int $lifetime): void
    {
        $cookieParams = session_get_cookie_params();
        
        // Enhanced security for social login sessions
        session_set_cookie_params(
            $lifetime,
            $cookieParams['path'] ?: '/',
            $cookieParams['domain'] ?: '',
            $this->getRequest()->isSecure(), // Secure flag based on HTTPS
            true, // HttpOnly for security
            'Lax' // SameSite attribute for CSRF protection
        );
        
        // Set session cache limiter for better performance
        session_cache_limiter('private');
        session_cache_expire($lifetime / 60);
    }

    /**
     * Check session health and renew if needed
     *
     * @return bool
     */
    public function maintainSessionHealth(): bool
    {
        if (!$this->customerSession->isLoggedIn() || !$this->isSocialLoginUser()) {
            return false;
        }
        
        try {
            $lastActivity = $this->customerSession->getData('last_activity_time');
            $currentTime = time();
            
            // Renew session every hour if user is active
            if (!$lastActivity || ($currentTime - $lastActivity) > 3600) {
                $this->customerSession->setData('last_activity_time', $currentTime);
                
                if ($this->isSessionRenewalEnabled()) {
                    // Regenerate session ID for security while maintaining data
                    session_regenerate_id(false);
                    $this->logger->debug('[MAB Social Login] Session renewed for active user');
                }
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Social Login] Session maintenance error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get session diagnostics
     *
     * @return array
     */
    public function getSessionDiagnostics(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['status' => 'not_logged_in'];
        }
        
        $diagnostics = [
            'is_social_login_user' => $this->isSocialLoginUser(),
            'session_id' => session_id(),
            'session_name' => session_name(),
            'is_mobile_device' => $this->customerSession->getData('is_mobile_device'),
            'remember_me_enabled' => $this->customerSession->getData('remember_me_enabled'),
            'session_optimized' => $this->customerSession->getData('session_optimized'),
            'social_login_time' => $this->customerSession->getData('social_login_time'),
            'last_activity_time' => $this->customerSession->getData('last_activity_time'),
            'customer_id' => $this->customerSession->getCustomerId()
        ];
        
        // Add calculated values
        if ($diagnostics['social_login_time']) {
            $diagnostics['session_age_hours'] = round((time() - $diagnostics['social_login_time']) / 3600, 2);
        }
        
        if ($diagnostics['last_activity_time']) {
            $diagnostics['inactive_time_minutes'] = round((time() - $diagnostics['last_activity_time']) / 60, 2);
        }
        
        return $diagnostics;
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debugLog(string $message, array $context = []): void
    {
        $this->logger->debug('[MAB Social Login] ' . $message, $context);
    }
}
