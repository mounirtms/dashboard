<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

/**
 * Interface ShippingAddressManagerInterface
 *
 * Manage the order shipping address
 */
interface ShippingAddressManagerInterface
{
    /**
     * Get shipping address information by order id
     *
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderAddressInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShippingAddressByOrderId(int $orderId): \Magento\Sales\Api\Data\OrderAddressInterface;

    /**
     * Update shipping address data by order id
     *
     * @param int $orderId
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $shippingAddressData
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateShippingAddressDataByOrderId(
        int $orderId,
        \Magento\Sales\Api\Data\OrderAddressInterface $shippingAddressData
    ): void;
}
