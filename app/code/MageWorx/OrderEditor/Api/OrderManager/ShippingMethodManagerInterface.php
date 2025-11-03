<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

interface ShippingMethodManagerInterface
{
    /**
     * @param int $orderId
     * @return \MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterface
     */
    public function getShippingMethodByOrderId(int $orderId
    ): \MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterface;

    /**
     * Change shipping method in the corresponding quote.
     * To apply changes in the order the commit method must be called.
     *
     * @param int $orderId
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterface $shippingMethodData
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function setShippingMethodByOrderId(
        int $orderId,
        \MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterface $shippingMethodData
    ): \Magento\Sales\Api\Data\OrderInterface;
}
