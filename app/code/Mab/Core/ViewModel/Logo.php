<?php
namespace Mab\Core\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class Logo implements ArgumentInterface
{
    const XML_PATH_LOGO_ENABLED = 'mab_core/general_settings/logo_enabled';
    const XML_PATH_LOGO_PATH = 'mab_core/general_settings/logo_path';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Logo constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Check if custom logo is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLogoEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LOGO_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get logo URL
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getLogoUrl($storeId = null)
    {
        if (!$this->isLogoEnabled($storeId)) {
            return null;
        }

        $logoPath = $this->scopeConfig->getValue(
            self::XML_PATH_LOGO_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$logoPath) {
            return null;
        }

        $mediaBaseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return $mediaBaseUrl . 'mab/logo/' . $logoPath;
    }
}
