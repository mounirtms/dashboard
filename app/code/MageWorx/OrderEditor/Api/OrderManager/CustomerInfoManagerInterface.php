<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

interface CustomerInfoManagerInterface
{
    /**
     * Get information about customer
     *
     * @param int $orderId
     * @return \MageWorx\OrderEditor\Api\Data\OrderManager\CustomerInfoInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomerInfoByOrderId(int $orderId
    ): \MageWorx\OrderEditor\Api\Data\OrderManager\CustomerInfoInterface;

    /**
     * Update information about customer
     *
     * @param int $orderId
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\CustomerInfoInterface $customerInfo
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateCustomerInfoByOrderId(
        int $orderId,
        \MageWorx\OrderEditor\Api\Data\OrderManager\CustomerInfoInterface $customerInfo
    ): void;
}
