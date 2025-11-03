<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersBase\Model\Config\Source;

use MageWorx\OrdersBase\Api\CarriersFromOrderSourceInterface;

class CarriersFromOrderSource implements CarriersFromOrderSourceInterface
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Grid
     */
    protected $gridResource;

    protected $options = [];

    /**
     * @param \Magento\Sales\Model\ResourceModel\Grid $gridResource
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Grid $gridResource
    ) {
        $this->gridResource = $gridResource;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        if (empty($this->options)) {
            $connection = $this->gridResource->getConnection();
            $select     = $connection
                ->select()
                ->from(
                    ['so' => $this->gridResource->getTable('sales_order')],
                    ['shipping_method', 'shipping_description']
                )
                ->where('shipping_method IS NOT NULL')
                ->distinct(true);

            $data = $connection->fetchAll($select);
            $options = [];

            foreach ($data as $datum) {
                $options[] = [
                    'value' => $datum['shipping_method'],
                    'label' => $datum['shipping_description']
                ];
            }

            $this->options = $options;
        }

        return $this->options;
    }
}
