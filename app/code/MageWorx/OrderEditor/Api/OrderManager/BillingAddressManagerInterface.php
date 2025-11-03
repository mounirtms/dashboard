<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

/**
 * Interface BillingAddressManagerInterface
 *
 * Manage the order billing address
 */
interface BillingAddressManagerInterface
{
    /**
     * Get billing address information by order id
     *
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderAddressInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getBillingAddressByOrderId(int $orderId): \Magento\Sales\Api\Data\OrderAddressInterface;

    /**
     * Update billing address data by order id
     *
     * @param int $orderId
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddressData
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateBillingAddressDataByOrderId(
        int $orderId,
        \Magento\Sales\Api\Data\OrderAddressInterface $billingAddressData
    ): void;
}
