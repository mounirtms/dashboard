<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteDataBackup extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \MageWorx\OrderEditor\Api\RestoreQuoteInterface::TABLE_NAME,
            \MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface::ID
        );
    }
}
