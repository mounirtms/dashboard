<?php
namespace Mab\Core\Model\License;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Json\Helper\Data as JsonHelper;

class Validator
{
    const XML_PATH_FIREBASE_CONFIG = 'mab_core/firebase/config';
    const XML_PATH_LICENSE_KEY = 'mab_core/license/key';
    const CACHE_KEY_PREFIX = 'mab_license_validation_';
    const CACHE_LIFETIME = 86400; // 24 hours

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var JsonHelper
     */
    private $jsonHelper;

    /**
     * @var \Magento\Framework\App\Cache\Type\Config
     */
    private $cache;

    /**
     * @var array
     */
    private $validationResults = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Curl $curl
     * @param JsonHelper $jsonHelper
     * @param \Magento\Framework\App\Cache\Type\Config $cache
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        JsonHelper $jsonHelper,
        \Magento\Framework\App\Cache\Type\Config $cache
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->jsonHelper = $jsonHelper;
        $this->cache = $cache;
    }

    /**
     * Validate license for a specific module
     *
     * @param string $moduleCode
     * @return bool
     */
    public function validateLicense($moduleCode)
    {
        try {
            // Validate input
            if (empty($moduleCode) || !is_string($moduleCode)) {
                throw new \InvalidArgumentException('Module code must be a non-empty string');
            }

            // Check runtime cache
            if (isset($this->validationResults[$moduleCode])) {
                return $this->validationResults[$moduleCode];
            }

            // Check persistent cache
            $cacheKey = self::CACHE_KEY_PREFIX . $moduleCode;
            $cachedResult = $this->cache->load($cacheKey);
            if ($cachedResult !== false) {
                $this->validationResults[$moduleCode] = (bool)$cachedResult;
                return $this->validationResults[$moduleCode];
            }

            $licenseKey = $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE);
            if (empty($licenseKey)) {
                $this->cacheResult($moduleCode, false);
                return false;
            }

            // Get Firebase config from core module settings
            $firebaseConfig = $this->scopeConfig->getValue(self::XML_PATH_FIREBASE_CONFIG, ScopeInterface::SCOPE_STORE);
            if (empty($firebaseConfig)) {
                $this->cacheResult($moduleCode, false);
                return false;
            }

            // Decode Firebase config with error handling
            try {
                $firebaseConfig = $this->jsonHelper->jsonDecode($firebaseConfig);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid Firebase configuration JSON: ' . $e->getMessage());
            }

            if (!is_array($firebaseConfig) || !isset($firebaseConfig['databaseURL']) || empty($firebaseConfig['databaseURL'])) {
                throw new \InvalidArgumentException('Firebase configuration must contain a valid databaseURL');
            }
            
            // Build the Firebase Realtime Database URL for license validation
            $url = rtrim($firebaseConfig['databaseURL'], '/') . '/licenses/' . urlencode($licenseKey) . '/' . urlencode($moduleCode) . '.json';
            
            // Set curl options with better error handling
            $this->curl->setOptions([
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Mab-License-Validator/1.0'
            ]);
            
            // Make the request to Firebase
            $this->curl->get($url);
            $httpCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();
            
            // Check HTTP status
            if ($httpCode !== 200) {
                throw new \RuntimeException("HTTP request failed with status code: {$httpCode}");
            }
            
            if (empty($responseBody)) {
                $this->cacheResult($moduleCode, false);
                return false;
            }
            
            // Decode response with error handling
            try {
                $response = $this->jsonHelper->jsonDecode($responseBody);
            } catch (\Exception $e) {
                throw new \RuntimeException('Invalid JSON response from Firebase: ' . $e->getMessage());
            }
            
            // Validate response structure and content
            $isValid = $this->validateLicenseResponse($response);
            
            $this->cacheResult($moduleCode, $isValid);
            return $isValid;

        } catch (\InvalidArgumentException $e) {
            // Log validation errors but don't cache them
            error_log("Mab License Validation Error: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            // Log unexpected errors and cache negative result for shorter time
            error_log("Mab License Validation Exception: " . $e->getMessage());
            $this->cacheResult($moduleCode, false, 3600); // Cache for 1 hour on error
            return false;
        }
    }

    /**
     * Validate license response structure and content
     *
     * @param mixed $response
     * @return bool
     */
    private function validateLicenseResponse($response)
    {
        if (!is_array($response) || empty($response)) {
            return false;
        }

        // Check if license is marked as valid
        if (!isset($response['valid']) || $response['valid'] !== true) {
            return false;
        }

        // Check expiration if present
        if (isset($response['expires'])) {
            $expirationTime = is_numeric($response['expires']) 
                ? (int)$response['expires'] 
                : strtotime($response['expires']);
            
            if ($expirationTime === false || $expirationTime <= time()) {
                return false;
            }
        }

        // Additional validation checks can be added here
        return true;
    }

    /**
     * Cache validation result
     *
     * @param string $moduleCode
     * @param bool $result
     * @param int|null $lifetime
     * @return void
     */
    private function cacheResult($moduleCode, $result, $lifetime = null)
    {
        $this->validationResults[$moduleCode] = (bool)$result;
        $this->cache->save(
            (string)(int)$result,
            self::CACHE_KEY_PREFIX . $moduleCode,
            [],
            $lifetime ?: self::CACHE_LIFETIME
        );
    }
}
