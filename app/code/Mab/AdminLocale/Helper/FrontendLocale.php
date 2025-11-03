<?php
namespace Mab\AdminLocale\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\State;

class FrontendLocale extends AbstractHelper
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @param Context $context
     * @param State $appState
     */
    public function __construct(
        Context $context,
        State $appState
    ) {
        parent::__construct($context);
        $this->appState = $appState;
    }

    /**
     * Check if we're in frontend area
     *
     * @return bool
     */
    public function isFrontendArea()
    {
        try {
            return $this->appState->getAreaCode() === 'frontend';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if we're in admin area
     *
     * @return bool
     */
    public function isAdminArea()
    {
        try {
            return $this->appState->getAreaCode() === 'adminhtml';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get required frontend locale
     *
     * @return string
     */
    public function getRequiredFrontendLocale()
    {
        return 'fr_FR';
    }

    /**
     * Get required admin locale
     *
     * @return string
     */
    public function getRequiredAdminLocale()
    {
        return 'en_US';
    }

    /**
     * Check if locale enforcement is enabled
     *
     * @return bool
     */
    public function isLocaleEnforcementEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'mab_admin_locale/locale_settings/force_english_admin',
            ScopeInterface::SCOPE_STORE
        );
    }
}
