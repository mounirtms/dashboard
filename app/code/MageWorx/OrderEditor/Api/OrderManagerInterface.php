<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

interface OrderManagerInterface
{
    /**
     * Backup quote data for the quote related to the selected order.
     *
     * @param int $orderId
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function backupOrdersQuoteByOrderId(int $orderId): void;

    /**
     * Restore quote data for the quote related to the selected order.
     *
     * @param int $orderId
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function restoreOrdersQuoteByOrderId(int $orderId): void;
}
