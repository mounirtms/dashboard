<?php
/**
 * Copyright ©  MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use MageWorx\OrdersGrid\Helper\Data as Helper;
use MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\CollectionFactory as OrderGridCustomCollectionFactory;

/**
 * Class SynchronizeOrderGrid
 *
 * Automatically synchronize recent orders when orders grid is opening
 */
class SynchronizeOrderGrid
{
    /**
     * @var OrderGridCustomCollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Helper
     */
    protected $helperData;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * SynchronizeOrderGrid constructor.
     *
     * @param OrderGridCustomCollectionFactory $collectionFactory
     * @param Helper $helperData
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        OrderGridCustomCollectionFactory $collectionFactory,
        Helper $helperData,
        ResourceConnection $resourceConnection
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->helperData = $helperData;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Synchronize each available object
     *
     * @param $subject
     * @return void
     */
    public function beforeExecute($subject)
    {
        if ($this->helperData->getSyncOrderGrid()) {
            /** @var \MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection $collection */
            $collection = $this->collectionFactory->create();
            $orderCount = $this->helperData->getSyncOrdersCount();

            /** @var AdapterInterface $connection */
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from(['so' => $collection->getTable('sales_order')], ['entity_id'])
                ->order(['entity_id ' . \Magento\Framework\DB\Select::SQL_DESC])
                ->limit($orderCount);

            $query = $connection->fetchAll($select);
            $orderIds = [];
            foreach ($query as $row => $value) {
                $orderIds[] = $value['entity_id'];
            }

            $collection->syncOrdersData($orderIds);
        }
    }
}
