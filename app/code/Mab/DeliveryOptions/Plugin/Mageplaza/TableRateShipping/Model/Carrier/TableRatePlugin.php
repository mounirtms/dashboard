<?php
namespace Mab\DeliveryOptions\Plugin\Mageplaza\TableRateShipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class TableRatePlugin
{
    const XML_PATH_FREE_SHIPPING_ENABLED = 'carriers/yalidine/free_shipping_enabled';
    const XML_PATH_FREE_SHIPPING_MIN_AMOUNT = 'carriers/yalidine/free_shipping_minimum';
    const XML_PATH_DEBUG_ENABLED = 'carriers/yalidine/debug_enabled';
    
    const YALIDINE_METHOD_CODES = [
        '2',   // Home delivery
        '24'   // Agency pickup
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->rateResultFactory = $rateResultFactory;
    }

    /**
     * Override Yalidin method rates to make them free when conditions are met
     * ONLY during frontend checkout - preserve admin configuration
     *
     * @param \Mageplaza\TableRateShipping\Model\Carrier\TableRate $subject
     * @param Result $result
     * @param RateRequest $request
     * @return Result
     */
    public function aroundCollectRates(
        \Mageplaza\TableRateShipping\Model\Carrier\TableRate $subject,
        callable $proceed,
        RateRequest $request
    ) {
        try {
            // Skip ALL plugin logic in admin area to preserve configuration
            if ($this->isAdminArea()) {
                $this->debugLog('Admin area detected - bypassing all plugin logic to preserve configuration');
                return $proceed($request);
            }
            
            // Proceed to collect original rates first
            $result = $proceed($request);
            if (!$result) {
                $this->debugLog('No rates returned from original carrier');
                return $result;
            }

            // Skip override logic for admin area to preserve configuration
            if ($this->isAdminArea()) {
                $this->debugLog('Admin area detected - skipping rate overrides to preserve configuration');
                return $result;
            }

            $this->debugLog('Processing shipping rates for Yalidin override');
            
            // Get configuration values with error handling
            $freeEnabled = $this->isEnabled();
            if (!$freeEnabled) {
                $this->debugLog('Free shipping override is disabled');
                return $result;
            }

            $minAmount = $this->getMinimumAmount();
            $cartTotal = $this->getCartTotal($request);
            
            if ($cartTotal === null) {
                $this->debugLog('Unable to determine cart total, skipping free shipping override');
                return $result;
            }

            // SKU-based eligibility with error handling
            $eligibleSkus = $this->getEligibleSkus();
            $excludedSkus = $this->getExcludedSkus();
            $skuEligible = $this->isQuoteSkuEligible($request, $eligibleSkus, $excludedSkus);
            
            $isFreeShippingEligible = $minAmount > 0 && 
                                    (float)$cartTotal >= (float)$minAmount && 
                                    $skuEligible;
            
            $this->debugLog(sprintf(
                'Free shipping check: Cart total %.2f, Min amount %.2f, SKU eligible: %s, Eligible: %s',
                $cartTotal,
                $minAmount,
                $skuEligible ? 'Yes' : 'No',
                $isFreeShippingEligible ? 'Yes' : 'No'
            ));

            // Build filtered result applying region restrictions and free override
            $destRegionId = (int) $request->getDestRegionId();
            $destCountryId = (string) $request->getDestCountryId();
            $areaAllowed = ($destCountryId === 'DZ');

            $filteredResult = $this->rateResultFactory->create();

            foreach ($result->getAllRates() as $rate) {
                try {
                    // Only remove legacy custom carrier in frontend, preserve for admin
                    if ($rate->getCarrier() === 'yalidine' && !$this->isAdminArea()) {
                        $this->debugLog('Removing legacy custom carrier rate: yalidine (frontend only)');
                        continue;
                    }

                    $method = (string) $rate->getMethod();
                    $methodCode = $this->normalizeMethodCode($method);

                    if (in_array($methodCode, self::YALIDINE_METHOD_CODES, true)) {
                        if (!$areaAllowed) {
                            $this->debugLog(sprintf(
                                'Removing Yalidin method %s due to destination restriction (country: %s, region ID: %d)', 
                                $methodCode, 
                                $destCountryId, 
                                $destRegionId
                            ));
                            continue;
                        }

                        if ($isFreeShippingEligible) {
                            $originalPrice = $rate->getPrice();
                            $this->debugLog(sprintf(
                                'Overriding Yalidin method %s: Original price %.2f -> Free (0.00)',
                                $methodCode,
                                $originalPrice
                            ));

                            $rate->setPrice(0);
                            $rate->setCost(0);

                            $this->updateMethodTitle($rate);
                        }
                    }

                    $filteredResult->append($rate);
                } catch (\Exception $e) {
                    $this->logger->error('[MAB Delivery] Error processing rate: ' . $e->getMessage(), [
                        'method' => $rate->getMethod() ?? 'unknown',
                        'carrier' => $rate->getCarrier() ?? 'unknown',
                        'exception' => $e->getTraceAsString()
                    ]);
                    // Continue processing other rates
                    $filteredResult->append($rate);
                }
            }

            return $filteredResult;

        } catch (\Exception $e) {
            $this->logger->error('[MAB Delivery] Critical error in rate collection: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            // Return original result on critical error
            return $result ?? $this->rateResultFactory->create();
        }
    }

    /**
     * Get cart total with fallback options
     *
     * @param RateRequest $request
     * @return float|null
     */
    private function getCartTotal(RateRequest $request): ?float
    {
        try {
            $cartTotal = $request->getBaseSubtotalInclTax();
            if ($cartTotal !== null) {
                return (float)$cartTotal;
            }

            // Fallbacks if subtotal including tax is not available
            $cartTotal = $request->getPackageValueWithDiscount();
            if ($cartTotal !== null) {
                return (float)$cartTotal;
            }

            $cartTotal = $request->getPackageValue();
            if ($cartTotal !== null) {
                return (float)$cartTotal;
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('[MAB Delivery] Error getting cart total: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize method code by removing prefixes
     *
     * @param string $method
     * @return string
     */
    private function normalizeMethodCode(string $method): string
    {
        // Normalize code if Mageplaza prefixes method with 'mptablerate_'
        if (strpos($method, 'mptablerate_') === 0) {
            return substr($method, strlen('mptablerate_'));
        }
        return $method;
    }

    /**
     * Update method title to indicate free shipping
     *
     * @param \Magento\Quote\Model\Quote\Address\RateResult\Method $rate
     * @return void
     */
    private function updateMethodTitle($rate): void
    {
        try {
            $originalTitle = (string) $rate->getMethodTitle();
            if ($originalTitle !== '' && strpos($originalTitle, 'Gratuit') === false) {
                $rate->setMethodTitle($originalTitle . ' - Livraison Gratuite');
            }
        } catch (\Exception $e) {
            $this->logger->error('[MAB Delivery] Error updating method title: ' . $e->getMessage());
        }
    }

    /**
     * Get eligible SKUs list
     *
     * @return array
     */
    private function getEligibleSkus(): array
    {
        try {
            $eligibleSkusCfg = (string) $this->scopeConfig->getValue(
                'carriers/yalidine/free_shipping_eligible_skus', 
                ScopeInterface::SCOPE_STORE
            );
            return $this->parseSkuList($eligibleSkusCfg);
        } catch (\Exception $e) {
            $this->logger->error('[MAB Delivery] Error getting eligible SKUs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get excluded SKUs list
     *
     * @return array
     */
    private function getExcludedSkus(): array
    {
        try {
            $excludedSkusCfg = (string) $this->scopeConfig->getValue(
                'carriers/yalidine/free_shipping_excluded_skus', 
                ScopeInterface::SCOPE_STORE
            );
            return $this->parseSkuList($excludedSkusCfg);
        } catch (\Exception $e) {
            $this->logger->error('[MAB Delivery] Error getting excluded SKUs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if free shipping override is enabled
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FREE_SHIPPING_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get minimum amount for free shipping
     *
     * @return float
     */
    private function getMinimumAmount(): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_MIN_AMOUNT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Debug logging
     *
     * @param string $message
     */
    private function debugLog(string $message): void
    {
        if ($this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_ENABLED, ScopeInterface::SCOPE_STORE)) {
            $this->logger->info('[MAB Delivery] ' . $message);
        }
    }

    /**
     * Parse CSV or newline separated SKUs
     */
    private function parseSkuList(string $cfg): array
    {
        if ($cfg === '') {
            return [];
        }
        $normalized = str_replace(["\r", ";"], ["", ","], $cfg);
        $parts = preg_split('/[\n,]+/', $normalized);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, static function ($v) { return $v !== ''; });
        return array_values(array_unique($parts));
    }

    /**
     * Check if we're in admin area
     *
     * @return bool
     */
    private function isAdminArea(): bool
    {
        try {
            // Check if we're in admin context
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $state = $objectManager->get(\Magento\Framework\App\State::class);
            return $state->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE;
        } catch (\Exception $e) {
            // Default to false if we can't determine area
            return false;
        }
    }

    /**
     * Determine SKU eligibility based on include/exclude lists
     */
    private function isQuoteSkuEligible(RateRequest $request, array $eligibleSkus, array $excludedSkus): bool
    {
        if (empty($eligibleSkus) && empty($excludedSkus)) {
            return true; // no restrictions
        }

        $items = $request->getAllItems();
        if (!is_array($items) || empty($items)) {
            return false; // cannot validate
        }

        // Flatten and filter out child items (bundle/configurable) by checking parent
        $skus = [];
        foreach ($items as $item) {
            if (method_exists($item, 'getParentItem') && $item->getParentItem()) {
                continue; // skip children
            }
            if (method_exists($item, 'getSku')) {
                $skus[] = (string) $item->getSku();
            }
        }

        if (!empty($excludedSkus)) {
            foreach ($skus as $sku) {
                if (in_array($sku, $excludedSkus, true)) {
                    return false;
                }
            }
        }

        if (!empty($eligibleSkus)) {
            $anyEligible = false;
            foreach ($skus as $sku) {
                if (in_array($sku, $eligibleSkus, true)) {
                    $anyEligible = true;
                    break;
                }
            }
            if (!$anyEligible) {
                return false; // at least one must be eligible
            }
        }

        return true;
    }
}