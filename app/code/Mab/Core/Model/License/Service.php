<?php
namespace Mab\Core\Model\License;

use Mab\Core\Helper\Firebase;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Service
{
    const CACHE_KEY_PREFIX = 'mab_license_';
    const CACHE_LIFETIME = 1800; // 30 minutes

    /**
     * @var Firebase
     */
    private $firebaseHelper;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Firebase $firebaseHelper
     * @param CacheInterface $cache
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        Firebase $firebaseHelper,
        CacheInterface $cache,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->firebaseHelper = $firebaseHelper;
        $this->cache = $cache;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Check module license status
     *
     * @param string $moduleName
     * @return bool
     */
    public function isLicenseValid($moduleName)
    {
        try {
            $cacheKey = self::CACHE_KEY_PREFIX . $moduleName;

            // Check cache first
            if ($cachedResult = $this->cache->load($cacheKey)) {
                return (bool)$cachedResult;
            }

            if (!$this->firebaseHelper->isEnabled()) {
                $this->logger->warning("Firebase is not enabled for license validation");
                return false;
            }

            $config = $this->firebaseHelper->getFirebaseConfig();
            if (empty($config)) {
                $this->logger->warning("Firebase configuration is missing");
                return false;
            }

            // Build Firebase REST API URL
            $url = sprintf(
                'https://%s.firebaseio.com/licenses/%s.json?auth=%s',
                $config['projectId'],
                $moduleName,
                $config['apiKey']
            );

            // Make request to Firebase
            $this->curl->get($url);
            $response = json_decode($this->curl->getBody(), true);

            $isValid = !empty($response) && isset($response['license']) && $response['license'] === true;

            // Cache the result
            $this->cache->save((string)$isValid, $cacheKey, [], self::CACHE_LIFETIME);

            return $isValid;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'License check failed for module %s: %s',
                $moduleName,
                $e->getMessage()
            ));
            return false;
        }
    }
}
