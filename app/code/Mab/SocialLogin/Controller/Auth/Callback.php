<?php
namespace Mab\SocialLogin\Controller\Auth;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Mab\SocialLogin\Helper\Data as SocialLoginHelper;
use Mab\Core\Helper\ErrorHandler;
use Kreait\Firebase\Factory;
use Psr\Log\LoggerInterface;

/**
 * Enhanced Social Login Callback Controller with comprehensive session management
 */
class Callback extends Action
{
    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var SocialLoginHelper
     */
    protected $socialLoginHelper;

    /**
     * @var ErrorHandler
     */
    protected $errorHandler;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Kreait\Firebase\Auth
     */
    protected $firebase;

    /**
     * Constructor
     *
     * @param Context $context
     * @param CustomerFactory $customerFactory
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param SocialLoginHelper $socialLoginHelper
     * @param ErrorHandler $errorHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CustomerFactory $customerFactory,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        SocialLoginHelper $socialLoginHelper,
        ErrorHandler $errorHandler,
        LoggerInterface $logger
    ) {
        $this->customerFactory = $customerFactory;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->socialLoginHelper = $socialLoginHelper;
        $this->errorHandler = $errorHandler;
        $this->logger = $logger;

        // Initialize Firebase if enabled and configured
        if ($this->socialLoginHelper->isFirebaseEnabled()) {
            $this->initializeFirebase();
        }

        parent::__construct($context);
    }

    /**
     * Execute social login callback
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if (!$this->socialLoginHelper->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Social login is not enabled.'));
            return $this->redirectToLogin();
        }

        if (!$this->firebase) {
            $this->logger->error('[MAB Social Login] Firebase is not configured properly.');
            $this->messageManager->addErrorMessage(__('Social login service is not available.'));
            return $this->redirectToLogin();
        }

        $idToken = $this->getRequest()->getParam('idToken');
        $provider = $this->getRequest()->getParam('provider', 'google');

        if (!$idToken) {
            $this->logger->error('[MAB Social Login] No ID token provided.');
            $this->messageManager->addErrorMessage(__('Authentication failed. Please try again.'));
            return $this->redirectToLogin();
        }

        return $this->errorHandler->executeWithErrorHandling(
            function () use ($idToken, $provider) {
                return $this->processAuthentication($idToken, $provider);
            },
            $this->redirectToLogin(),
            'Processing social login authentication'
        );
    }

    /**
     * Process authentication with Firebase
     *
     * @param string $idToken
     * @param string $provider
     * @return \Magento\Framework\Controller\Result\Redirect
     * @throws \Exception
     */
    private function processAuthentication(string $idToken, string $provider)
    {
        try {
            // Verify the ID token
            $verifiedIdToken = $this->firebase->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');
            
            if (!$email) {
                throw new \Exception('Email not provided by social login provider.');
            }

            // Get user information from Firebase
            $firebaseUser = $this->firebase->getUser($uid);
            
            $this->logger->info('[MAB Social Login] Authentication successful for: ' . $email . ' via ' . $provider);

            // Find or create customer
            $customer = $this->findOrCreateCustomer($firebaseUser, $provider);
            
            if (!$customer || !$customer->getId()) {
                throw new \Exception('Failed to create or find customer account.');
            }

            // Log in the customer
            $this->customerSession->setCustomerAsLoggedIn($customer);
            
            // Set up comprehensive session with optimization
            $rememberMe = $this->getRequest()->getParam('remember_me', false);
            $sessionOptimized = $this->socialLoginHelper->optimizeSessionSettings($customer->getId(), $rememberMe);
            
            // Success message with session info
            if ($sessionOptimized) {
                $deviceType = $this->socialLoginHelper->isMobileDevice() ? 'mobile' : 'desktop';
                $this->messageManager->addSuccessMessage(
                    __('Welcome %1! You are now logged in via %2 with extended session (optimized for %3).', 
                       $customer->getFirstname(),
                       ucfirst($provider),
                       $deviceType
                    )
                );
            } else {
                // Fallback to basic session setup
                $this->socialLoginHelper->setExtendedSession($customer->getId());
                $this->messageManager->addSuccessMessage(
                    __('Welcome %1! You have been successfully logged in via %2.', 
                       $customer->getFirstname(),
                       ucfirst($provider)
                    )
                );
            }
            
            $this->logger->info('[MAB Social Login] Customer logged in successfully: ' . $customer->getId());
            
            // Redirect to account dashboard
            return $this->redirectFactory->create()->setPath('customer/account');
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Social Login] Authentication error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Authentication failed: %1', $e->getMessage())
            );
            return $this->redirectToLogin();
        }
    }

    /**
     * Find existing customer or create new one
     *
     * @param \Kreait\Firebase\Auth\UserRecord $firebaseUser
     * @param string $provider
     * @return \Magento\Customer\Model\Customer|null
     * @throws \Exception
     */
    private function findOrCreateCustomer($firebaseUser, string $provider)
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $email = $firebaseUser->email;
        
        // Try to find existing customer
        $customer = $this->customerFactory->create()
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        if ($customer->getId()) {
            // Update last login info
            $customer->setData('last_social_login', date('Y-m-d H:i:s'));
            $customer->setData('social_login_provider', $provider);
            $customer->save();
            
            $this->logger->info('[MAB Social Login] Existing customer found: ' . $customer->getId());
            return $customer;
        }

        // Create new customer
        try {
            $newCustomer = $this->customerFactory->create();
            $newCustomer->setWebsiteId($websiteId);
            $newCustomer->setEmail($email);
            
            // Extract name information
            $displayName = $firebaseUser->displayName ?? '';
            if ($displayName) {
                $nameParts = explode(' ', $displayName, 2);
                $newCustomer->setFirstname($nameParts[0]);
                $newCustomer->setLastname($nameParts[1] ?? '');
            } else {
                $newCustomer->setFirstname('User');
                $newCustomer->setLastname('');
            }
            
            // Set secure random password
            $newCustomer->setPassword($this->generateSecurePassword());
            
            // Set social login attributes
            $newCustomer->setData('created_via_social_login', true);
            $newCustomer->setData('social_login_provider', $provider);
            $newCustomer->setData('social_login_uid', $firebaseUser->uid);
            $newCustomer->setData('last_social_login', date('Y-m-d H:i:s'));
            
            $newCustomer->save();
            
            $this->logger->info('[MAB Social Login] New customer created: ' . $newCustomer->getId());
            return $newCustomer;
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Social Login] Error creating customer: ' . $e->getMessage());
            throw new \Exception('Failed to create customer account: ' . $e->getMessage());
        }
    }

    /**
     * Initialize Firebase authentication
     *
     * @return void
     */
    private function initializeFirebase(): void
    {
        try {
            $firebaseConfig = $this->socialLoginHelper->getFirebaseConfig();
            if (empty($firebaseConfig)) {
                $this->logger->warning('[MAB Social Login] Firebase configuration is empty.');
                return;
            }
            
            // Use Firebase configuration to initialize
            $factory = new Factory();
            
            // If service account is configured, use it
            $serviceAccountJson = $this->scopeConfig->getValue('mab_social_login/firebase/service_account');
            if ($serviceAccountJson) {
                $factory = $factory->withServiceAccount($serviceAccountJson);
            }
            
            $this->firebase = $factory->createAuth();
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Social Login] Firebase initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Generate secure password for social login users
     *
     * @return string
     */
    private function generateSecurePassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Redirect to login page
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirectToLogin()
    {
        return $this->redirectFactory->create()->setPath('customer/account/login');
    }
}
