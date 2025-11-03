<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Order\SalesProcessor;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoCommentCreationInterfaceFactory;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\RefundInvoiceInterface;
use Magento\Sales\Model\Order as OriginalOrder;
use Magento\Sales\Model\Order\Invoice as OriginalInvoice;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\InvoiceFinderInterface;
use MageWorx\OrderEditor\Api\OrderItemRepositoryInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\SalesProcessorInterface;
use MageWorx\OrderEditor\Api\ShipmentManagerInterface;
use MageWorx\OrderEditor\Helper\Data as Helper;
use MageWorx\OrderEditor\Model\Order;
use MageWorx\OrderEditor\Model\Order\SalesProcessor\KeepUntouchedSalesProcessor\Creditmemo\CreditmemoFactory as CustomCreditmemoFactory;
use MageWorx\OrderEditor\Model\Order\SalesProcessorAbstract;

/**
 * Updated sales processor which will save invoices and credit memos instead of deleting them.
 */
class KeepUntouchedSalesProcessor extends SalesProcessorAbstract implements SalesProcessorInterface
{
    /**
     * @var CustomCreditmemoFactory
     */
    protected $creditmemoFactory;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var RefundInvoiceInterface
     */
    protected $refundInvoice;

    /**
     * @var \Magento\Sales\Api\Data\CreditmemoCommentCreationInterfaceFactory
     */
    protected $creditmemoCommentCreationFactory;

    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * @var \Magento\Sales\Api\CreditmemoManagementInterface
     */
    protected $creditmemoService;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var InvoiceFinderInterface
     */
    protected $invoiceFinder;

    /**
     * @param Helper $helperData
     * @param TransactionFactory $transactionFactory
     * @param HttpRequest $request
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $oeOrderItemRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param ShipmentManagerInterface $shipmentManager
     * @param EventManager $eventManager
     * @param CustomCreditmemoFactory $creditmemoFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param RefundInvoiceInterface $refundInvoice
     * @param CreditmemoCommentCreationInterfaceFactory $creditmemoCommentCreationFactory
     * @param CreditmemoManagementInterface $creditmemoService
     * @param Registry $registry
     * @param InvoiceFinderInterface $invoiceFinder
     */
    public function __construct(
        Helper                                                            $helperData,
        TransactionFactory                                                $transactionFactory,
        HttpRequest                                                       $request,
        OrderRepositoryInterface                                          $orderRepository,
        OrderItemRepositoryInterface                                      $oeOrderItemRepository,
        OrderPaymentRepositoryInterface                                   $orderPaymentRepository,
        ShipmentManagerInterface                                          $shipmentManager,
        EventManager                                                      $eventManager,
        CustomCreditmemoFactory                                           $creditmemoFactory,
        CreditmemoRepositoryInterface                                     $creditmemoRepository,
        \Magento\Sales\Api\RefundInvoiceInterface                         $refundInvoice,
        \Magento\Sales\Api\Data\CreditmemoCommentCreationInterfaceFactory $creditmemoCommentCreationFactory,
        \Magento\Sales\Api\CreditmemoManagementInterface                  $creditmemoService,
        \Magento\Framework\Registry                                       $registry,
        InvoiceFinderInterface                                            $invoiceFinder
    ) {
        parent::__construct(
            $helperData,
            $transactionFactory,
            $request,
            $orderRepository,
            $oeOrderItemRepository,
            $orderPaymentRepository,
            $shipmentManager,
            $eventManager
        );
        $this->creditmemoFactory                = $creditmemoFactory;
        $this->creditmemoRepository             = $creditmemoRepository;
        $this->refundInvoice                    = $refundInvoice;
        $this->creditmemoCommentCreationFactory = $creditmemoCommentCreationFactory;
        $this->creditmemoService                = $creditmemoService;
        $this->registry                         = $registry;
        $this->invoiceFinder                    = $invoiceFinder;
    }

    /**
     * Update credit-memos, invoices, shipments.
     * Synchronize quote data with order data.
     * Synchronize payment data with order data.
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

            // Sync quote data always, it's ok
            $order->syncQuote(); // @TODO Check: it must delete removed quote items

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

            // Refund
            $this->refundItems($order);
            $this->refundAmounts($order);

            // Invoice
            $this->createInvoiceForOrder($order);

            // Ship
            $this->shipmentManager->updateShipmentsOnOrderEdit($this->getOrder());

            // Synchronize changes with payment method
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
     * Refund removed items (including shipping amount). Used in case when not only totals has been changed.
     *
     * @param Order $order
     * @return void
     * @throws \Exception
     */
    protected function refundItems(Order $order): void
    {
        if ($order->hasRemovedItems() || $order->hasItemsWithDecreasedQty()) {
            $decreasedItems = $order->getDecreasedItems();
            $removedItems   = $order->getRemovedItems(); // @TODO Removed items must has full qty
            // Removed items has priority against decreased items. Do not change the order!
            $itemsToRemove  = $removedItems + $decreasedItems;

            $creditmemoCandidates = [];
            foreach ($itemsToRemove as $removedOrderItemId => $qty) {
                $orderItem   = $order->getItemById($removedOrderItemId);
                if (!$orderItem) {
                    continue;
                }

                $qtyInvoiced = (float)$orderItem->getQtyInvoiced();
                if ($qtyInvoiced) {
                    // Create creditmemo
                    $creditmemoCandidates[$removedOrderItemId] = $orderItem;
                }
            }

            if (!empty($creditmemoCandidates)) {
                /**
                 * If we create creditmemo without items and without flag it will refund whole order
                 * using regular magento rules
                 */
                $isOnline = (bool)$order->getPayment()->getLastTransId();

                $creditmemoData = [
                    'do_offline'   => !$isOnline,
                    'comment_text' => 'Automatically created creditmemo'
                ];

                $this->createCreditmemoForOrderItems($order, $creditmemoCandidates, $creditmemoData);
            }
        }
    }

    /**
     * Refund amount without items (including shipping amount). Used in case when totals changed.
     *
     * @param Order $order
     * @return void
     * @throws \Exception
     */
    protected function refundAmounts(Order $order): void
    {
        $changesInAmount            = $order->getChangesInAmounts();
        $changesInShipping          = $order->getChangesInShipping();
        $refundBaseAmountPerInvoice = [];
        $isInvoiceLocked            = (bool)$order->getData('invoice_locked');

        if ($this->isRefundAmountNotNecessary($order)) {
            /**
             * Set as much total refunded as needed on each item whenever we did not create a real refund
             * because of the totals difference of whole order.
             */
            $this->correctItemTotalsWhenRealRefundIsNotNecessary($order);

            return;
        }

        $refundBaseAmount = 0;
        $compensation     = 0;

        // Collect changes in items
        foreach ($changesInAmount as $itemId => $itemChanges) {
            $orderItem = $order->getItemById($itemId);

            // Get total invoiced from the beginning of the order
            $invoicedBaseAmount = (float)$orderItem->getBaseRowInvoiced();
            // Get actual row total for now
            $newBaseAmount = (float)$orderItem->getBaseRowTotalInclTax();
            // Get actual row total refunded for now
            $baseAmountRefunded = (float)$orderItem->getBaseAmountRefunded();
            // Calculate real invoiced total (invoiced minus refunded for now) for now
            $baseRealTotalInvoiced = $invoicedBaseAmount - $baseAmountRefunded;

            // We must create new creditmemo only when total real invoiced amount is smaller than new base amount
            if ($baseRealTotalInvoiced > $newBaseAmount) {
                // Return for that item
                $refundBaseAmountItem = $invoicedBaseAmount - $newBaseAmount; // @TODO Somewhere here we need to correct sum for already refunded (decrease sum)
                // Return only difference, excluding total refunded in past
                $refundBaseRealAmountItem = $refundBaseAmountItem - $baseAmountRefunded;
                $refundedAmountItem       = (float)$orderItem->getRowInvoiced()
                    - (float)$orderItem->getRowTotalInclTax();
                $orderItem->setBaseAmountRefunded($refundBaseAmountItem);
                $orderItem->setAmountRefunded($refundedAmountItem);
                // Add to total refunded
                $refundBaseAmount += $refundBaseRealAmountItem;

                // Collect data per invoice
                /** @var \Magento\Sales\Model\Order\Invoice[] $invoices */
                $invoices = $this->invoiceFinder->getInvoiceByOrderItemId(
                    (int)$itemId,
                    (int)$order->getId()
                );

                foreach ($invoices as $invoice) {
                    $refundBaseAmountPerInvoice[$invoice->getEntityId()]['invoice'] = $invoice;

                    $amountToReturnForInvoice    = $refundBaseAmountPerInvoice[$invoice->getEntityId()]
                        ['base_amount_to_return'] ?? 0;
                    $newAmountToReturnForInvoice = $amountToReturnForInvoice + $refundBaseRealAmountItem;

                    if ($invoice->getBaseSubtotalInclTax() > $newAmountToReturnForInvoice) {
                        /**
                         * Case when invoice base grand total covers all amount to return,
                         * so we do not need to use more than one invoice to proceed.
                         */
                        $refundBaseAmountPerInvoice[$invoice->getEntityId()]['base_amount_to_return']
                            = $newAmountToReturnForInvoice;
                        break;
                    } else {
                        /**
                         * Case when invoice base grand total did not cover all amount to return, and we must
                         * use another invoice to process this creditmemo. We are checked all invoices
                         * one by one before GT sum did not reach amount to be returned.
                         */
                        $refundBaseAmountPerInvoice[$invoice->getEntityId()]['base_amount_to_return']
                            += $invoice->getBaseSubtotalInclTax();
                    }
                }
            } else {
                $compensation += $newBaseAmount - $baseRealTotalInvoiced;
            }
        }

        // Collect changes in shipping
        if (!empty($changesInShipping['base_amount_incl_tax'])
            && $changesInShipping['base_amount_incl_tax'] < 0
        ) {
            $baseShippingAmountToRefund = abs($changesInShipping['base_amount_incl_tax']);
            $refundBaseAmount           += $baseShippingAmountToRefund;
            // Check invoice with shipping amount
            $shippingInvoice = $this->getOrder()
                                    ->getInvoiceCollection()
                                    ->addFieldToFilter(
                                        'shipping_amount',
                                        ['gt' => 0]
                                    )
                                    ->getFirstItem();

            $invoiceId                                         = $shippingInvoice->getEntityId();
            $refundBaseAmountPerInvoice[$invoiceId]['invoice'] = $shippingInvoice;

            if (empty($refundBaseAmountPerInvoice[$invoiceId]['base_amount_to_return'])) {
                $refundBaseAmountPerInvoice[$invoiceId]['base_amount_to_return'] = $baseShippingAmountToRefund;
            } else {
                $refundBaseAmountPerInvoice[$invoiceId]['base_amount_to_return'] += $baseShippingAmountToRefund;
            }
        }

        // Do refund
        if ($refundBaseAmount > 0) {
            $this->refundBaseAmountWithoutItem($refundBaseAmount, $order, $refundBaseAmountPerInvoice, $compensation);
        }
    }

    /**
     * When totals has been changed for two or more items in different directions we should use only one case of two:
     * create refund for a difference or crete invoice for a difference. Here we are checking is a refund necessary.
     * It should work correctly with a cse when one item totally refunded and other two (or more) items has a changes in
     * amounts in different directions.
     *
     * @param Order $order
     * @return bool
     */
    public function isRefundAmountNotNecessary(Order $order): bool
    {
        $changesInAmount   = $order->getChangesInAmounts();
        $changesInShipping = $order->getChangesInShipping();
        $isInvoiceLocked   = (bool)$order->getData('invoice_locked');

        // Always create credit memo when do invoice is locked
        if ($isInvoiceLocked) {
            return false;
        }

        if (empty($changesInAmount) && empty($changesInShipping)) {
            return true; // There were no changes in amounts
        }

        // Check total amount changed and if amount added greater than amount removed - don't refund
        $sum = 0;
        foreach ($changesInAmount as $value) {
            $sum += $value['base_row_total'];
        }

        $sum += !empty($changesInShipping['base_amount_incl_tax']) ? $changesInShipping['base_amount_incl_tax'] : 0;

        if ($sum > 0) {
            return true;
        }

        return false;
    }

    /**
     * When order has a difference in totals and real refund is not necessary we must correct item totals
     * manually to prevent future errors in calculations.
     *
     * @param Order $order
     * @return void
     */
    private function correctItemTotalsWhenRealRefundIsNotNecessary(Order $order): void
    {
        $changesInAmount = $order->getChangesInAmounts();
        if (!empty($changesInAmount)) {
            foreach ($changesInAmount as $orderItemId => $data) {
                $rowTotalChanges = $data['base_row_total'];
                if ($rowTotalChanges < 0) {
                    $orderItem = $order->getItemById($orderItemId);
                    if ($orderItem->getQtyInvoiced() > 0) {
                        $newBaseAmountRefunded = $orderItem->getBaseAmountRefunded() + abs($rowTotalChanges);
                        $newAmountRefunded     = $order->getBaseToOrderRate() * $newBaseAmountRefunded;
                        $orderItem->setBaseAmountRefunded($newBaseAmountRefunded);
                        $orderItem->setAmountRefunded($newAmountRefunded);
                    }
                }
            }
        }
    }

    /**
     * Refund amount without physical item return
     * Used when item price decreased
     *
     * @param float $amount
     * @param Order $order
     * @param array $refundBaseAmountPerInvoice
     * @throws \Exception
     */
    private function refundBaseAmountWithoutItem(
        float $amount,
        Order $order,
        array $refundBaseAmountPerInvoice = [],
        float $invoiceBaseAmountCompensation = 0
    ): void {
        $isOnline = (bool)$order->getPayment()->getLastTransId();

        $creditmemoData                                         = [];
        $creditmemoData['do_offline']                           = !$isOnline;
        $creditmemoData['comment_text']                         = 'Automatically created creditmemo';
        $creditmemoData['shipping_amount']                      = 0;
        $creditmemoData['adjustment_positive']                  = 0;
        $creditmemoData['adjustment_negative']                  = 0;
        $creditmemoData['refund_customerbalance_return_enable'] = 0; // Refund to customer balance
        $creditmemoData['refund_without_items']                 = true; // Flag for factory to prevent items calculations

        $creditMemosData = [];
        foreach ($refundBaseAmountPerInvoice as $invoiceId => $data) {
            $baseAmountToReturn = $data['base_amount_to_return'];

            if ($invoiceBaseAmountCompensation >= $baseAmountToReturn) {
                $invoiceBaseAmountCompensation -= $baseAmountToReturn;
                continue;
            } else {
                $baseAmountToReturn -= $invoiceBaseAmountCompensation;
            }

            $creditMemosData[$invoiceId]                        = $creditmemoData;
            $creditMemosData[$invoiceId]['invoice']             = $data['invoice'];
            $creditMemosData[$invoiceId]['adjustment_positive'] = $baseAmountToReturn;
        }

        $this->createCreditMemoAndRefund($order, $creditMemosData);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Api\Data\OrderItemInterface[] $orderItems
     * @return void
     * @throws \Exception
     */
    private function createCreditmemoForOrderItems(
        \Magento\Sales\Api\Data\OrderInterface $order,
        array                                  $orderItems = [],
        array                                  $data = []
    ): void {
        $order                 = $this->getOrder();
        $decreasedItems = $order->getDecreasedItems();
        $removedItems   = $order->getRemovedItems(); // @TODO Removed items must has full qty
        // Removed items has priority against decreased items. Do not change the order!
        $itemsToRemove  = $removedItems + $decreasedItems;

        // Prepare creditmemo data
        $creditMemosData = [];

        // Prepare items qty
        foreach ($orderItems as $orderItem) {
            $orderItemId = (int)$orderItem->getItemId();
            $qtyToRefund = !empty($itemsToRemove[$orderItemId])
                ? (float)$itemsToRemove[$orderItemId] // Item has decreased qty and not fully removed from the order
                : (float)$orderItem->getQtyInvoiced() - (float)$orderItem->getQtyRefunded(); // Item totally removed from the order

            $invoices = $this->invoiceFinder->getInvoiceByOrderItemId(
                $orderItemId,
                (int)$order->getId(),
                $qtyToRefund
            );

            foreach ($invoices as $invoice) {
                /**
                 * Getting the item from invoice corresponding to the item from order.
                 * We need to detect actual invoice for creditmemo for online refunds (transactions).
                 */
                $invoiceItems           = $invoice->getItems();
                $invoiceItemByOrderItem = null;
                foreach ($invoiceItems as $invoiceItem) {
                    if ((int)$invoiceItem->getOrderItemId() === $orderItemId) {
                        $invoiceItemByOrderItem = $invoiceItem;
                        break;
                    }
                }

                if ($invoiceItemByOrderItem === null) {
                    throw new NoSuchEntityException(
                        __('Unable to locate invoice for order item with id %1', $orderItemId)
                    );
                }

                /**
                 * Getting minimum qty to refund according current invoice item. Finally, the $qtyToRefund must be 0,
                 * but it may need more than one invoice item (more than one invoice) in case when item
                 * has been invoiced twice or more time.
                 */
                $qty         = min($qtyToRefund, $invoiceItemByOrderItem->getQty());
                $qtyToRefund -= $qty; // During next iteration we must see reduced qty to refund to process correct qty.

                if (empty($creditMemosData[$invoice->getEntityId()])) {
                    $creditMemosData[$invoice->getEntityId()] = $data;
                }

                $creditMemosData[$invoice->getEntityId()]['invoice']                    = $invoice;
                $creditMemosData[$invoice->getEntityId()]['qtys'][$orderItemId]         = $qty;
                $creditMemosData[$invoice->getEntityId()]['items'][$orderItemId]['qty'] = $qty;
            }
        }

        $this->createCreditMemoAndRefund($order, $creditMemosData);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order
     * @param array $creditMemosData
     * @return void
     * @throws LocalizedException
     */
    public function createCreditMemoAndRefund(
        \Magento\Sales\Api\Data\OrderInterface $order,
        array                                  $creditMemosData
    ): void {
        foreach ($creditMemosData as $creditmemoData) {
            $creditMemo = $this->creditmemoFactory->createByOrder($order, $creditmemoData);
            $creditMemo->setInvoice($creditmemoData['invoice']);

            $this->eventManager->dispatch(
                'adminhtml_sales_order_creditmemo_register_before',
                ['creditmemo' => $creditMemo, 'input' => $creditmemoData]
            );

            $this->registry->register('current_creditmemo', $creditMemo);

            if ($creditMemo) {
                if (!$creditMemo->isValidGrandTotal()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }

                if (!empty($creditmemoData['comment_text'])) {
                    $creditMemo->addComment(
                        $creditmemoData['comment_text'],
                        isset($creditmemoData['comment_customer_notify']),
                        isset($creditmemoData['is_visible_on_front'])
                    );

                    $creditMemo->setCustomerNote($creditmemoData['comment_text']);
                    $creditMemo->setCustomerNoteNotify(isset($creditmemoData['comment_customer_notify']));
                }

                if (isset($creditmemoData['do_offline'])) {
                    //do not allow online refund for Refund to Store Credit
                    if (!$creditmemoData['do_offline'] && !empty($creditmemoData['refund_customerbalance_return_enable'])) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('Cannot create online refund for Refund to Store Credit.')
                        );
                    }
                }

                $creditMemo->getOrder()->setCustomerNoteNotify(!empty($creditmemoData['send_email']));

                $doOffline = isset($creditmemoData['do_offline']) ? (bool)$creditmemoData['do_offline'] : false;
                $this->creditmemoService->refund($creditMemo, $doOffline);

                // Clean registry to process possible next creditmemo
                $this->registry->unregister('current_creditmemo');
            }
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Api\Data\OrderItemInterface[] $orderItems
     */
    private function cancelOrderItems(\Magento\Sales\Api\Data\OrderInterface $order, array $orderItems = []): void
    {// if not empty $orderItems
        foreach ($orderItems as $orderItemId => $orderItem) {
            $qtyToCancel = $orderItem->getQtyOrdered() - $orderItem->getQtyCanceled() - $orderItem->getQtyRefunded();
            $orderItem->setQtyCanceled($orderItem->getQtyCanceled() + $qtyToCancel);
        }

        $databaseTransaction = $this->transactionFactory->create();
        $databaseTransaction->addObject($order)->save();
    }

    /**
     * @return void
     * @throws \Exception
     * @throws LocalizedException
     */
    protected function createInvoiceForOrder(\Magento\Sales\Api\Data\OrderInterface $order = null): void
    {
        $order = $order ?? $this->getOrder();
        if (!$order instanceof Order) {
            throw new \MageWorx\OrderEditor\Exception\CriticalWorkflowException(
                __('OrderEditor model of the order expected, %s provided', get_class($order))
            );
        }

        if (!$this->isNeedToCreateInvoiceForTheOrder($order)) {
            return;
        }

        $order->setState(OriginalOrder::STATE_PROCESSING);
        $this->orderRepository->save($order);

        if ($order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            if (empty($invoice)) {
                throw new LocalizedException(__('Can not create invoice'));
            }

            /**
             * We must add shipping amount manually, because magento totals collector drops any calculation in case
             * when at least one previous invoice is not canceled and has shipping amount invoiced.
             *
             * @see \Magento\Sales\Model\Order\Invoice\Total\Shipping::collect() lines 31-33 .
             */
            $changesInShipping = $order->getChangesInShipping();
            if (!empty($changesInShipping['base_amount']) && $changesInShipping['base_amount'] > 0) {
                $baseShippingAmount = $changesInShipping['base_amount'];
                $shippingAmount     = $order->getBaseToOrderRate() * $baseShippingAmount;

                $invoice->setShippingAmount($shippingAmount);
                $invoice->setBaseShippingAmount($baseShippingAmount);
                $invoice->setShippingInclTax($order->getBaseToOrderRate() * $changesInShipping['base_amount_incl_tax']);
                $invoice->setBaseShippingInclTax($changesInShipping['base_amount_incl_tax']);

                $invoice->setGrandTotal($invoice->getGrandTotal() + $shippingAmount);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseShippingAmount);
            }

            // Do not create an empty invoice
            if ($this->isEmptyInvoice($invoice)) {
                return;
            }

            $invoice->setRequestedCaptureCase(OriginalInvoice::CAPTURE_ONLINE);
            $invoice->register();

            // Update amount invoiced in each changed item to prevent errors with subsequent refunds
            $this->processOrderItemsInvoicedAmounts($order);

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

            $this->orderRepository->save($order);
        }
    }

    /**
     * Check each item in the order which has been changed and update corresponding totals (invoiced amounts).
     * It is necessary to prevent errors in subsequent creditmemos.
     *
     * @param OrderInterface $order
     */
    private function processOrderItemsInvoicedAmounts(OrderInterface $order): void
    {
        $changesInAmount = $order->getChangesInAmounts();
        if (!empty($changesInAmount)) {
            // Changes will be saved with the order save
            foreach ($changesInAmount as $itemId => $itemChanges) {
                $orderItem = $order->getItemById($itemId);

                $invoicedBaseAmount    = (float)$orderItem->getBaseRowInvoiced(
                ); // Get total invoiced from the beginning of the order
                $newBaseAmount         = (float)$orderItem->getBaseRowTotalInclTax(); // Get actual row total for now
                $baseAmountRefunded    = (float)$orderItem->getBaseAmountRefunded(
                ); // Get actual row total refunded for now
                $baseRealTotalInvoiced = $invoicedBaseAmount - $baseAmountRefunded; // Calculate real invoiced total (invoiced minus refunded for now) for now

                if ($baseRealTotalInvoiced < $newBaseAmount) { // We must create new creditmemo only when total real invoiced amount is smaller than new base amount
                    $orderItem->setBaseRowInvoiced((float)$orderItem->getBaseRowTotal())
                              ->setRowInvoiced((float)$orderItem->getRowTotal())
                              ->setBaseDiscountInvoiced($orderItem->getBaseDiscountAmount())
                              ->setDiscountInvoiced($orderItem->getDiscountAmount())
                              ->setBaseTaxInvoiced($orderItem->getBaseTaxAmount())
                              ->setTaxInvoiced($orderItem->getTaxAmount())
                              ->setGwBasePriceInvoiced($orderItem->getGwBasePrice())
                              ->setGwPriceInvoiced($orderItem->getGwPrice())
                              ->setGwBaseTaxAmountInvoiced($orderItem->getGwBaseTaxAmount())
                              ->setGwTaxAmountInvoiced($orderItem->getGwTaxAmount());
                }
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
     * Check is order must be invoiced
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return bool
     */
    public function isNeedToCreateInvoiceForTheOrder(\Magento\Sales\Api\Data\OrderInterface $order): bool
    {
        return $order->getTotalInvoiced() > 0 && !$order->getData('invoice_locked');
    }

    /**
     * Check is order must be processed (invoiced, refunded, shipped)
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isNeedToProcessOrder(\Magento\Sales\Api\Data\OrderInterface $order): bool
    {
        return $order->getTotalInvoiced() > 0 || $order->getData('amstorecredit_invoiced_base_amount') > 0;
    }

    /**
     * Check is empty invoice
     *
     * @param InvoiceInterface $invoice
     * @return bool
     */
    public function isEmptyInvoice(InvoiceInterface $invoice): bool
    {
        if ($invoice->getBaseGrandTotal() < 0.00001 && count($invoice->getAllItems()) < 1) {
            return true;
        }

        return false;
    }
}
