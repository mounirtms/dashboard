<?php
namespace Mab\CheckoutCustomization\Plugin\Block\Cart;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Mab\License\Helper\Data as LicenseHelper;

class CouponPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LicenseHelper
     */
    private $licenseHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LicenseHelper $licenseHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LicenseHelper $licenseHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->licenseHelper = $licenseHelper;
    }

    /**
     * Before toHtml hook to set discount disabled flag
     *
     * @param \Magento\Checkout\Block\Cart\Coupon $subject
     * @return void
     */
    public function beforeToHtml(\Magento\Checkout\Block\Cart\Coupon $subject)
    {
        if (!$this->licenseHelper->isLicenseActive()) {
            return;
        }

        $isDisabled = $this->scopeConfig->isSetFlag(
            'mab_checkout/discount_settings/disable_discount_code',
            ScopeInterface::SCOPE_STORE
        );
        $subject->setData('is_discount_disabled', $isDisabled);
    }
}
