<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Invoice;

use \Magento\Sales\Api\Data\InvoiceInterface;

class CorrectIsLastFlag
{
    /**
     * Return false in case when order has cancelled items (and subtotal)
     * to prevent incorrect invoice subtotal. Invoice subtotal must include only not cancelled items.
     *
     * @param InvoiceInterface $subject
     * @param bool $result
     * @return bool
     */
    public function afterIsLast(InvoiceInterface $subject, bool $result): bool
    {
        if ($result) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $subject->getOrder();
            if ($order) {
                return 0 >= (float)$order->getSubtotalCanceled();
            }
        }

        return $result;
    }
}
