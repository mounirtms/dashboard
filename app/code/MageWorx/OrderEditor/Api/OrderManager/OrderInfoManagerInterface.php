<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

/**
 * Interface OrderInfoManagerInterface
 *
 * Manage the Order Info block
 */
interface OrderInfoManagerInterface
{
    /**
     * Get Order information by order id
     *
     * @param int $orderId
     * @return \MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderInfoByOrderId(int $orderId): \MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterface;

    /**
     * Update Order information by order id
     *
     * @param int $orderId
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterface $orderInfo
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateOrderInfoByOrderId(
        int $orderId,
        \MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterface $orderInfo
    ): void;
}
