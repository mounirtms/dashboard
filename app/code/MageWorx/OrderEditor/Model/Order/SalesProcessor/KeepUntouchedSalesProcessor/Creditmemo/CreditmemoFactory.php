<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Order\SalesProcessor\KeepUntouchedSalesProcessor\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;

class CreditmemoFactory extends \Magento\Sales\Model\Order\CreditmemoFactory
{
    /**
     * Prepare order creditmemo based on order items and requested params.
     * It allows us to create a creditmemo without items, just for total amount.
     * Original creditmemo factory requires at least one item or creates a creditmemo for the whole order (in case we
     * do not pass any item as an argument)
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $data
     * @return Creditmemo
     */
    public function createByOrder(\Magento\Sales\Model\Order $order, array $data = []): Creditmemo
    {
        if (!empty($data['refund_without_items'])) {
            $creditmemo = $this->convertor->toCreditmemo($order);
            $creditmemo->setTotalQty(0);
            $this->initData($creditmemo, $data);
            $creditmemo->collectTotals();
        } else {
            $creditmemo = parent::createByOrder($order, $data);

            /*
             * Remove shipping and recollect totals manually.
             * To refund shipping amount we must use creditmemo with no items.
             */
            $gt                        = $creditmemo->getGrandTotal();
            $bgt                       = $creditmemo->getBaseGrandTotal();
            $initialShippingAmount     = $creditmemo->getShippingAmount();
            $initialBaseShippingAmount = $creditmemo->getBaseShippingAmount();
            $initialShippingTax        = $creditmemo->getShippingTaxAmount();
            $initialBaseShippingTax    = $creditmemo->getBaseShippingTaxAmount();

            $gt  -= ($initialShippingAmount + $initialShippingTax);
            $bgt -= ($initialBaseShippingAmount + $initialBaseShippingTax);

            $creditmemo->setGrandTotal($gt)
                       ->setBaseGrandTotal($bgt)
                       ->setShippingAmount(0)
                       ->setShippingTaxAmount(0)
                       ->setBaseShippingTaxAmount(0)
                       ->setBaseShippingAmount(0)
                       ->setBaseShippingInclTax(0)
                       ->setShippingInclTax(0)
                       ->setShippingDiscountAmount(0)
                       ->setBaseShippingDiscountAmount(0);
        }

        return $creditmemo;
    }
}
