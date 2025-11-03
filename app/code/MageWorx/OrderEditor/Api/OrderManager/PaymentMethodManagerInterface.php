<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

/**
 * Interface PaymentMethodManagerInterface
 *
 * Manage the payment method of the order
 */
interface PaymentMethodManagerInterface
{
    /**
     * Get information about selected payment method
     *
     * @param int $orderId
     * @return \MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentMethodByOrderId(int $orderId
    ): \MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterface;

    /**
     * @param int $orderId
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterface $paymentMethodData
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updatePaymentMethodByOrderId(
        int $orderId,
        \MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterface $paymentMethodData
    ): void;
}
