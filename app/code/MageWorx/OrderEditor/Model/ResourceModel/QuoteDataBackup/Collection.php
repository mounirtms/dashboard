<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\ResourceModel\QuoteDataBackup;

use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\DataObject;
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
            \MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface::class,
            \MageWorx\OrderEditor\Model\ResourceModel\QuoteDataBackup::class
        );
        $this->_setIdFieldName(\MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface::ID);
    }

    /**
     * Retrieve collection items
     *
     * @return DataObject[]|ExtensibleDataInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }
}
