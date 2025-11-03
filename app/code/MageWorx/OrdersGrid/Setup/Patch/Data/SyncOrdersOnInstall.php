<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace MageWorx\OrdersGrid\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;

class SyncOrdersOnInstall implements DataPatchInterface
{
    /**
     * @var \MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection
     */
    private $collection;

    /**
     * @param \MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection $collection
     */
    public function __construct(
        \MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection $collection
    ) {
        $this->collection = $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->collection->syncOrdersData([]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
