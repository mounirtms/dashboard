<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Order;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use MageWorx\OrderEditor\Api\OrderItemRepositoryInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\SalesProcessorInterface;
use MageWorx\OrderEditor\Api\ShipmentManagerInterface;
use MageWorx\OrderEditor\Helper\Data as Helper;
use MageWorx\OrderEditor\Model\Order;
use MageWorx\OrderEditor\Model\Order as OrderEditorOrder;

abstract class SalesProcessorAbstract implements SalesProcessorInterface
{
    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var Helper
     */
    protected $helperData;

    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderItemRepositoryInterface
     */
    protected $oeOrderItemRepository;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    protected $orderPaymentRepository;

    /**
     * @var ShipmentManagerInterface
     */
    protected $shipmentManager;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var OrderEditorOrder
     */
    protected $order;

    /**
     * Sales constructor.
     *
     * @param Helper $helperData
     * @param TransactionFactory $transactionFactory
     * @param HttpRequest $request
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $oeOrderItemRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param ShipmentManagerInterface $shipmentManager
     * @param EventManager $eventManager
     */
    public function __construct(
        Helper $helperData,
        TransactionFactory $transactionFactory,
        HttpRequest $request,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $oeOrderItemRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        ShipmentManagerInterface $shipmentManager,
        EventManager $eventManager
    ) {
        $this->transactionFactory     = $transactionFactory;
        $this->helperData             = $helperData;
        $this->request                = $request;
        $this->orderRepository        = $orderRepository;
        $this->oeOrderItemRepository  = $oeOrderItemRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->shipmentManager        = $shipmentManager;
        $this->eventManager           = $eventManager;
    }

    /**
     * @param OrderEditorOrder $order
     * @return SalesProcessorInterface
     */
    public function setOrder(OrderEditorOrder $order): SalesProcessorInterface
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return OrderEditorOrder|null
     */
    public function getOrder(): ?OrderEditorOrder
    {
        return $this->order;
    }

    /**
     * Update data in the corresponding Quote
     *
     * @param OrderEditorOrder $order
     * @throws \Exception
     */
    protected function syncQuoteOfTheOrder(Order $order): void
    {
        $order->syncQuote();
    }

    /**
     * @return bool
     */
    protected function isOrderTotalIncreased(): bool
    {
        $order = $this->getOrder();

        return ($order->hasItemsWithIncreasedQty() || $order->hasAddedItems())
            && (!$order->hasItemsWithDecreasedQty() && !$order->hasRemovedItems());
    }

    /**
     * Check: is nothing was changed in the order?
     *
     * @param OrderEditorOrder $order
     * @return bool
     */
    protected function isOrderHasNoChanges(Order $order): bool
    {
        return !$order->isTotalWasChanged()
            && !$order->hasChangesInAmounts()
            && !$order->hasItemsWithIncreasedQty()
            && !$order->hasAddedItems()
            && !$order->hasItemsWithDecreasedQty()
            && !$order->hasRemovedItems();
    }
}
