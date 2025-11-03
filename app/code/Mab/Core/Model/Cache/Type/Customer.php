<?php
namespace Mab\Core\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Customer extends TagScope
{
    const TYPE_IDENTIFIER = 'mab_customer';
    const CACHE_TAG = 'MAB_CUSTOMER';

    public function __construct(FrontendPool $frontendPool)
    {
        parent::__construct($frontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
