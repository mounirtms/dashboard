<?php
namespace Mab\CheckoutCustomization\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Mab\Core\Helper\Data as CoreHelper;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'mab_checkout/general/enabled';
    const XML_PATH_DEBUG_MODE = 'mab_checkout/general/debug_mode';
    const XML_PATH_DISABLE_DISCOUNT = 'mab_checkout/discount_settings/disable_discount_code';
    const XML_PATH_CUSTOM_MESSAGE = 'mab_checkout/discount_settings/custom_message';
    const XML_PATH_REGION_OPTIONS = 'mab_checkout/general/region_options';
    const XML_PATH_MIN_CART_TOTAL = 'mab_checkout/general/min_cart_total';
    const XML_PATH_ENABLE_SHIPPING_STEP = 'mab_checkout/checkout_steps/enable_shipping_step';
    const XML_PATH_ENABLE_PAYMENT_STEP = 'mab_checkout/checkout_steps/enable_payment_step';
    const XML_PATH_ENABLE_REVIEW_STEP = 'mab_checkout/checkout_steps/enable_review_step';
    const XML_PATH_ALLOW_GUEST_CHECKOUT = 'mab_checkout/checkout_steps/allow_guest_checkout';

    /**
     * @var CoreHelper
     */
    private $coreHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param CoreHelper $coreHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CoreHelper $coreHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->coreHelper = $coreHelper;
        $this->logger = $logger;
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isModuleEnabled($storeId = null)
    {
        try {
            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) && $this->coreHelper->isCheckoutCustomizationEnabled($storeId);
        } catch (\Exception $e) {
            $this->logError('Error checking if module is enabled', $e);
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
            return $this->isModuleEnabled($storeId) && $this->scopeConfig->isSetFlag(
                self::XML_PATH_DEBUG_MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking debug mode', $e);
            return false;
        }
    }

    /**
     * Check if discount code field should be disabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDiscountCodeDisabled($storeId = null)
    {
        try {
            if (!$this->isModuleEnabled($storeId)) {
                return false;
            }

            return $this->scopeConfig->isSetFlag(
                self::XML_PATH_DISABLE_DISCOUNT,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking discount code status', $e);
            return false;
        }
    }

    /**
     * Get custom message to display instead of discount code
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCustomMessage($storeId = null)
    {
        try {
            return (string)$this->scopeConfig->getValue(
                self::XML_PATH_CUSTOM_MESSAGE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error getting custom message', $e);
            return '';
        }
    }

    /**
     * Get region options as array
     *
     * @param int|null $storeId
     * @return array
     */
    public function getRegionOptions($storeId = null)
    {
        try {
            $regionOptions = $this->scopeConfig->getValue(
                self::XML_PATH_REGION_OPTIONS,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if (empty($regionOptions)) {
                return [];
            }

            $decoded = json_decode($regionOptions, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON in region options: ' . json_last_error_msg());
            }

            return is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            $this->logError('Error getting region options', $e);
            return [];
        }
    }

    /**
     * Get minimum cart total for free delivery
     *
     * @param int|null $storeId
     * @return float
     */
    public function getMinCartTotal($storeId = null)
    {
        try {
            $value = $this->scopeConfig->getValue(
                self::XML_PATH_MIN_CART_TOTAL,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            return (float)$value;
        } catch (\Exception $e) {
            $this->logError('Error getting minimum cart total', $e);
            return 0.0;
        }
    }

    /**
     * Check if shipping step is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShippingStepEnabled($storeId = null)
    {
        try {
            return $this->isModuleEnabled($storeId) && $this->scopeConfig->isSetFlag(
                self::XML_PATH_ENABLE_SHIPPING_STEP,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking shipping step status', $e);
            return true; // Default to enabled
        }
    }

    /**
     * Check if payment step is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPaymentStepEnabled($storeId = null)
    {
        try {
            return $this->isModuleEnabled($storeId) && $this->scopeConfig->isSetFlag(
                self::XML_PATH_ENABLE_PAYMENT_STEP,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking payment step status', $e);
            return true; // Default to enabled
        }
    }

    /**
     * Check if review step is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isReviewStepEnabled($storeId = null)
    {
        try {
            return $this->isModuleEnabled($storeId) && $this->scopeConfig->isSetFlag(
                self::XML_PATH_ENABLE_REVIEW_STEP,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking review step status', $e);
            return true; // Default to enabled
        }
    }

    /**
     * Check if guest checkout is allowed
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGuestCheckoutAllowed($storeId = null)
    {
        try {
            return $this->isModuleEnabled($storeId) && $this->scopeConfig->isSetFlag(
                self::XML_PATH_ALLOW_GUEST_CHECKOUT,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            $this->logError('Error checking guest checkout status', $e);
            return true; // Default to enabled
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
        if ($this->isDebugModeEnabled()) {
            $this->logger->error('[MAB Checkout] ' . $message, [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
        }
    }
}
