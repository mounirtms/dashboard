<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Creditmemo\Item;

use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItemModel;

/**
 * We need to correct the qty ordered of order item before calculate the creditmemo item subtotal because it
 * does not take into account the qty canceled of order item.
 *
 * @see CreditmemoItemModel::calcRowTotal() line ~247:
 * ```
 * $orderItemQty = $orderItem->getQtyOrdered();
 * $this->setRowTotalInclTax(
 *     $creditmemo->roundPrice($rowTotalInclTax / $orderItemQty * $qty, 'including')
 * );
 * $this->setBaseRowTotalInclTax(
 *     $creditmemo->roundPrice($baseRowTotalInclTax / $orderItemQty * $qty, 'including_base')
 * );
 * ```                         ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
 */
class CorrectQtyOrderedWhenCalculatingRowTotal
{
    /**
     * @param CreditmemoItemInterface|CreditmemoItemModel $subject
     * @param callable $proceed
     * @return CreditmemoItemInterface
     */
    public function aroundCalcRowTotal(
        CreditmemoItemInterface $subject,
        callable                $proceed
    ): CreditmemoItemInterface {
        $orderItem = $subject->getOrderItem();
        if (!$orderItem) {
            return $proceed();
        }

        $oiQtyOrdered      = (float)$orderItem->getQtyOrdered();
        $oiQtyCanceled     = (float)$orderItem->getQtyCanceled();
        $oiQtyRefunded     = (float)$orderItem->getQtyRefunded();
        $oiQtyRefundingNow = (float)$subject->getQty();
        $oiNewTempQty      = $oiQtyOrdered - $oiQtyCanceled - $oiQtyRefunded - $oiQtyRefundingNow;

        // Fix qty ordered for subtotal calculations
        if ($oiNewTempQty > 0) {
            $orderItem->setQtyOrdered($oiNewTempQty);
        } else {
            if (($oiQtyOrdered - $oiQtyCanceled) == 0) {
                return $subject;
            } else {
                $orderItem->setQtyOrdered($oiQtyOrdered - $oiQtyCanceled);
            }
        }

        $result = $proceed();

        // Return qty ordered to initial state back
        $orderItem->setQtyOrdered($oiQtyOrdered);

        return $result;
    }
}
