<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\Indexer;

/**
 * Always perform full rebuild, because each row depends on another by sort order. So, changes in one entity could
 * affect another entities.
 * Complexity is {[number of shipping methods] * [number of payment methods] * [number of order statuses]} available in
 * the store (~40k in Magento out-of-the-box).
 * Must be rebuilt after each change made in the "Grid Additional Data" entity.
 */
class AdditionalData implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    /**
     * @var \MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData
     */
    protected $resource;

    /**
     * @param \MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData $resource
     */
    public function __construct(
        \MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * Execute materialization on ids entities
     *
     * @param int[] $ids
     * @return void
     */
    public function execute($ids): void
    {
        $this->resource->rebuildIndexTable($ids);
    }

    /**
     * @return void
     */
    public function executeFull(): void
    {
        $this->execute([]);
    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids): void
    {
        $this->execute($ids);
    }

    /**
     * @param $id
     * @return void
     */
    public function executeRow($id): void
    {
        $this->execute([$id]);
    }
}
