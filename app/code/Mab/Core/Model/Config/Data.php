<?php
namespace Mab\Core\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;

class Data
{
    const CACHE_TAG = 'MAB_CONFIG';
    const CACHE_ID_PREFIX = 'mab_config_';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var \Magento\Framework\App\Cache\Frontend\Pool
     */
    protected $cacheFrontendPool;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Get cached config value
     *
     * @param string $path
     * @param string $scope
     * @param mixed $scopeCode
     * @return mixed
     */
    public function getConfig($path, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        $cacheId = $this->getCacheId($path, $scope, $scopeCode);
        $cache = $this->cacheFrontendPool->get(ConfigCache::TYPE_IDENTIFIER);
        
        $value = $cache->load($cacheId);
        if ($value === false) {
            $value = $this->scopeConfig->getValue($path, $scope, $scopeCode);
            $cache->save(
                \json_encode($value),
                $cacheId,
                [self::CACHE_TAG],
                86400 // 24 hours
            );
        } else {
            $value = \json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * Clean config cache
     *
     * @return void
     */
    public function clean()
    {
        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);
    }

    /**
     * Get cache ID for config path
     *
     * @param string $path
     * @param string $scope
     * @param mixed $scopeCode
     * @return string
     */
    protected function getCacheId($path, $scope, $scopeCode)
    {
        return self::CACHE_ID_PREFIX . md5($path . $scope . $scopeCode);
    }
}
