<?php
namespace Mab\CheckoutCustomization\Plugin;

use Magento\Checkout\Block\Checkout\LayoutProcessor as CheckoutLayoutProcessor;
use Mab\CheckoutCustomization\Helper\Data as Helper;
use Mab\License\Helper\Data as LicenseHelper;

class LayoutProcessor
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var LicenseHelper
     */
    private $licenseHelper;

    /**
     * @param Helper $helper
     * @param LicenseHelper $licenseHelper
     */
    public function __construct(
        Helper $helper,
        LicenseHelper $licenseHelper
    ) {
        $this->helper = $helper;
        $this->licenseHelper = $licenseHelper;
    }

    /**
     * Process layout to conditionally disable discount component
     *
     * @param CheckoutLayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function afterProcess(CheckoutLayoutProcessor $subject, array $jsLayout)
    {
        if (!$this->licenseHelper->isLicenseActive() || !$this->helper->isModuleEnabled()) {
            return $jsLayout;
        }

        if ($this->helper->isDiscountCodeDisabled()) {
            $this->disableDiscountComponent($jsLayout);
        }

        return $jsLayout;
    }

    /**
     * Disable the discount component in the checkout layout
     *
     * @param array &$jsLayout
     * @return void
     */
    private function disableDiscountComponent(array &$jsLayout)
    {
        // Remove from summary section
        if (isset($jsLayout['components']['checkout']['children']['sidebar']['children']['summary']['children']['discount'])) {
            unset($jsLayout['components']['checkout']['children']['sidebar']['children']['summary']['children']['discount']);
        }

        // Remove from totals section if it exists
        if (isset($jsLayout['components']['checkout']['children']['sidebar']['children']['summary']['children']['totals']['children']['discount'])) {
            unset($jsLayout['components']['checkout']['children']['sidebar']['children']['summary']['children']['totals']['children']['discount']);
        }

        // Remove from payment section if it exists
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['discount'])) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['discount']);
        }

        // Remove from shipping step if it exists
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['discount'])) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['discount']);
        }
    }
}
