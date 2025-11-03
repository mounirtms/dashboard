<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Api;

/**
 * Find all invoices for the order/order item
 */
interface InvoiceFinderInterface
{
    /**
     * @param int $orderItemId
     * @param int|null $orderId
     * @param float|null $qty
     * @return \Magento\Sales\Api\Data\InvoiceInterface[]
     */
    public function getInvoiceByOrderItemId(int $orderItemId, ?int $orderId, ?float $qty = 1): array;
}
