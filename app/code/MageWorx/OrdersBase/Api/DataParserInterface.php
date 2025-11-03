<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersBase\Api;

interface DataParserInterface
{
    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param Data\DeviceDataInterface $deviceData
     */
    public function parseData(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \MageWorx\OrdersBase\Api\Data\DeviceDataInterface $deviceData
    ): void;
}
