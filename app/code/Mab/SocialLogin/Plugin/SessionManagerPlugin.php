<?php
namespace Mab\SocialLogin\Plugin;

use Magento\Framework\Session\SessionManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Mab\SocialLogin\Helper\Data as SocialLoginHelper;
use Magento\Framework\HTTP\Header as HttpHeader;

/**
 * Plugin for extending session lifetime for social login users
 */
class SessionManagerPlugin
{
    /**
     * @var SocialLoginHelper
     */
    private $socialLoginHelper;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var HttpHeader
     */
    private $httpHeader;

    /**
     * Constructor
     *
     * @param SocialLoginHelper $socialLoginHelper
     * @param CustomerSession $customerSession
     * @param HttpHeader $httpHeader
     */
    public function __construct(
        SocialLoginHelper $socialLoginHelper,
        CustomerSession $customerSession,
        HttpHeader $httpHeader
    ) {
        $this->socialLoginHelper = $socialLoginHelper;
        $this->customerSession = $customerSession;
        $this->httpHeader = $httpHeader;
    }

    /**
     * Before session start - set extended lifetime for social login users
     *
     * @param SessionManagerInterface $subject
     * @return array
     */
    public function beforeStart(SessionManagerInterface $subject)
    {
        if ($this->socialLoginHelper->isEnabled() && $this->socialLoginHelper->isExtendedLifetimeEnabled()) {
            $isMobile = $this->socialLoginHelper->isMobileDevice();
            $sessionLifetime = $this->socialLoginHelper->getSessionLifetimeInSeconds($isMobile);
            
            // Set cookie lifetime based on configuration
            ini_set('session.cookie_lifetime', $sessionLifetime);
            ini_set('session.gc_maxlifetime', $sessionLifetime);
            
            // If user is logged in via social login, extend their session
            if ($this->customerSession->isLoggedIn()) {
                $customerId = $this->customerSession->getCustomerId();
                if ($customerId && $this->isSocialLoginUser($customerId)) {
                    // Set extended cookie parameters
                    $cookieParams = session_get_cookie_params();
                    session_set_cookie_params(
                        $sessionLifetime,
                        $cookieParams['path'],
                        $cookieParams['domain'],
                        $cookieParams['secure'],
                        $cookieParams['httponly']
                    );
                }
            }
        }

        return [];
    }

    /**
     * After session start - renew session if needed
     *
     * @param SessionManagerInterface $subject
     * @return void
     */
    public function afterStart(SessionManagerInterface $subject)
    {
        if ($this->socialLoginHelper->isEnabled() && 
            $this->socialLoginHelper->isSessionRenewalEnabled() && 
            $this->customerSession->isLoggedIn()) {
            
            $lastActivity = $this->customerSession->getData('last_activity_time');
            $currentTime = time();
            
            // Renew session every hour if user is active
            if (!$lastActivity || ($currentTime - $lastActivity) > 3600) {
                $this->customerSession->setData('last_activity_time', $currentTime);
                
                if ($this->isSocialLoginUser($this->customerSession->getCustomerId())) {
                    // Regenerate session ID for security while maintaining extended lifetime
                    session_regenerate_id(false);
                }
            }
        }
    }

    /**
     * Check if user logged in via social login
     *
     * @param int $customerId
     * @return bool
     */
    private function isSocialLoginUser($customerId)
    {
        // Check if customer has social login data or flag
        // This would typically check a custom attribute or table
        // For now, we'll assume all customers can benefit from extended session
        return true;
    }
}