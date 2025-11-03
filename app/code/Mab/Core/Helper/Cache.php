<?php
namespace Mab\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Mab\Core\Model\Cache\Type\Config as ConfigCache;
use Mab\Core\Model\Cache\Type\Delivery as DeliveryCache;
use Mab\Core\Model\Cache\Type\Customer as CustomerCache;

class Cache extends AbstractHelper
{
    protected $cacheTypeList;
    protected $cacheFrontendPool;

    public function __construct(
        Context $context,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        parent::__construct($context);
    }

    public function saveCache($data, $identifier, $tags = [], $type = ConfigCache::TYPE_IDENTIFIER)
    {
        try {
            $cache = $this->cacheFrontendPool->get($type);
            return $cache->save(
                is_string($data) ? $data : json_encode($data),
                $identifier,
                $tags,
                86400 // 24 hours
            );
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            return false;
        }
    }

    public function loadCache($identifier, $type = ConfigCache::TYPE_IDENTIFIER)
    {
        try {
            $cache = $this->cacheFrontendPool->get($type);
            $data = $cache->load($identifier);
            return $data ? json_decode($data, true) : false;
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            return false;
        }
    }

    public function cleanCache($type = null)
    {
        try {
            if ($type) {
                $this->cacheTypeList->cleanType($type);
            } else {
                $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);
                $this->cacheTypeList->cleanType(DeliveryCache::TYPE_IDENTIFIER);
                $this->cacheTypeList->cleanType(CustomerCache::TYPE_IDENTIFIER);
            }
            return true;
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            return false;
        }
    }
}
