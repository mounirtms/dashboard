<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Order\SalesProcessor;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order as OriginalOrder;
use Magento\Sales\Model\Order\Invoice as OriginalInvoice;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\SalesProcessorInterface;
use MageWorx\OrderEditor\Model\Order;
use MageWorx\OrderEditor\Model\Order\SalesProcessorAbstract;

/**
 * Class DeleteAndCreateSalesProcessor
 *
 * Used to perform operations with related entities like: invoice, creditmemo, shipment.
 * Removes old invoices, creditmemos and shipments and create new ones from scratch instead.
 */
class DeleteAndCreateSalesProcessor extends SalesProcessorAbstract implements SalesProcessorInterface
{
    /**
     * Update credit-memos, invoices, shipments
     *
     * @return bool
     */
    public function updateSalesObjects(): bool
    {
        try {
            $order = $this->getOrder();
            if ($order === null) {
                throw new LocalizedException(__('Order is not set!'));
            }

            $this->syncQuoteOfTheOrder($order);

            $this->eventManager->dispatch(
                'mw_oe_process_sales_object_before',
                [
                    'subject' => $this,
                    'order'   => $order
                ]
            );

            if (!$this->isNeedToProcessOrder($order)) {
                return true;
            }

            if ($order->hasCreditmemos()) {
                $this->updateCreditMemos();
            } else {
                $this->updateInvoices();
            }

            $this->shipmentManager->updateShipmentsOnOrderEdit($this->getOrder());
            $this->updatePayment();

            $this->eventManager->dispatch(
                'mw_oe_process_sales_object_after',
                [
                    'subject' => $this,
                    'order'   => $order
                ]
            );
        } catch (\Exception $e) {
            $this->eventManager->dispatch(
                'mw_oe_process_sales_object_error',
                [
                    'subject'   => $this,
                    'order'     => $order,
                    'exception' => $e
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function updateInvoices(): void
    {
        if ($this->getOrder()->hasInvoices()) {
            if ($this->isOrderTotalIncreased()
                && $this->helperData->getIsAllowKeepPrevInvoice()
            ) {
                // Updating invoice in +
                $this->createInvoiceForOrder();
            } else {
                // Create credit memo and update invoice
                $this->removeAllInvoices();
                $this->createInvoiceForOrder();
            }
        }
    }

    /**
     * Update payment object
     *
     * @return void
     */
    protected function updatePayment(): void
    {
        $order   = $this->getOrder();
        $payment = $this->getOrder()->getPayment();
        $payment->setAmountOrdered($order->getGrandTotal())
                ->setBaseAmountOrdered($order->getBaseGrandTotal())
                ->setBaseShippingAmount($order->getBaseShippingAmount())
                ->setShippingCaptured($order->getShippingInvoiced())
                ->setAmountRefunded($order->getTotalRefunded())
                ->setBaseAmountPaid($order->getBaseTotalPaid())
                ->setAmountCanceled($order->getTotalCanceled())
                ->setBaseAmountAuthorized($order->getBaseTotalInvoiced())
                ->setBaseAmountPaidOnline($order->getBaseTotalInvoiced())
                ->setBaseAmountRefundedOnline($order->getBaseTotalRefunded())
                ->setBaseShippingAmount($order->getBaseShippingAmount())
                ->setShippingAmount($order->getShippingAmount())
                ->setAmountPaid($order->getTotalInvoiced())
                ->setAmountAuthorized($order->getTotalInvoiced())
                ->setBaseAmountOrdered($order->getBaseGrandTotal())
                ->setBaseShippingRefunded($order->getBaseShippingRefunded())
                ->setShippingRefunded($order->getShippingRefunded())
                ->setBaseAmountRefunded($order->getBaseTotalRefunded())
                ->setAmountOrdered($order->getGrandTotal())
                ->setBaseAmountCanceled($order->getBaseTotalCanceled());

        $this->orderPaymentRepository->save($payment);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function updateCreditMemos(): void
    {
        if (!$this->isOrderTotalIncreased()
            || !$this->helperData->getIsAllowKeepPrevInvoice()
        ) {
            $this->removeAllCreditMemos();
        }
        $this->updateInvoices();
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function removeAllCreditMemos(): void
    {
        /**
         * @var \Magento\Sales\Model\Order\Creditmemo $creditMemos
         */
        $creditMemos = $this->getOrder()->getCreditmemosCollection();
        foreach ($creditMemos as $creditMemo) {
            $creditMemo->delete();
        }

        $orderItems = $this->getOrder()->getItems();
        foreach ($orderItems as $orderItem) {
            $orderItem->setQtyRefunded(0)
                      ->setQtyReturned(0)
                      ->setDiscountRefunded(0)
                      ->setBaseDiscountRefunded(0)
                      ->setAmountRefunded(0)
                      ->setBaseAmountRefunded(0)
                      ->setTaxRefunded(0)
                      ->setBaseTaxRefunded(0)
                      ->setDiscountTaxCompensationRefunded(0)
                      ->setBaseDiscountTaxCompensationRefunded(0);

            $this->oeOrderItemRepository->save($orderItem);
        }

        $state = OriginalOrder::STATE_PROCESSING;

        $this->getOrder()
             ->setTaxRefunded(0)->setBaseTaxRefunded(0)
             ->setDiscountRefunded(0)->setBaseDiscountRefunded(0)
             ->setSubtotalRefunded(0)->setBaseSubtotalRefunded(0)
             ->setShippingRefunded(0)->setBaseShippingRefunded(0)
             ->setTotalOfflineRefunded(0)->setBaseTotalOfflineRefunded(0)
             ->setTotalRefunded(0)->setBaseTotalRefunded(0)
             ->setState($state);

        $this->orderRepository->save($this->getOrder());

        $payment = $this->getOrder()->getPayment();
        $payment->setAmountRefunded(0)
                ->setBaseAmountRefunded(0)
                ->setBaseAmountRefundedOnline(0)
                ->setShippingRefunded(0)
                ->setBaseShippingRefunded(0);

        $this->orderPaymentRepository->save($payment);
    }

    /**
     * @throws \Exception
     */
    protected function removeAllInvoices(): void
    {
        $invoices = $this->getOrder()->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            $shippingMethodDataInRequest = $this->request->getPost('shipping_method');
            if (!$invoice->isCanceled() && !empty($shippingMethodDataInRequest)) {
                $invoice->cancel();
                $transaction = $this->transactionFactory->create();
                $transaction->addObject($invoice->getOrder())->save();
            }
            $invoice->delete();
        }

        $invoices->removeAllItems();

        foreach ($this->getOrder()->getAllItems() as $orderItem) {
            $orderItem->setQtyInvoiced(0)
                      ->setRowInvoiced(0)
                      ->setBaseRowInvoiced(0)
                      ->setTaxInvoiced(0)
                      ->setBaseTaxInvoiced(0)
                      ->setDiscountInvoiced(0)
                      ->setBaseDiscountInvoiced(0)
                      ->setGwPriceInvoiced(0)
                      ->setGwBasePriceInvoiced(0)
                      ->setGwTaxAmountInvoiced(0)
                      ->setGwBaseTaxAmountInvoiced(0)
                      ->setDiscountTaxCompensationInvoiced(0)
                      ->setBaseDiscountTaxCompensationInvoiced(0);

            $this->oeOrderItemRepository->save($orderItem);
        }

        $this->getOrder()
             ->setTaxInvoiced(0)->setBaseTaxInvoiced(0)
             ->setDiscountInvoiced(0)->setBaseDiscountInvoiced(0)
             ->setSubtotalInvoiced(0)->setBaseSubtotalInvoiced(0)
             ->setTotalInvoiced(0)->setBaseTotalInvoiced(0)
             ->setShippingInvoiced(0)->setBaseShippingInvoiced(0)
             ->setTotalPaid(0)->setBaseTotalPaid(0)
             ->setState(OriginalOrder::STATE_PROCESSING);

        $this->orderRepository->save($this->getOrder());
    }

    /**
     * @return void
     * @throws \Exception
     * @throws LocalizedException
     */
    protected function createInvoiceForOrder(): void
    {
        $this->getOrder()
             ->setState(OriginalOrder::STATE_PROCESSING);
        $this->orderRepository->save($this->getOrder());

        if ($this->getOrder()->canInvoice()) {
            $order   = $this->getOrder();
            $invoice = $this->getOrder()->prepareInvoice();
            if (!$invoice) {
                throw new LocalizedException(__('Can not create invoice'));
            }

            $invoice->setRequestedCaptureCase(OriginalInvoice::CAPTURE_ONLINE);
            $invoice->register();

            $databaseTransaction = $this->transactionFactory->create();
            $databaseTransaction->addObject($invoice)->addObject($invoice->getOrder())->save();

            $this->eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::SIMPLE_MESSAGE_KEY => __(
                        'New Invoice <b>%1</b> has been created <b>(%2)</b>',
                        $invoice->getIncrementId(),
                        $invoice->getOrder()->formatPriceTxt($invoice->getGrandTotal())
                    )
                ]
            );

            /* hack for fix lost $0.01 */
            $payment = $order->getPayment();
            $payment->setBaseAmountPaid($order->getBaseGrandTotal())
                    ->setAmountPaid($order->getGrandTotal());

            $this->orderPaymentRepository->save($payment);

            $order->setBaseTotalPaid($order->getBaseGrandTotal())
                  ->setTotalPaid($order->getGrandTotal());

            $this->orderRepository->save($order);
        }
    }

    /**
     * Check is order must be processed (invoiced, refunded, shipped)
     *
     * @param OrderInterface|Order $order
     * @return bool
     */
    public function isNeedToProcessOrder(\Magento\Sales\Api\Data\OrderInterface $order): bool
    {
        if ($this->isOrderHasNoChanges($order)) {
            return false;
        }

        return true;
    }
}
