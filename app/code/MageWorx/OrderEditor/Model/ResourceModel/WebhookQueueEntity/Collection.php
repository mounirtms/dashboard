<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

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
            \MageWorx\OrderEditor\Model\Webhooks\QueueEntity::class,
            \MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity::class
        );
        $this->_setIdFieldName('entity_id');
    }
}
