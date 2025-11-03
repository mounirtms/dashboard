<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;

class Collection extends AbstractCollection
{
    /**
     * Set resource model and determine field mapping
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \MageWorx\OrdersGrid\Model\GridAdditionalData::class,
            \MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData::class
        );
        $this->_setIdFieldName(GridAdditionalDataInterface::KEY_ID);
    }
}
