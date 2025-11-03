<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WebhookQueueEntity extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            'mageworx_order_editor_webhook_queue',
            'entity_id'
        );
    }
}
