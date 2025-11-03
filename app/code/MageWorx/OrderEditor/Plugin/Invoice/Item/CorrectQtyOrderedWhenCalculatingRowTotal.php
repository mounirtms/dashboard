<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Invoice\Item;

use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItemModel;

/**
 * We need to correct the qty ordered of order item before calculate the invoice item subtotal because it
 * does not take into account the qty canceled of order item.
 * @see InvoiceItemModel::calcRowTotal() line ~247:
 * ```
 * $this->setRowTotalInclTax(
 *    $invoice->roundPrice($rowTotalInclTax / $orderItemQty * $this->getQty(), 'including')
 * );
 * ```                     ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
 */
class CorrectQtyOrderedWhenCalculatingRowTotal
{
    /**
     * @param InvoiceItemInterface|InvoiceItemModel $subject
     * @param callable $proceed
     * @return InvoiceItemInterface
     */
    public function aroundCalcRowTotal(
        InvoiceItemInterface $subject,
        callable             $proceed
    ): InvoiceItemInterface {
        $orderItem = $subject->getOrderItem();
        if (!$orderItem) {
            return $proceed();
        }

        $oiQtyRemoved = (float)$orderItem->getQtyCanceled() + (float)$orderItem->getQtyRefunded();
        if (0 >= $oiQtyRemoved) {
            return $proceed();
        }

        $oiQtyOrdered  = $orderItem->getQtyOrdered();
        $oiQtyCanceled = $orderItem->getQtyCanceled();
        $oiQtyRemain   = $oiQtyOrdered - $oiQtyCanceled;
        if ($oiQtyCanceled && $oiQtyRemain > 0) {
            // Fix qty ordered for subtotal calculations
            $orderItem->setQtyOrdered($oiQtyRemain);
        }

        $result = $proceed();

        if ($oiQtyCanceled && $oiQtyRemain > 0) {
            // Return qty ordered to initial state back
            $orderItem->setQtyOrdered($oiQtyOrdered);
        }

        return $result;
    }
}
