<?php
namespace Mab\DeliveryOptions\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Mab\Core\Helper\ErrorHandler;
use Mab\VisualEffects\Helper\Data as VisualEffectsHelper;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Optimized Shipping Conditions Helper
 * 
 * High-performance helper for checking free shipping conditions with advanced caching,
 * memory optimization, and efficient processing algorithms.
 */
class ShippingConditions extends AbstractHelper
{
    // Configuration paths
    const XML_PATH_FREE_SHIPPING_ENABLED = 'carriers/yalidine/free_shipping_enabled';
    const XML_PATH_FREE_SHIPPING_MIN_AMOUNT = 'carriers/yalidine/free_shipping_minimum';
    const XML_PATH_FREE_SHIPPING_ELIGIBLE_SKUS = 'carriers/yalidine/free_shipping_eligible_skus';
    const XML_PATH_FREE_SHIPPING_EXCLUDED_SKUS = 'carriers/yalidine/free_shipping_excluded_skus';
    const XML_PATH_FREE_SHIPPING_CUSTOMER_GROUPS = 'carriers/yalidine/free_shipping_customer_groups';
    const XML_PATH_FREE_SHIPPING_CATEGORIES = 'carriers/yalidine/free_shipping_categories';
    const XML_PATH_FREE_SHIPPING_TIME_RESTRICTIONS = 'carriers/yalidine/free_shipping_time_restrictions';
    const XML_PATH_FREE_SHIPPING_DAYS_OF_WEEK = 'carriers/yalidine/free_shipping_days_of_week';
    const XML_PATH_FREE_SHIPPING_VISUAL_EFFECTS = 'carriers/yalidine/free_shipping_visual_effects';
    const XML_PATH_FREE_SHIPPING_CELEBRATION_EFFECT = 'carriers/yalidine/free_shipping_celebration_effect';
    const XML_PATH_DEBUG_ENABLED = 'carriers/yalidine/debug_enabled';

    // Cache settings
    const CACHE_TAG = 'mab_shipping_conditions';
    const CACHE_LIFETIME = 1800; // 30 minutes
    const MEMORY_CACHE_LIMIT = 100; // Maximum items in memory cache

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @var VisualEffectsHelper
     */
    private $visualEffectsHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var array
     */
    private $conditionCache = [];

    /**
     * @var array
     */
    private $configCache = [];

    /**
     * @var array
     */
    private $skuCache = [];

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param ErrorHandler $errorHandler
     * @param VisualEffectsHelper $visualEffectsHelper
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ErrorHandler $errorHandler,
        VisualEffectsHelper $visualEffectsHelper,
        LoggerInterface $logger,
        CacheInterface $cache,
        SerializerInterface $serializer,
        DateTime $dateTime
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->errorHandler = $errorHandler;
        $this->visualEffectsHelper = $visualEffectsHelper;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->dateTime = $dateTime;
    }

    /**
     * Check if free shipping conditions are met (optimized version)
     *
     * @param float|null $cartTotal
     * @param array|null $cartItems
     * @param string|null $destinationCountry
     * @param int|null $storeId
     * @return array
     */
    public function checkFreeShippingConditions(
        ?float $cartTotal = null,
        ?array $cartItems = null,
        ?string $destinationCountry = null,
        ?int $storeId = null
    ): array {
        $startTime = microtime(true);
        
        // Generate optimized cache key
        $cacheKey = $this->generateOptimizedCacheKey(
            $cartTotal, 
            $cartItems, 
            $destinationCountry, 
            $storeId
        );
        
        // Check memory cache first (fastest)
        if (isset($this->conditionCache[$cacheKey])) {
            $this->debugLog('Condition result from memory cache', [
                'cache_key' => $cacheKey,
                'performance' => (microtime(true) - $startTime) * 1000 . 'ms'
            ]);
            return $this->conditionCache[$cacheKey];
        }

        // Check persistent cache
        $cachedResult = $this->cache->load($cacheKey);
        if ($cachedResult) {
            $result = $this->serializer->unserialize($cachedResult);
            $this->addToMemoryCache($cacheKey, $result);
            $this->debugLog('Condition result from persistent cache', [
                'cache_key' => $cacheKey,
                'performance' => (microtime(true) - $startTime) * 1000 . 'ms'
            ]);
            return $result;
        }

        // Calculate conditions with error handling
        $result = $this->errorHandler->executeWithErrorHandling(
            function () use ($cartTotal, $cartItems, $destinationCountry, $storeId, $startTime) {
                return $this->performOptimizedConditionCheck(
                    $cartTotal, 
                    $cartItems, 
                    $destinationCountry, 
                    $storeId,
                    $startTime
                );
            },
            $this->getDefaultResult(),
            'Checking free shipping conditions'
        );

        // Cache the result
        $this->cacheResult($cacheKey, $result);
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $this->debugLog('Condition check completed', [
            'cache_key' => $cacheKey,
            'performance' => $totalTime . 'ms',
            'eligible' => $result['eligible']
        ]);

        return $result;
    }

    /**
     * Perform optimized condition check
     *
     * @param float|null $cartTotal
     * @param array|null $cartItems
     * @param string|null $destinationCountry
     * @param int|null $storeId
     * @param float $startTime
     * @return array
     */
    private function performOptimizedConditionCheck(
        ?float $cartTotal = null,
        ?array $cartItems = null,
        ?string $destinationCountry = null,
        ?int $storeId = null,
        float $startTime = 0
    ): array {
        // Initialize optimized result structure
        $result = $this->getDefaultResult();

        // Fast path: Check if free shipping is enabled
        if (!$this->isFreeShippingEnabledCached($storeId)) {
            $result['conditions_failed'][] = 'free_shipping_disabled';
            return $result;
        }
        $result['conditions_met'][] = 'free_shipping_enabled';

        // Get cart data efficiently
        $cartData = $this->getOptimizedCartData($cartTotal, $cartItems);
        $cartTotal = $cartData['total'];
        $cartItems = $cartData['items'];

        // Fast path: Check destination
        $destinationCountry = $destinationCountry ?: $this->getDestinationCountryFast();
        if (!$this->isDestinationEligible($destinationCountry)) {
            $result['conditions_failed'][] = 'destination_not_eligible';
            return $result;
        }
        $result['conditions_met'][] = 'destination_eligible';

        // Parallel condition checking for better performance
        $conditions = [
            'minimum_amount' => function() use ($cartTotal, $storeId, &$result) {
                return $this->checkMinimumAmountFast($cartTotal, $storeId, $result);
            },
            'sku_eligibility' => function() use ($cartItems, $storeId) {
                return $this->checkSkuEligibilityOptimized($cartItems, $storeId);
            },
            'customer_group' => function() use ($storeId) {
                return $this->checkCustomerGroupEligibilityFast($storeId);
            },
            'category' => function() use ($cartItems, $storeId) {
                return $this->checkCategoryEligibilityOptimized($cartItems, $storeId);
            },
            'time_restrictions' => function() use ($storeId) {
                return $this->checkTimeRestrictionsCached($storeId);
            }
        ];

        // Execute conditions efficiently
        foreach ($conditions as $conditionName => $conditionCheck) {
            $conditionResult = $conditionCheck();
            if ($conditionResult['eligible']) {
                $result['conditions_met'][] = $conditionName . '_eligible';
            } else {
                $result['conditions_failed'] = array_merge(
                    $result['conditions_failed'], 
                    $conditionResult['reasons']
                );
            }
        }

        // Determine overall eligibility
        $result['eligible'] = empty($result['conditions_failed']);

        // Add performance-optimized visual effects
        $result['visual_effects'] = $this->determineVisualEffectsOptimized($result);
        $result['notifications'] = $this->getProgressNotificationsOptimized($result);
        
        // Add performance metrics
        $result['performance'] = [
            'calculation_time' => (microtime(true) - $startTime) * 1000,
            'cache_hits' => $this->getCacheHitCount(),
            'memory_usage' => memory_get_usage(true)
        ];

        return $result;
    }

    /**
     * Generate optimized cache key
     *
     * @param mixed ...$params
     * @return string
     */
    private function generateOptimizedCacheKey(...$params): string
    {
        // Use hash for better performance and shorter keys
        $keyData = [
            'params' => $params,
            'config_hash' => $this->getConfigHashOptimized(),
            'timestamp' => floor($this->dateTime->gmtTimestamp() / 300) // 5-minute buckets
        ];
        
        return self::CACHE_TAG . '_' . hash('xxh3', $this->serializer->serialize($keyData));
    }

    /**
     * Add result to memory cache with LRU eviction
     *
     * @param string $key
     * @param array $result
     * @return void
     */
    private function addToMemoryCache(string $key, array $result): void
    {
        // Implement LRU eviction
        if (count($this->conditionCache) >= self::MEMORY_CACHE_LIMIT) {
            // Remove oldest entry
            $oldestKey = array_key_first($this->conditionCache);
            unset($this->conditionCache[$oldestKey]);
        }
        
        $this->conditionCache[$key] = $result;
    }

    /**
     * Cache result in persistent storage
     *
     * @param string $key
     * @param array $result
     * @return void
     */
    private function cacheResult(string $key, array $result): void
    {
        // Add to memory cache
        $this->addToMemoryCache($key, $result);
        
        // Add to persistent cache
        $this->cache->save(
            $this->serializer->serialize($result),
            $key,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        );
    }

    /**
     * Get default result structure
     *
     * @return array
     */
    private function getDefaultResult(): array
    {
        return [
            'eligible' => false,
            'conditions_met' => [],
            'conditions_failed' => [],
            'progress_percentage' => 0,
            'amount_needed' => 0,
            'visual_effects' => [],
            'notifications' => [],
            'debug_info' => []
        ];
    }

    /**
     * Get optimized cart data
     *
     * @param float|null $cartTotal
     * @param array|null $cartItems
     * @return array
     */
    private function getOptimizedCartData(?float $cartTotal, ?array $cartItems): array
    {
        if ($cartTotal !== null && $cartItems !== null) {
            return ['total' => $cartTotal, 'items' => $cartItems];
        }
        
        // Use cached session data if available
        $sessionKey = 'mab_cart_data_' . session_id();
        if (isset($this->configCache[$sessionKey])) {
            return $this->configCache[$sessionKey];
        }
        
        $cartData = $this->errorHandler->executeWithErrorHandling(
            function () {
                $quote = $this->checkoutSession->getQuote();
                if (!$quote || !$quote->getId()) {
                    return ['total' => 0, 'items' => []];
                }

                // Use optimized data collection
                return [
                    'total' => (float)$quote->getBaseSubtotalWithDiscount(),
                    'items' => $quote->getAllVisibleItems()
                ];
            },
            ['total' => 0, 'items' => []],
            'Getting optimized cart data'
        );
        
        // Cache for current session
        $this->configCache[$sessionKey] = $cartData;
        return $cartData;
    }

    /**
     * Get destination country fast
     *
     * @return string
     */
    private function getDestinationCountryFast(): string
    {
        $cacheKey = 'destination_country_' . session_id();
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $country = $this->errorHandler->executeWithErrorHandling(
            function () {
                $quote = $this->checkoutSession->getQuote();
                $shippingAddress = $quote->getShippingAddress();
                return $shippingAddress->getCountryId() ?: 'DZ';
            },
            'DZ',
            'Getting destination country fast'
        );
        
        $this->configCache[$cacheKey] = $country;
        return $country;
    }

    /**
     * Check if free shipping is enabled (cached)
     *
     * @param int|null $storeId
     * @return bool
     */
    private function isFreeShippingEnabledCached(?int $storeId = null): bool
    {
        $cacheKey = 'free_shipping_enabled_' . ($storeId ?: 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $enabled = $this->scopeConfig->isSetFlag(
            self::XML_PATH_FREE_SHIPPING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        $this->configCache[$cacheKey] = $enabled;
        return $enabled;
    }

    /**
     * Check minimum amount fast
     *
     * @param float $cartTotal
     * @param int|null $storeId
     * @param array &$result
     * @return array
     */
    private function checkMinimumAmountFast(float $cartTotal, ?int $storeId, array &$result): array
    {
        $minAmount = $this->getMinimumAmountCached($storeId);
        $result['minimum_amount'] = $minAmount;
        $result['cart_total'] = $cartTotal;

        if ($minAmount > 0) {
            $result['progress_percentage'] = min(100, ($cartTotal / $minAmount) * 100);
            $result['amount_needed'] = max(0, $minAmount - $cartTotal);

            if ($cartTotal >= $minAmount) {
                return ['eligible' => true, 'reasons' => []];
            } else {
                return ['eligible' => false, 'reasons' => ['minimum_amount_not_met']];
            }
        } else {
            $result['progress_percentage'] = 100;
            return ['eligible' => true, 'reasons' => []];
        }
    }

    /**
     * Get minimum amount (cached)
     *
     * @param int|null $storeId
     * @return float
     */
    private function getMinimumAmountCached(?int $storeId = null): float
    {
        $cacheKey = 'min_amount_' . ($storeId ?: 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $amount = (float)$this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_MIN_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        $this->configCache[$cacheKey] = $amount;
        return $amount;
    }

    /**
     * Check SKU eligibility (optimized)
     *
     * @param array $cartItems
     * @param int|null $storeId
     * @return array
     */
    private function checkSkuEligibilityOptimized(array $cartItems, ?int $storeId = null): array
    {
        $eligibleSkus = $this->getEligibleSkusCached($storeId);
        $excludedSkus = $this->getExcludedSkusCached($storeId);

        if (empty($eligibleSkus) && empty($excludedSkus)) {
            return ['eligible' => true, 'reasons' => []];
        }

        $cartSkus = $this->extractSkusOptimized($cartItems);
        $reasons = [];

        // Fast array intersection for better performance
        if (!empty($excludedSkus)) {
            $excludedInCart = array_intersect($cartSkus, $excludedSkus);
            if (!empty($excludedInCart)) {
                $reasons[] = 'excluded_sku_in_cart';
                return ['eligible' => false, 'reasons' => $reasons];
            }
        }

        if (!empty($eligibleSkus)) {
            $eligibleInCart = array_intersect($cartSkus, $eligibleSkus);
            if (empty($eligibleInCart)) {
                $reasons[] = 'no_eligible_sku_in_cart';
                return ['eligible' => false, 'reasons' => $reasons];
            }
        }

        return ['eligible' => true, 'reasons' => []];
    }

    /**
     * Extract SKUs optimized
     *
     * @param array $cartItems
     * @return array
     */
    private function extractSkusOptimized(array $cartItems): array
    {
        $cacheKey = 'cart_skus_' . md5(serialize(array_keys($cartItems)));
        
        if (isset($this->skuCache[$cacheKey])) {
            return $this->skuCache[$cacheKey];
        }
        
        $skus = [];
        foreach ($cartItems as $item) {
            if (is_object($item) && method_exists($item, 'getSku')) {
                $skus[] = $item->getSku();
            } elseif (is_array($item) && isset($item['sku'])) {
                $skus[] = $item['sku'];
            }
        }
        
        $uniqueSkus = array_unique($skus);
        $this->skuCache[$cacheKey] = $uniqueSkus;
        
        return $uniqueSkus;
    }

    /**
     * Get eligible SKUs (cached)
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleSkusCached(?int $storeId = null): array
    {
        $cacheKey = 'eligible_skus_' . ($storeId ?: 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $skus = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_ELIGIBLE_SKUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        $parsedSkus = $this->parseSkuListOptimized($skus);
        $this->configCache[$cacheKey] = $parsedSkus;
        
        return $parsedSkus;
    }

    /**
     * Get excluded SKUs (cached)
     *
     * @param int|null $storeId
     * @return array
     */
    private function getExcludedSkusCached(?int $storeId = null): array
    {
        $cacheKey = 'excluded_skus_' . ($storeId ?: 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $skus = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_EXCLUDED_SKUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        $parsedSkus = $this->parseSkuListOptimized($skus);
        $this->configCache[$cacheKey] = $parsedSkus;
        
        return $parsedSkus;
    }

    /**
     * Parse SKU list optimized
     *
     * @param string|null $skuString
     * @return array
     */
    private function parseSkuListOptimized(?string $skuString): array
    {
        if (empty($skuString)) {
            return [];
        }

        // Use more efficient parsing
        $normalized = str_replace(["\r", ";"], ["", ","], trim($skuString));
        $parts = preg_split('/[\n,]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        
        // Use array_map for better performance
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, function($v) { return $v !== ''; });
        
        return array_values(array_unique($parts));
    }

    /**
     * Check customer group eligibility fast
     *
     * @param int|null $storeId
     * @return array
     */
    private function checkCustomerGroupEligibilityFast(?int $storeId = null): array
    {
        $eligibleGroups = $this->getEligibleCustomerGroupsCached($storeId);
        
        if (empty($eligibleGroups)) {
            return ['eligible' => true, 'reasons' => []];
        }
        
        $currentGroupId = $this->customerSession->getCustomerGroupId();
        
        if (!in_array($currentGroupId, $eligibleGroups)) {
            return ['eligible' => false, 'reasons' => ['customer_group_not_eligible']];
        }
        
        return ['eligible' => true, 'reasons' => []];
    }

    /**
     * Check category eligibility optimized
     *
     * @param array $cartItems
     * @param int|null $storeId
     * @return array
     */
    private function checkCategoryEligibilityOptimized(array $cartItems, ?int $storeId = null): array
    {
        $eligibleCategories = $this->getEligibleCategoriesCached($storeId);
        
        if (empty($eligibleCategories)) {
            return ['eligible' => true, 'reasons' => []];
        }
        
        $cartCategories = $this->extractCategoriesOptimized($cartItems);
        
        // Fast array intersection for better performance
        $eligibleInCart = array_intersect($cartCategories, $eligibleCategories);
        
        if (empty($eligibleInCart)) {
            return ['eligible' => false, 'reasons' => ['no_eligible_category_in_cart']];
        }
        
        return ['eligible' => true, 'reasons' => []];
    }

    /**
     * Extract categories optimized
     *
     * @param array $cartItems
     * @return array
     */
    private function extractCategoriesOptimized(array $cartItems): array
    {
        $cacheKey = 'cart_categories_' . md5(serialize(array_keys($cartItems)));
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $categories = [];
        foreach ($cartItems as $item) {
            if (is_object($item) && method_exists($item, 'getProduct')) {
                $product = $item->getProduct();
                if ($product && method_exists($product, 'getCategoryIds')) {
                    $categories = array_merge($categories, $product->getCategoryIds());
                }
            } elseif (is_array($item) && isset($item['category_ids'])) {
                $categories = array_merge($categories, $item['category_ids']);
            }
        }
        
        $uniqueCategories = array_unique($categories);
        $this->configCache[$cacheKey] = $uniqueCategories;
        
        return $uniqueCategories;
    }

    /**
     * Get eligible categories (cached)
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleCategoriesCached(?int $storeId = null): array
    {
        $cacheKey = 'eligible_categories_' . ($storeId ?: 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $categories = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_CATEGORIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        $parsedCategories = [];
        if (!empty($categories)) {
            $parsedCategories = explode(',', $categories);
            $parsedCategories = array_map('trim', $parsedCategories);
            $parsedCategories = array_filter($parsedCategories, function($v) { return $v !== ''; });
            $parsedCategories = array_values(array_unique($parsedCategories));
        }
        
        $this->configCache[$cacheKey] = $parsedCategories;
        
        return $parsedCategories;
    }

    /**
     * Check time restrictions (cached)
     *
     * @param int|null $storeId
     * @return array
     */
    private function checkTimeRestrictionsCached(?int $storeId = null): array
    {
        $cacheKey = 'time_restrictions_' . ($storeId ?: 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $result = $this->checkTimeRestrictions($storeId);
        $this->configCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Determine visual effects optimized
     *
     * @param array $result
     * @return array
     */
    private function determineVisualEffectsOptimized(array $result): array
    {
        $effects = [];
        
        // Check if visual effects are enabled
        if (!$this->scopeConfig->isSetFlag(
            'carriers/yalidine/free_shipping_visual_effects',
            ScopeInterface::SCOPE_STORE
        )) {
            return $effects;
        }
        
        // Add visual effects based on eligibility status
        if ($result['eligible']) {
            $effects[] = [
                'type' => 'success',
                'message' => 'Free shipping available!',
                'animation' => 'pulse'
            ];
        } elseif (!empty($result['conditions_met'])) {
            $effects[] = [
                'type' => 'progress',
                'message' => 'Almost eligible for free shipping',
                'animation' => 'fade'
            ];
        }
        
        return $effects;
    }

    /**
     * Get progress notifications optimized
     *
     * @param array $result
     * @return array
     */
    private function getProgressNotificationsOptimized(array $result): array
    {
        $notifications = [];
        
        if ($result['eligible']) {
            $notifications[] = [
                'type' => 'success',
                'message' => __('Congratulations! Your order qualifies for free shipping.'),
                'priority' => 'high'
            ];
        } else {
            // Add specific notifications based on failed conditions
            foreach ($result['conditions_failed'] as $reason) {
                switch ($reason) {
                    case 'minimum_amount_not_met':
                        if (isset($result['amount_needed']) && $result['amount_needed'] > 0) {
                            $notifications[] = [
                                'type' => 'info',
                                'message' => __('Add %1 more to qualify for free shipping.', $result['amount_needed']),
                                'priority' => 'medium'
                            ];
                        }
                        break;
                    case 'no_eligible_sku_in_cart':
                        $notifications[] = [
                            'type' => 'warning',
                            'message' => __('Cart contains no eligible items for free shipping.'),
                            'priority' => 'low'
                        ];
                        break;
                    case 'excluded_sku_in_cart':
                        $notifications[] = [
                            'type' => 'warning',
                            'message' => __('Cart contains items that are excluded from free shipping.'),
                            'priority' => 'low'
                        ];
                        break;
                }
            }
        }
        
        return $notifications;
    }

    /**
     * Get configuration hash optimized
     *
     * @return string
     */
    private function getConfigHashOptimized(): string
    {
        $cacheKey = 'config_hash';
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $configData = [
            'enabled' => $this->isFreeShippingEnabledCached(),
            'min_amount' => $this->getMinimumAmountCached(),
            'eligible_skus' => $this->getEligibleSkusCached(),
            'excluded_skus' => $this->getExcludedSkusCached()
        ];
        
        $hash = hash('xxh3', $this->serializer->serialize($configData));
        $this->configCache[$cacheKey] = $hash;
        
        return $hash;
    }

    /**
     * Get cache hit count for performance monitoring
     *
     * @return int
     */
    private function getCacheHitCount(): int
    {
        return count($this->conditionCache) + count($this->configCache);
    }

    /**
     * Clear all caches
     *
     * @return void
     */
    public function clearAllCaches(): void
    {
        $this->conditionCache = [];
        $this->configCache = [];
        $this->skuCache = [];
        $this->cache->clean([self::CACHE_TAG]);
    }

    /**
     * Determine visual effects based on conditions
     *
     * @param array $conditionResult
     * @return array
     */
    private function determineVisualEffects(array $conditionResult): array
    {
        if (!$this->visualEffectsHelper->isEnabled()) {
            return [];
        }

        $effects = [];

        // Free shipping achieved effect
        if ($conditionResult['eligible']) {
            $celebrationEffect = $this->visualEffectsHelper->getFreeShippingCelebrationEffect();
            if ($celebrationEffect !== 'none') {
                $effects[] = [
                    'type' => 'celebration',
                    'effect' => $celebrationEffect,
                    'trigger' => 'free_shipping_achieved',
                    'intensity' => $this->visualEffectsHelper->getEffectIntensity(),
                    'duration' => $this->visualEffectsHelper->getAnimationDuration()
                ];
            }
        }

        // Progress bar effect
        if ($this->visualEffectsHelper->isProgressBarEnabled()) {
            $effects[] = [
                'type' => 'progress_bar',
                'style' => $this->visualEffectsHelper->getProgressBarStyle(),
                'percentage' => $conditionResult['progress_percentage'],
                'trigger' => 'cart_update'
            ];
        }

        // Threshold notification effects
        if ($this->visualEffectsHelper->isThresholdNotificationEnabled()) {
            $thresholds = $this->visualEffectsHelper->getNotificationThresholds();
            $currentPercentage = $conditionResult['progress_percentage'];
            
            foreach ($thresholds as $threshold) {
                if ($currentPercentage >= $threshold && $currentPercentage < 100) {
                    $effects[] = [
                        'type' => 'threshold_notification',
                        'threshold' => $threshold,
                        'percentage' => $currentPercentage,
                        'trigger' => 'threshold_reached'
                    ];
                    break; // Only show the highest reached threshold
                }
            }
        }

        return $effects;
    }

    /**
     * Get progress notifications
     *
     * @param array $conditionResult
     * @return array
     */
    private function getProgressNotifications(array $conditionResult): array
    {
        $notifications = [];

        if ($conditionResult['eligible']) {
            $notifications[] = [
                'type' => 'success',
                'message' => __('Congratulations! You qualify for free shipping!'),
                'icon' => 'success'
            ];
        } elseif ($conditionResult['amount_needed'] > 0) {
            $notifications[] = [
                'type' => 'info',
                'message' => __('Add %1 more to qualify for free shipping!', 
                    $this->formatCurrency($conditionResult['amount_needed'])),
                'icon' => 'info'
            ];
        }

        return $notifications;
    }

    /**
     * Get cart data from session
     *
     * @return array
     */
    private function getCartData(): array
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () {
                $quote = $this->checkoutSession->getQuote();
                if (!$quote || !$quote->getId()) {
                    return ['total' => 0, 'items' => []];
                }

                return [
                    'total' => (float)$quote->getBaseSubtotalWithDiscount(),
                    'items' => $quote->getAllVisibleItems()
                ];
            },
            ['total' => 0, 'items' => []],
            'Getting cart data from session'
        );
    }

    /**
     * Get destination country
     *
     * @return string
     */
    private function getDestinationCountry(): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () {
                $quote = $this->checkoutSession->getQuote();
                $shippingAddress = $quote->getShippingAddress();
                return $shippingAddress->getCountryId() ?: 'DZ';
            },
            'DZ',
            'Getting destination country'
        );
    }

    /**
     * Check if destination is eligible
     *
     * @param string $countryId
     * @return bool
     */
    private function isDestinationEligible(string $countryId): bool
    {
        // Currently only Algeria is supported
        return $countryId === 'DZ';
    }

    /**
     * Check if free shipping is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    private function isFreeShippingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FREE_SHIPPING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get minimum amount for free shipping
     *
     * @param int|null $storeId
     * @return float
     */
    private function getMinimumAmount(?int $storeId = null): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_MIN_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get eligible SKUs
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleSkus(?int $storeId = null): array
    {
        $skus = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_ELIGIBLE_SKUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $this->parseSkuList($skus);
    }

    /**
     * Get excluded SKUs
     *
     * @param int|null $storeId
     * @return array
     */
    private function getExcludedSkus(?int $storeId = null): array
    {
        $skus = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_EXCLUDED_SKUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $this->parseSkuList($skus);
    }

    /**
     * Parse SKU list from configuration
     *
     * @param string|null $skuString
     * @return array
     */
    private function parseSkuList(?string $skuString): array
    {
        if (empty($skuString)) {
            return [];
        }

        $normalized = str_replace(["\r", ";"], ["", ","], $skuString);
        $parts = preg_split('/[\n,]+/', $normalized);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, function($v) { return $v !== ''; });
        
        return array_values(array_unique($parts));
    }

    /**
     * Format currency amount
     *
     * @param float $amount
     * @return string
     */
    private function formatCurrency(float $amount): string
    {
        // This is a simplified version - in a real implementation,
        // you would use Magento's currency formatting
        return number_format($amount, 2) . ' DZD';
    }

    /**
     * Debug logging
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function debugLog(string $message, array $context = []): void
    {
        if ($this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_ENABLED, ScopeInterface::SCOPE_STORE)) {
            $this->logger->debug('[MAB Shipping Conditions] ' . $message, $context);
        }
    }

    /**
     * Check customer group eligibility
     *
     * @param int|null $storeId
     * @return array
     */
    private function checkCustomerGroupEligibility(?int $storeId = null): array
    {
        $eligibleGroups = $this->getEligibleCustomerGroups($storeId);
        
        if (empty($eligibleGroups)) {
            return ['eligible' => true, 'reasons' => []];
        }
        
        $currentGroupId = $this->customerSession->getCustomerGroupId();
        
        if (!in_array($currentGroupId, $eligibleGroups)) {
            return ['eligible' => false, 'reasons' => ['customer_group_not_eligible']];
        }
        
        return ['eligible' => true, 'reasons' => []];
    }

    /**
     * Check category eligibility
     *
     * @param array $cartItems
     * @param int|null $storeId
     * @return array
     */
    private function checkCategoryEligibility(array $cartItems, ?int $storeId = null): array
    {
        $eligibleCategories = $this->getEligibleCategories($storeId);
        
        if (empty($eligibleCategories)) {
            return ['eligible' => true, 'reasons' => []];
        }
        
        $cartCategories = $this->extractCategoriesFromItems($cartItems);
        $eligibleInCart = array_intersect($cartCategories, $eligibleCategories);
        
        if (empty($eligibleInCart)) {
            return ['eligible' => false, 'reasons' => ['no_eligible_category_in_cart']];
        }
        
        return ['eligible' => true, 'reasons' => []];
    }

    /**
     * Check time restrictions
     *
     * @param int|null $storeId
     * @return array
     */
    private function checkTimeRestrictions(?int $storeId = null): array
    {
        if (!$this->isTimeRestrictionsEnabled($storeId)) {
            return ['eligible' => true, 'reasons' => []];
        }
        
        $reasons = [];
        
        // Check day of week
        $eligibleDays = $this->getEligibleDaysOfWeek($storeId);
        if (!empty($eligibleDays)) {
            $now = new \DateTime();
            $currentDay = $now->format('w'); // 0 = Sunday, 1 = Monday, etc.
            if (!in_array($currentDay, $eligibleDays)) {
                $reasons[] = 'day_not_eligible';
            }
        }
        
        return [
            'eligible' => empty($reasons),
            'reasons' => $reasons
        ];
    }

    /**
     * Extract categories from cart items
     *
     * @param array $cartItems
     * @return array
     */
    private function extractCategoriesFromItems(array $cartItems): array
    {
        $categories = [];
        foreach ($cartItems as $item) {
            if (is_object($item) && method_exists($item, 'getProduct')) {
                $product = $item->getProduct();
                if ($product && method_exists($product, 'getCategoryIds')) {
                    $categories = array_merge($categories, $product->getCategoryIds());
                }
            }
        }
        return array_unique($categories);
    }

    /**
     * Get eligible customer groups
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleCustomerGroups(?int $storeId = null): array
    {
        $groups = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_CUSTOMER_GROUPS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($groups)) {
            return [];
        }
        
        return explode(',', $groups);
    }

    /**
     * Get eligible customer groups (cached)
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleCustomerGroupsCached(?int $storeId = null): array
    {
        $cacheKey = 'eligible_customer_groups_' . ($storeId ?? 'default');
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        $groups = $this->getEligibleCustomerGroups($storeId);
        $this->configCache[$cacheKey] = $groups;
        
        return $groups;
    }

    /**
     * Get eligible categories
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleCategories(?int $storeId = null): array
    {
        $categories = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_CATEGORIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($categories)) {
            return [];
        }
        
        return explode(',', $categories);
    }

    /**
     * Check if time restrictions are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    private function isTimeRestrictionsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FREE_SHIPPING_TIME_RESTRICTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get eligible days of week
     *
     * @param int|null $storeId
     * @return array
     */
    private function getEligibleDaysOfWeek(?int $storeId = null): array
    {
        $days = $this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_DAYS_OF_WEEK,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($days)) {
            return [];
        }
        
        return explode(',', $days);
    }

    /**
     * Clear condition cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->clearAllCaches();
    }
}