<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\OrderManager;

use Magento\Framework\Event\Manager as EventManager;
use Magento\Sales\Api\Data\OrderAddressInterface as ShippingAddressDataInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use MageWorx\OrderEditor\Api\OrderManager\ShippingAddressManagerInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;

/**
 * Class ShippingAddressManager
 *
 * Manage shipping address of the order
 *
 * @api
 */
class ShippingAddressManager implements ShippingAddressManagerInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderAddressRepositoryInterface
     */
    private $orderAddressRepository;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * ShippingAddressManager constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderAddressRepositoryInterface $orderAddressRepository
     * @param EventManager $eventManager
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderAddressRepositoryInterface $orderAddressRepository,
        EventManager $eventManager
    ) {
        $this->orderRepository        = $orderRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->eventManager           = $eventManager;
    }

    /**
     * @inheritDoc
     */
    public function getShippingAddressByOrderId(int $orderId): ShippingAddressDataInterface
    {
        $order           = $this->orderRepository->getById($orderId);
        $shippingAddress = $order->getShippingAddress();

        return $shippingAddress;
    }

    /**
     * @inheritDoc
     */
    public function updateShippingAddressDataByOrderId(
        int $orderId,
        ShippingAddressDataInterface $shippingAddressData
    ): void {
        $order = $this->orderRepository->getById($orderId);

        /** Set default values */
        /** @var \Magento\Sales\Model\Order\Address $shippingAddressData */
        $shippingAddressData->setAddressType('shipping')
                            ->setParentId($order->getId());

        /** Update data in original address entity */
        /** @var \Magento\Sales\Model\Order\Address $shippingAddressOriginal */
        $shippingAddressOriginal = $order->getshippingAddress();
        $shippingAddressOriginal->addData($shippingAddressData->getData());

        $this->orderAddressRepository->save($shippingAddressOriginal);

        $order->setShippingAddress($shippingAddressData);
        $this->orderRepository->save($order);

        $this->eventManager->dispatch(
            'mageworx_order_updated',
            [
                'action' => \MageWorx\OrderEditor\Api\WebhookProcessorInterface::ACTION_UPDATE_ORDER_SHIPPING_ADDRESS,
                'object' => $order,
                'initial_params' => $shippingAddressData->getData()
            ]
        );
    }
}
