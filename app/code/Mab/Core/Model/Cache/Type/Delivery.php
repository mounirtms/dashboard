<?php
namespace Mab\Core\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Delivery extends TagScope
{
    const TYPE_IDENTIFIER = 'mab_delivery';
    const CACHE_TAG = 'MAB_DELIVERY';

    public function __construct(FrontendPool $frontendPool)
    {
        parent::__construct($frontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
