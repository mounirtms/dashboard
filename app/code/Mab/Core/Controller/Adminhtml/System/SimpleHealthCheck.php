<?php
namespace Mab\Core\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Simple Health Check Controller for MAB Modules
 */
class SimpleHealthCheck extends Action
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;
    
    /**
     * @var ModuleManager
     */
    private $moduleManager;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * MAB Modules to monitor
     */
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

    /**
     * Constructor
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ModuleManager $moduleManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->moduleManager = $moduleManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Execute health check
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        
        try {
            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'modules' => $this->checkModuleStatus(),
                'social_login' => $this->checkSocialLoginConfig(),
                'oauth_diagnosis' => $this->diagnoseOAuthIssue()
            ];
            
            return $resultJson->setData([
                'success' => true,
                'report' => $report
            ]);
            
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check module status
     */
    private function checkModuleStatus(): array
    {
        $moduleStatus = [];
        
        foreach ($this->mabModules as $moduleName => $description) {
            $isEnabled = $this->moduleManager->isEnabled($moduleName);
            $moduleStatus[$moduleName] = [
                'name' => $description,
                'enabled' => $isEnabled,
                'status' => $isEnabled ? 'OK' : 'DISABLED'
            ];
        }
        
        return $moduleStatus;
    }

    /**
     * Check social login configuration
     */
    private function checkSocialLoginConfig(): array
    {
        return [
            'enabled' => $this->scopeConfig->isSetFlag('mab_social_login/general/enabled'),
            'google_enabled' => $this->scopeConfig->isSetFlag('mab_social_login/providers/google_enabled'),
            'google_client_id' => $this->scopeConfig->getValue('mab_social_login/google/client_id'),
            'extended_lifetime_enabled' => $this->scopeConfig->isSetFlag('mab_social_login/session/extended_lifetime_enabled'),
            'session_lifetime_hours' => (int)$this->scopeConfig->getValue('mab_social_login/session/session_lifetime') ?: 720,
            'mobile_session_lifetime_hours' => (int)$this->scopeConfig->getValue('mab_social_login/session/mobile_extended_lifetime') ?: 2160
        ];
    }

    /**
     * Diagnose OAuth issue
     */
    private function diagnoseOAuthIssue(): array
    {
        $googleClientId = $this->scopeConfig->getValue('mab_social_login/google/client_id');
        $googleClientSecret = $this->scopeConfig->getValue('mab_social_login/google/client_secret');
        
        $diagnosis = [
            'client_id_configured' => !empty($googleClientId) && $googleClientId !== 'bot',
            'client_secret_configured' => !empty($googleClientSecret),
            'current_client_id' => $googleClientId,
            'issues' => [],
            'recommendations' => []
        ];
        
        // Identify issues
        if (empty($googleClientId) || $googleClientId === 'bot') {
            $diagnosis['issues'][] = 'Google Client ID is not properly configured (currently set to "bot")';
            $diagnosis['recommendations'][] = 'Configure proper Google OAuth Client ID in Google Cloud Console';
        }
        
        if (empty($googleClientSecret)) {
            $diagnosis['issues'][] = 'Google Client Secret is not configured';
            $diagnosis['recommendations'][] = 'Configure Google OAuth Client Secret in admin panel';
        }
        
        // OAuth Setup Instructions
        $diagnosis['setup_instructions'] = [
            '1. Go to Google Cloud Console (https://console.cloud.google.com/)',
            '2. Create or select a project',
            '3. Enable Google+ API or Google Sign-In API',
            '4. Go to Credentials → Create Credentials → OAuth 2.0 Client IDs',
            '5. Set Application Type to "Web Application"',
            '6. Add Authorized JavaScript origins: https://beta.technostationery.com',
            '7. Add Authorized redirect URIs: https://beta.technostationery.com/mab_social/auth/callback',
            '8. Copy Client ID and Client Secret to Magento admin',
            '9. Enable Google Login in Social Login configuration'
        ];
        
        return $diagnosis;
    }

    /**
     * Check ACL permissions
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Mab_Core::config');
    }
}