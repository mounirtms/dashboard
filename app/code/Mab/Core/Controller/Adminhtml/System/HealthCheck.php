<?php
namespace Mab\Core\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Mab\SocialLogin\Helper\Data as SocialLoginHelper;
use Psr\Log\LoggerInterface;

/**
 * Health Check Controller for MAB Modules
 */
class HealthCheck extends Action
{
    private $jsonFactory;
    private $moduleManager;
    private $scopeConfig;
    private $socialLoginHelper;
    private $logger;
    
    private $mabModules = [
        'Mab_Core' => 'Core Framework',
        'Mab_License' => 'License Management',
        'Mab_SocialLogin' => 'Social Login',
        'Mab_DeliveryOptions' => 'Delivery Options',
        'Mab_CheckoutCustomization' => 'Checkout Customization',
        'Mab_VisualEffects' => 'Visual Effects',
        'Mab_AdminLocale' => 'Admin Locale',
        'Mab_SourceSelector' => 'Source Selector',
        'Mab_GuestCheckout' => 'Guest Checkout',
        'Mab_Theme' => 'Theme Enhancement'
    ];

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ModuleManager $moduleManager,
        ScopeConfigInterface $scopeConfig,
        SocialLoginHelper $socialLoginHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->moduleManager = $moduleManager;
        $this->scopeConfig = $scopeConfig;
        $this->socialLoginHelper = $socialLoginHelper;
        $this->logger = $logger;
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        
        try {
            $action = $this->getRequest()->getParam('action', 'full_check');
            
            switch ($action) {
                case 'oauth_check':
                    $report = $this->checkOAuthConfiguration();
                    break;
                case 'social_login':
                    $report = $this->checkSocialLoginDetailed();
                    break;
                case 'modules':
                    $report = $this->checkModuleStatus();
                    break;
                default:
                    $report = $this->performFullHealthCheck();
            }
            
            return $resultJson->setData([
                'success' => true,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s'),
                'report' => $report
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('[MAB Health Check] Error: ' . $e->getMessage());
            
            return $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    private function performFullHealthCheck(): array
    {
        return [
            'modules' => $this->checkModuleStatus(),
            'social_login' => $this->checkSocialLoginDetailed(),
            'oauth_configuration' => $this->checkOAuthConfiguration(),
            'system_info' => $this->getSystemInfo(),
            'recommendations' => $this->generateRecommendations()
        ];
    }

    private function checkModuleStatus(): array
    {
        $moduleStatus = [];
        $enabledCount = 0;
        
        foreach ($this->mabModules as $moduleName => $description) {
            $isEnabled = $this->moduleManager->isEnabled($moduleName);
            if ($isEnabled) {
                $enabledCount++;
            }
            
            $moduleStatus[$moduleName] = [
                'name' => $description,
                'enabled' => $isEnabled,
                'status' => $isEnabled ? 'OK' : 'DISABLED'
            ];
        }
        
        return [
            'modules' => $moduleStatus,
            'summary' => [
                'total' => count($this->mabModules),
                'enabled' => $enabledCount,
                'disabled' => count($this->mabModules) - $enabledCount,
                'health_score' => round(($enabledCount / count($this->mabModules)) * 100, 2)
            ]
        ];
    }

    private function checkSocialLoginDetailed(): array
    {
        try {
            $config = $this->socialLoginHelper->getComprehensiveConfig();
            
            $sessionDiagnostics = [];
            if (method_exists($this->socialLoginHelper, 'getSessionDiagnostics')) {
                $sessionDiagnostics = $this->socialLoginHelper->getSessionDiagnostics();
            }
            
            return [
                'configuration' => $config,
                'session_diagnostics' => $sessionDiagnostics,
                'status' => $this->determineSocialLoginStatus($config),
                'optimizations' => [
                    'extended_lifetime' => $config['extended_lifetime_enabled'] ?? false,
                    'session_lifetime_hours' => $config['session_lifetime'] ?? 720,
                    'mobile_lifetime_hours' => $config['mobile_extended_lifetime'] ?? 2160
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'status' => 'ERROR'
            ];
        }
    }

    private function checkOAuthConfiguration(): array
    {
        $googleClientId = $this->scopeConfig->getValue('mab_social_login/google/client_id');
        $googleClientSecret = $this->scopeConfig->getValue('mab_social_login/google/client_secret');
        $firebaseEnabled = $this->scopeConfig->isSetFlag('mab_social_login/firebase/firebase_enabled');
        $firebaseProject = $this->scopeConfig->getValue('mab_social_login/firebase/project_id');
        
        $diagnosis = [
            'google_client_id' => $googleClientId,
            'client_id_valid' => $this->validateGoogleClientId($googleClientId),
            'client_secret_configured' => !empty($googleClientSecret),
            'firebase_enabled' => $firebaseEnabled,
            'firebase_project' => $firebaseProject,
            'issues' => [],
            'recommendations' => []
        ];
        
        if (!$diagnosis['client_id_valid']) {
            $diagnosis['issues'][] = 'Google Client ID is invalid (currently: "' . $googleClientId . '")';
            $diagnosis['recommendations'][] = 'Configure valid Google OAuth Client ID from Google Cloud Console';
        }
        
        if (!$diagnosis['client_secret_configured']) {
            $diagnosis['issues'][] = 'Google Client Secret is not configured';
            $diagnosis['recommendations'][] = 'Set Google OAuth Client Secret in admin configuration';
        }
        
        $diagnosis['setup_guide'] = [
            '1. Go to Google Cloud Console (https://console.cloud.google.com/)',
            '2. Select project: ' . ($firebaseProject ?: 'techno-magento'),
            '3. Enable APIs: Google+ API, Google Sign-In API',
            '4. Create OAuth 2.0 Client ID (Web application)',
            '5. Set authorized origins: https://beta.technostationery.com',
            '6. Set redirect URI: https://beta.technostationery.com/mab_social/auth/callback',
            '7. Copy Client ID and Secret to Magento configuration'
        ];
        
        return $diagnosis;
    }

    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'magento_mode' => $this->scopeConfig->getValue('dev/front_end_development_workflow/type') ?: 'default',
            'cache_enabled' => $this->scopeConfig->isSetFlag('system/full_page_cache/caching_application'),
            'social_login_enabled' => $this->scopeConfig->isSetFlag('mab_social_login/general/enabled'),
            'extended_sessions' => $this->scopeConfig->isSetFlag('mab_social_login/session/extended_lifetime_enabled'),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];
        
        $googleClientId = $this->scopeConfig->getValue('mab_social_login/google/client_id');
        if (!$this->validateGoogleClientId($googleClientId)) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'OAuth Configuration',
                'message' => 'Configure valid Google OAuth Client ID to enable social login',
                'action' => 'Update Google Client ID in Social Login configuration'
            ];
        }
        
        $extendedLifetime = $this->scopeConfig->isSetFlag('mab_social_login/session/extended_lifetime_enabled');
        if (!$extendedLifetime) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Session Management',
                'message' => 'Enable extended session lifetime for better user experience',
                'action' => 'Enable extended sessions in Social Login > Session Management'
            ];
        }
        
        return $recommendations;
    }

    private function validateGoogleClientId(?string $clientId): bool
    {
        if (empty($clientId) || $clientId === 'bot') {
            return false;
        }
        
        return (bool)preg_match('/^[0-9]+-[a-zA-Z0-9]+\.apps\.googleusercontent\.com$/', $clientId);
    }

    private function determineSocialLoginStatus(array $config): string
    {
        if (!($config['enabled'] ?? false)) {
            return 'DISABLED';
        }
        
        if (!$this->validateGoogleClientId($config['google_client_id'] ?? null)) {
            return 'NEEDS_OAUTH_CONFIGURATION';
        }
        
        if ($config['extended_lifetime_enabled'] ?? false) {
            return 'OPTIMIZED';
        }
        
        return 'BASIC';
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Mab_Core::config');
    }
}