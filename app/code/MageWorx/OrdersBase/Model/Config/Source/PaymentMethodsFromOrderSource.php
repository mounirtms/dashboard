<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersBase\Model\Config\Source;

use MageWorx\OrdersBase\Api\PaymentMethodsFromOrderSourceInterface;

class PaymentMethodsFromOrderSource implements PaymentMethodsFromOrderSourceInterface
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
                    ['sog' => $this->gridResource->getTable('sales_order_grid')],
                    ['payment_method']
                )
                ->where('payment_method IS NOT NULL')
                ->distinct(true);

            $data = $connection->fetchAll($select);
            $options = [];

            foreach ($data as $datum) {
                $options[] = [
                    'value' => $datum['payment_method'],
                    'label' => $datum['payment_method']
                ];
            }

            $this->options = $options;
        }

        return $this->options;
    }
}
