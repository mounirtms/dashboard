<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as OriginalOrder;
use MageWorx\OrderEditor\Model\Config\Source\Shipments\UpdateMode;
use MageWorx\OrderEditor\Helper\Data as Helper;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Framework\DB\TransactionFactory;

/**
 * Class ShipmentManager
 *
 * Add or remove shipments for the order after edit
 */
class ShipmentManager implements \MageWorx\OrderEditor\Api\ShipmentManagerInterface
{
    /**
     * @var Helper
     */
    private $helperData;

    /**
     * @var ShipmentLoader
     */
    private $shipmentLoader;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $orderPaymentRepository;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $origOrderRepository;

    /**
     * ShipmentManager constructor.
     *
     * @param Helper $helperData
     * @param ShipmentLoader $shipmentLoader
     * @param TransactionFactory $transactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $origOrderRepository
     */
    public function __construct(
        Helper $helperData,
        ShipmentLoader $shipmentLoader,
        TransactionFactory $transactionFactory,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $orderItemRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $origOrderRepository
    ) {
        $this->helperData             = $helperData;
        $this->shipmentLoader         = $shipmentLoader;
        $this->transactionFactory     = $transactionFactory;
        $this->orderRepository        = $orderRepository;
        $this->orderItemRepository    = $orderItemRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->origOrderRepository    = $origOrderRepository;
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function updateShipmentsOnOrderEdit(
        \MageWorx\OrderEditor\Model\Order $order
    ): \MageWorx\OrderEditor\Model\Order {
        if ($order->hasShipments()) {
            switch ($this->helperData->getUpdateShipmentMode()) {
                case UpdateMode::MODE_UPDATE_ADD:
                    if (!$this->isOrderTotalIncreased($order)) {
                        $this->removeAllShipments($order);
                    }
                    $this->createShipmentForOrder($order);
                    break;
                case UpdateMode::MODE_UPDATE_REBUILD:
                    $this->removeAllShipments($order);
                    $this->createShipmentForOrder($order);
                    break;
                case UpdateMode::MODE_UPDATE_NOTHING:
                    break; // Do nothing
            }
        }

        return $order;
    }

    /**
     * @param \MageWorx\OrderEditor\Model\Order $order
     * @return void
     * @throws LocalizedException
     */
    private function removeAllShipments(\MageWorx\OrderEditor\Model\Order $order): void
    {
        $shipments = $order->getShipmentsCollection();
        foreach ($shipments as $shipment) {
            $shipment->delete();
        }

        $orderItems = $order->getItems();
        foreach ($orderItems as $orderItem) {
            $orderItem->setQtyShipped(0);
            $this->orderItemRepository->save($orderItem);
        }

        $state = OriginalOrder::STATE_PROCESSING;

        $order->setState($state);
        $this->orderRepository->save($order);

        // Need to reload items in order after update
        $order->setItems(null) && $order->getItems();

        $payment = $order->getPayment();
        $payment->setShippingCaptured(0)
                ->setBaseShippingCaptured(0)
                ->setShippingRefunded(0)
                ->setBaseShippingRefunded(0);

        $this->orderPaymentRepository->save($payment);
    }

    /**
     * @param \MageWorx\OrderEditor\Model\Order $order
     * @return void
     * @throws LocalizedException
     */
    protected function createShipmentForOrder(\MageWorx\OrderEditor\Model\Order $order): void
    {
        if ($order->canShip()) {
            /**
             * We must reload order in original repository registry, because it used by magento in shipment loader
             * and has old (not changed) items with incorrect data.
             */
            $this->origOrderRepository->save($order);
            $this->shipmentLoader->setOrderId($order->getId());
            $shipment = $this->shipmentLoader->load();
            if (!$shipment) {
                throw new LocalizedException(__('Can not create shipment'));
            }

            $shipment->register();

            $transaction = $this->transactionFactory->create();
            $transaction->addObject($shipment)->addObject($shipment->getOrder())->save();
        }
    }

    /**
     * @param \MageWorx\OrderEditor\Model\Order $order
     * @return bool
     */
    private function isOrderTotalIncreased(\MageWorx\OrderEditor\Model\Order $order): bool
    {
        return ($order->hasItemsWithIncreasedQty() || $order->hasAddedItems())
            && (!$order->hasItemsWithDecreasedQty() && !$order->hasRemovedItems());
    }
}
