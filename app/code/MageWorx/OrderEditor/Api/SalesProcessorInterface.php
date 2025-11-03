<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace MageWorx\OrderEditor\Api;

use Magento\Sales\Api\Data\OrderInterface;
use MageWorx\OrderEditor\Model\Order as OrderEditorOrder;

interface SalesProcessorInterface
{
    /**
     * Set order to process
     *
     * @param OrderEditorOrder $order
     * @return $this
     */
    public function setOrder(OrderEditorOrder $order): SalesProcessorInterface;

    /**
     * Get actual order
     *
     * @return OrderEditorOrder|null
     */
    public function getOrder(): ?OrderEditorOrder;

    /**
     * Update credit-memos, invoices, shipments
     *
     * @return bool
     */
    public function updateSalesObjects(): bool;

    /**
     * Check is order must be processed (invoiced, refunded, shipped)
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isNeedToProcessOrder(\Magento\Sales\Api\Data\OrderInterface $order): bool;
}
