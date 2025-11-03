<?php
namespace Mab\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    const XML_PATH_CHECKOUT_CUSTOMIZATION_ENABLED = 'mab_core/module_management/checkout_customization_enabled';
    const XML_PATH_VISUAL_EFFECTS_ENABLED = 'mab_core/module_management/visual_effects_enabled';
    const XML_PATH_SOCIAL_LOGIN_ENABLED = 'mab_core/module_management/social_login_enabled';
    const XML_PATH_DEBUG_MODE = 'mab_core/general_settings/debug_mode';
    const XML_PATH_LOG_ENABLED = 'mab_core/general_settings/log_enabled';
    const XML_PATH_LOGO_ENABLED = 'mab_core/general_settings/logo_enabled';
    const XML_PATH_LOGO_PATH = 'mab_core/general_settings/logo_path';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    /**
     * Check if Checkout Customization module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCheckoutCustomizationEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_CHECKOUT_CUSTOMIZATION_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking checkout customization status', $e);
            return false;
        }
    }

    /**
     * Check if Visual Effects module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isVisualEffectsEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_VISUAL_EFFECTS_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking visual effects status', $e);
            return false;
        }
    }

    /**
     * Check if Social Login module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSocialLoginEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_SOCIAL_LOGIN_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking social login status', $e);
            return false;
        }
    }

    /**
     * Check if debug mode is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugModeEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_DEBUG_MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking debug mode status', $e);
            return false;
        }
    }

    /**
     * Check if logging is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLoggingEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_LOG_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking logging status', $e);
            return false;
        }
    }

    /**
     * Check if custom logo is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLogoEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_LOGO_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking logo status', $e);
            return false;
        }
    }

    /**
     * Get custom logo path
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getLogoPath($storeId = null)
    {
        try {
            if (!$this->isLogoEnabled($storeId)) {
                return null;
            }

            return $this->scopeConfig->getValue(
                self::XML_PATH_LOGO_PATH,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error getting logo path', $e);
            return null;
        }
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
            $this->logError("Error getting config value for path: {$path}", $e);
            return $default;
        }
    }

    /**
     * Check if configuration flag is set with error handling
     *
     * @param string $path
     * @param int|null $storeId
     * @param bool $default
     * @return bool
     */
    public function isSetFlag($path, $storeId = null, $default = false)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                $path,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError("Error checking flag for path: {$path}", $e);
            return $default;
        }
    }

    /**
     * Log error with context
     *
     * @param string $message
     * @param \Exception $exception
     * @return void
     */
    private function logError($message, \Exception $exception)
    {
        if ($this->isLoggingEnabled()) {
            $this->logger->error('[MAB Core] ' . $message, [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
        }
    }
} 