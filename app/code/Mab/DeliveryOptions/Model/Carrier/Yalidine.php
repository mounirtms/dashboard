<?php
/**
 * MAB Delivery Options - Yalidine Carrier Model
 * 
 * @category    Mab
 * @package     Mab_DeliveryOptions
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\DeliveryOptions\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Helper\Data;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\App\CacheInterface;
use Mab\DeliveryOptions\Helper\ShippingConditions;

/**
 * Class Yalidine
 * 
 * Professional Yalidine shipping carrier for Algeria with optimized performance
 */
class Yalidine extends AbstractCarrier implements CarrierInterface
{
    /**
     * Carrier code
     */
    const CODE = 'yalidine';

    /**
     * Cache tags
     */
    const CACHE_TAG = 'mab_yalidine_rates';
    const CACHE_LIFETIME = 1800; // 30 minutes

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var CountryFactory
     */
    protected $countryFactory;

    /**
     * @var CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * @var Data
     */
    protected $directoryData;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var FormatInterface
     */
    protected $localeFormat;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ShippingConditions
     */
    protected $shippingConditions;

    /**
     * @var array
     */
    protected $rateCache = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param CountryFactory $countryFactory
     * @param CurrencyFactory $currencyFactory
     * @param Data $directoryData
     * @param StockRegistryInterface $stockRegistry
     * @param FormatInterface $localeFormat
     * @param CacheInterface $cache
     * @param ShippingConditions $shippingConditions
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        Data $directoryData,
        StockRegistryInterface $stockRegistry,
        FormatInterface $localeFormat,
        CacheInterface $cache,
        ShippingConditions $shippingConditions,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->countryFactory = $countryFactory;
        $this->currencyFactory = $currencyFactory;
        $this->directoryData = $directoryData;
        $this->stockRegistry = $stockRegistry;
        $this->localeFormat = $localeFormat;
        $this->cache = $cache;
        $this->shippingConditions = $shippingConditions;
        
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect shipping rates with optimized performance
     *
     * @param RateRequest $request
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        // Early return if not active
        if (!$this->isActive()) {
            return false;
        }

        // Generate cache key for this rate request
        $cacheKey = $this->generateCacheKey($request);
        
        // Check memory cache first
        if (isset($this->rateCache[$cacheKey])) {
            $this->debugLog('Rate retrieved from memory cache', ['cache_key' => $cacheKey]);
            return $this->rateCache[$cacheKey];
        }

        // Check persistent cache
        $cachedResult = $this->cache->load($cacheKey);
        if ($cachedResult) {
            $rateData = unserialize($cachedResult);
            if ($rateData && is_array($rateData)) {
                // Reconstruct result from cached data
                $result = $this->rateResultFactory->create();
                foreach ($rateData as $data) {
                    $method = $this->rateMethodFactory->create();
                    $method->setCarrier($data['carrier']);
                    $method->setCarrierTitle($data['carrier_title']);
                    $method->setMethod($data['method']);
                    $method->setMethodTitle($data['method_title']);
                    $method->setPrice($data['price']);
                    $method->setCost($data['cost']);
                    $result->append($method);
                }
                $this->rateCache[$cacheKey] = $result;
                $this->debugLog('Rate reconstructed from persistent cache', ['cache_key' => $cacheKey]);
                return $result;
            }
        }

        // Calculate rates
        $startTime = microtime(true);
        $result = $this->calculateRates($request);
        $calculationTime = microtime(true) - $startTime;

        // Cache the result if valid
        if ($result && $result->getAllRates()) {
            $this->rateCache[$cacheKey] = $result;
            
            // Cache only serializable rate data instead of full result object
            $rateData = [];
            foreach ($result->getAllRates() as $rate) {
                $rateData[] = [
                    'carrier' => $rate->getCarrier(),
                    'carrier_title' => $rate->getCarrierTitle(),
                    'method' => $rate->getMethod(),
                    'method_title' => $rate->getMethodTitle(),
                    'price' => $rate->getPrice(),
                    'cost' => $rate->getCost()
                ];
            }
            
            $this->cache->save(
                serialize($rateData),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME
            );
            
            $this->debugLog('Rate calculated and cached', [
                'cache_key' => $cacheKey,
                'calculation_time' => round($calculationTime * 1000, 2) . 'ms'
            ]);
        }

        return $result;
    }

    /**
     * Calculate shipping rates
     *
     * @param RateRequest $request
     * @return Result|bool
     */
    protected function calculateRates(RateRequest $request)
    {
        // Debug logging
        $this->debugLog('Yalidine rate calculation started', [
            'dest_country' => $request->getDestCountryId(),
            'dest_region' => $request->getDestRegionId(),
            'dest_postcode' => $request->getDestPostcode(),
            'package_value' => $request->getPackageValue(),
            'package_weight' => $request->getPackageWeight(),
            'package_qty' => $request->getPackageQty()
        ]);

        // Check if Mageplaza integration is enabled and should override
        if ($this->shouldHideYalidineCarrier()) {
            $this->debugLog('Yalidine carrier hidden - Mageplaza integration active');
            return false;
        }

        $result = $this->rateResultFactory->create();

        // Check if destination is supported (Algeria focus)
        if (!$this->isDestinationSupported($request)) {
            $this->debugLog('Destination not supported', [
                'country' => $request->getDestCountryId()
            ]);
            return false;
        }

        // Create shipping method with optimized free shipping check
        $method = $this->createOptimizedShippingMethod($request);
        
        if ($method) {
            $result->append($method);
            
            $this->debugLog('Rate created successfully', [
                'method_title' => $method->getMethodTitle(),
                'price' => $method->getPrice(),
                'cost' => $method->getCost()
            ]);
        }

        return $result;
    }

    /**
     * Generate cache key for rate request
     *
     * @param RateRequest $request
     * @return string
     */
    protected function generateCacheKey(RateRequest $request)
    {
        $keyData = [
            'carrier' => $this->_code,
            'country' => $request->getDestCountryId(),
            'region' => $request->getDestRegionId(),
            'postcode' => $request->getDestPostcode(),
            'city' => $request->getDestCity(),
            'weight' => round($request->getPackageWeight(), 2),
            'value' => round($request->getPackageValue(), 2),
            'qty' => $request->getPackageQty(),
            'config_hash' => $this->getConfigHash()
        ];
        
        // Include cart items hash for SKU-based rules
        if ($request->getAllItems()) {
            $keyData['items_hash'] = $this->getItemsHash($request->getAllItems());
        }
        
        return self::CACHE_TAG . '_' . md5(serialize($keyData));
    }

    /**
     * Get configuration hash
     *
     * @return string
     */
    protected function getConfigHash()
    {
        $configData = [
            'enabled' => $this->getConfigData('active'),
            'price' => $this->getConfigData('price'),
            'free_shipping' => $this->getConfigData('free_shipping_enabled'),
            'min_amount' => $this->getConfigData('free_shipping_minimum'),
            'eligible_skus' => $this->getConfigData('free_shipping_eligible_skus'),
            'excluded_skus' => $this->getConfigData('free_shipping_excluded_skus')
        ];
        
        return md5(serialize($configData));
    }

    /**
     * Get items hash for cache key
     *
     * @param array $items
     * @return string
     */
    protected function getItemsHash($items)
    {
        $skus = [];
        foreach ($items as $item) {
            if (method_exists($item, 'getSku')) {
                $skus[] = $item->getSku();
            }
        }
        sort($skus);
        return md5(implode(',', $skus));
    }

    /**
     * Check if destination is supported
     *
     * @param RateRequest $request
     * @return bool
     */
    protected function isDestinationSupported(RateRequest $request)
    {
        $destCountry = $request->getDestCountryId();
        
        // Check if specific countries are allowed
        if ($this->getConfigData('sallowspecific')) {
            $allowedCountries = explode(',', $this->getConfigData('specificcountry'));
            if (!in_array($destCountry, $allowedCountries)) {
                return false;
            }
        }

        // Primary focus on Algeria
        if ($destCountry === 'DZ') {
            return true;
        }

        // Allow other countries if not restricted
        return !$this->getConfigData('sallowspecific');
    }

    /**
     * Check if Yalidine carrier should be hidden due to Mageplaza integration
     *
     * @return bool
     */
    protected function shouldHideYalidineCarrier()
    {
        // Check if Mageplaza integration is enabled
        $mageplazaEnabled = $this->_scopeConfig->isSetFlag(
            'mab_delivery_options/mageplaza_integration/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$mageplazaEnabled) {
            return false;
        }

        // Check if hiding is specifically enabled
        $hideCarrier = $this->_scopeConfig->isSetFlag(
            'mab_delivery_options/mageplaza_integration/hide_yalidine_carrier',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($hideCarrier) {
            $this->debugLog('Hiding Yalidine carrier - Mageplaza integration with hiding enabled');
            return true;
        }

        return false;
    }

    /**
     * Create optimized shipping method
     *
     * @param RateRequest $request
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method|false
     */
    protected function createOptimizedShippingMethod(RateRequest $request)
    {
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        // Use optimized shipping conditions helper for free shipping check
        $cartItems = $request->getAllItems() ?: [];
        $cartTotal = $request->getPackageValue();
        $destinationCountry = $request->getDestCountryId();
        
        $freeShippingResult = $this->shippingConditions->checkFreeShippingConditions(
            $cartTotal,
            $cartItems,
            $destinationCountry
        );

        // Calculate cost and price based on free shipping eligibility
        $cost = $this->calculateOptimizedShippingCost($request);
        $price = $freeShippingResult['eligible'] ? 0.00 : $cost;

        $method->setPrice($price);
        $method->setCost($cost);

        // Add additional data for frontend
        $method->setData('free_shipping_info', $freeShippingResult);
        
        // Add carrier-specific data
        $method->setData('yalidine_tracking_enabled', $this->isTrackingAvailable());
        $method->setData('yalidine_delivery_time', $this->getEstimatedDeliveryTime($request));

        return $method;
    }

    /**
     * Calculate optimized shipping cost
     *
     * @param RateRequest $request
     * @return float
     */
    protected function calculateOptimizedShippingCost(RateRequest $request)
    {
        $baseCost = (float)$this->getConfigData('price');
        
        // Apply weight-based calculation with caching
        $weight = $request->getPackageWeight();
        if ($weight > 1) {
            // Progressive pricing for additional weight
            $extraWeight = $weight - 1;
            $weightCostPerKg = $this->getWeightBasedCost($weight);
            $baseCost += $extraWeight * $weightCostPerKg;
        }

        // Apply dimension-based surcharge if applicable
        $dimensionSurcharge = $this->calculateDimensionSurcharge($request);
        $baseCost += $dimensionSurcharge;

        // Apply value-based insurance if needed
        $insuranceCost = $this->calculateInsuranceCost($request->getPackageValue());
        $baseCost += $insuranceCost;

        return round($baseCost, 2);
    }

    /**
     * Get weight-based cost per kg
     *
     * @param float $totalWeight
     * @return float
     */
    protected function getWeightBasedCost($totalWeight)
    {
        // Progressive pricing: more weight = lower cost per kg
        if ($totalWeight <= 5) {
            return 50; // 50 DZD per kg for up to 5kg
        } elseif ($totalWeight <= 10) {
            return 40; // 40 DZD per kg for 5-10kg
        } else {
            return 30; // 30 DZD per kg for 10kg+
        }
    }

    /**
     * Calculate dimension-based surcharge
     *
     * @param RateRequest $request
     * @return float
     */
    protected function calculateDimensionSurcharge(RateRequest $request)
    {
        // Get package dimensions if available
        $length = $request->getPackageLength() ?: 0;
        $width = $request->getPackageWidth() ?: 0;
        $height = $request->getPackageHeight() ?: 0;
        
        if ($length === 0 || $width === 0 || $height === 0) {
            return 0; // No dimension data available
        }
        
        $volume = $length * $width * $height; // in cm³
        $volumetricWeight = $volume / 5000; // Standard volumetric factor
        
        // Apply surcharge if volumetric weight exceeds actual weight significantly
        $actualWeight = $request->getPackageWeight();
        if ($volumetricWeight > $actualWeight * 1.5) {
            return 100; // 100 DZD surcharge for oversized packages
        }
        
        return 0;
    }

    /**
     * Calculate insurance cost based on package value
     *
     * @param float $packageValue
     * @return float
     */
    protected function calculateInsuranceCost($packageValue)
    {
        // No insurance for low-value packages
        if ($packageValue < 5000) {
            return 0;
        }
        
        // Insurance: 0.5% of package value, minimum 50 DZD, maximum 500 DZD
        $insuranceCost = $packageValue * 0.005;
        return max(50, min(500, $insuranceCost));
    }

    /**
     * Get estimated delivery time
     *
     * @param RateRequest $request
     * @return string
     */
    protected function getEstimatedDeliveryTime(RateRequest $request)
    {
        $city = $request->getDestCity();
        
        // Major cities get faster delivery
        $majorCities = ['Alger', 'Oran', 'Constantine', 'Annaba', 'Blida'];
        
        if (in_array($city, $majorCities)) {
            return '24-48 hours';
        } else {
            return '2-4 business days';
        }
    }

    /**
     * Optimized debug logging
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function debugLog($message, array $context = [])
    {
        if ($this->getConfigData('debug_enabled')) {
            $context['carrier'] = $this->_code;
            $context['timestamp'] = date('Y-m-d H:i:s');
            $this->_logger->info('[MAB Delivery Optimized] ' . $message, $context);
        }
    }

    /**
     * Check if free shipping is eligible
     *
     * @param RateRequest $request
     * @return bool
     */
    protected function isFreeShippingEligible(RateRequest $request)
    {
        if (!$this->getConfigData('free_shipping_enabled')) {
            return false;
        }

        // Check minimum order amount
        $minAmount = (float)$this->getConfigData('free_shipping_minimum');
        if ($minAmount > 0 && $request->getPackageValue() < $minAmount) {
            return false;
        }

        // Check destination (free shipping only for Algeria)
        if ($request->getDestCountryId() !== 'DZ') {
            return false;
        }

        // Check time restrictions
        if ($this->getConfigData('free_shipping_time_restrictions')) {
            if (!$this->isWithinTimeRestrictions()) {
                return false;
            }
        }

        // Check SKU restrictions
        if (!$this->checkSkuEligibility($request)) {
            return false;
        }

        return true;
    }

    /**
     * Check if current time is within free shipping restrictions
     *
     * @return bool
     */
    protected function isWithinTimeRestrictions()
    {
        $now = new \DateTime();
        
        // Check date range
        $startDate = $this->getConfigData('free_shipping_start_date');
        $endDate = $this->getConfigData('free_shipping_end_date');
        
        if ($startDate && $now < new \DateTime($startDate)) {
            return false;
        }
        
        if ($endDate && $now > new \DateTime($endDate)) {
            return false;
        }

        // Check days of week
        $allowedDays = $this->getConfigData('free_shipping_days_of_week');
        if ($allowedDays) {
            $allowedDaysArray = explode(',', $allowedDays);
            $currentDay = $now->format('w'); // 0 = Sunday, 1 = Monday, etc.
            
            if (!in_array($currentDay, $allowedDaysArray)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check SKU eligibility for free shipping
     *
     * @param RateRequest $request
     * @return bool
     */
    protected function checkSkuEligibility(RateRequest $request)
    {
        $items = $request->getAllItems();
        if (!$items) {
            return true;
        }

        // Check excluded SKUs
        $excludedSkus = $this->getConfigData('free_shipping_excluded_skus');
        if ($excludedSkus) {
            $excludedArray = array_map('trim', explode(',', $excludedSkus));
            foreach ($items as $item) {
                if (in_array($item->getSku(), $excludedArray)) {
                    return false;
                }
            }
        }

        // Check eligible SKUs
        $eligibleSkus = $this->getConfigData('free_shipping_eligible_skus');
        if ($eligibleSkus) {
            $eligibleArray = array_map('trim', explode(',', $eligibleSkus));
            $hasEligibleSku = false;
            
            foreach ($items as $item) {
                if (in_array($item->getSku(), $eligibleArray)) {
                    $hasEligibleSku = true;
                    break;
                }
            }
            
            if (!$hasEligibleSku) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get tracking information
     *
     * @param string $tracking
     * @return string|false
     */
    public function getTrackingInfo($tracking)
    {
        $result = $this->_trackFactory->create();
        
        $result->setCarrier($this->_code);
        $result->setCarrierTitle($this->getConfigData('title'));
        $result->setTracking($tracking);
        $result->setPopup(1);
        $result->setUrl("https://www.yalidine.com/tracking?code=" . $tracking);
        
        return $result;
    }

    /**
     * Check if city option required
     *
     * @return bool
     */
    public function isCityRequired()
    {
        return true;
    }

    /**
     * Determine whether zip-code is required for the country of destination
     *
     * @param string|null $countryId
     * @return bool
     */
    public function isZipCodeRequired($countryId = null)
    {
        if ($countryId === 'DZ') {
            return false; // Algeria doesn't use zip codes consistently
        }
        
        return parent::isZipCodeRequired($countryId);
    }
}