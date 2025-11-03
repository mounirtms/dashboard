<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\OrderManager;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterface;
use Magento\Framework\Api\AttributeInterfaceFactory;
use MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterfaceFactory;
use MageWorx\OrderEditor\Api\OrderManager\OrderInfoManagerInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\Data\LogMessageInterfaceFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Class OrderInfoManager
 *
 * Manage order info: created at date, status, state
 *
 * @api
 */
class OrderInfoManager implements OrderInfoManagerInterface
{
    /**
     * @var OrderInfoInterfaceFactory
     */
    private $orderInfoFactory;

    /**
     * @var AttributeInterfaceFactory
     */
    private $customAttributesFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var LogMessageInterfaceFactory
     */
    private $logMessageFactory;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * CustomerInfoManager constructor.
     *
     * @param OrderInfoInterfaceFactory $orderInfoFactory
     * @param AttributeInterfaceFactory $customAttributesFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param TimezoneInterface $timezone
     * @param LogMessageInterfaceFactory $logMessageFactory
     * @param EventManager $eventManager
     */
    public function __construct(
        OrderInfoInterfaceFactory $orderInfoFactory,
        AttributeInterfaceFactory $customAttributesFactory,
        OrderRepositoryInterface $orderRepository,
        TimezoneInterface $timezone,
        LogMessageInterfaceFactory $logMessageFactory,
        EventManager $eventManager
    ) {
        $this->orderInfoFactory        = $orderInfoFactory;
        $this->customAttributesFactory = $customAttributesFactory;
        $this->orderRepository         = $orderRepository;
        $this->timezone                = $timezone;
        $this->logMessageFactory       = $logMessageFactory;
        $this->eventManager            = $eventManager;
    }

    /**
     * @inheritDoc
     */
    public function getOrderInfoByOrderId(int $orderId): OrderInfoInterface
    {
        /** @var OrderInfoInterface $orderInfo */
        $orderInfo = $this->orderInfoFactory->create();
        $order     = $this->orderRepository->getById($orderId);

        $orderInfo->setCreatedAt($order->getCreatedAt());
        $orderInfo->setStatus($order->getStatus());
        $orderInfo->setState($order->getState());

        return $orderInfo;
    }

    /**
     * @inheritDoc
     */
    public function updateOrderInfoByOrderId(int $orderId, OrderInfoInterface $orderInfo): void
    {
        $order         = $this->orderRepository->getById($orderId);
        $orderInfoData = $this->extractOrderInfoData($orderInfo);
        $this->logChanges($order, $orderInfoData);
        $order->addData($orderInfoData);
        $this->orderRepository->save($order);

        $this->eventManager->dispatch(
            'mageworx_order_updated',
            [
                'action' => \MageWorx\OrderEditor\Api\WebhookProcessorInterface::ACTION_UPDATE_ORDER_INFO,
                'object' => $order,
                'initial_params' => $orderInfoData
            ]
        );

        $this->eventManager->dispatch(
            'mageworx_save_logged_changes_for_order',
            [
                'order_id'        => $order->getId(),
                'notify_customer' => false
            ]
        );
    }

    /**
     * @param OrderInfoInterface $orderInfo
     * @return array
     */
    private function extractOrderInfoData(OrderInfoInterface $orderInfo): array
    {
        $data = [
            'created_at' => $orderInfo->getCreatedAt(),
            'status'     => $orderInfo->getStatus(),
            'state'      => $orderInfo->getState()
        ];

        $customAttributes = $orderInfo->getCustomAttributes();
        if (!empty($customAttributes)) {
            foreach ($customAttributes as $attribute) {
                $data[$attribute->getAttributeCode()] = $attribute->getValue();
            }
        }

        $data = array_filter($data);

        return $data;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param array $orderInfoData
     */
    private function logChanges(\Magento\Sales\Api\Data\OrderInterface $order, array $orderInfoData): void
    {
        $changeLog = [];
        if (!empty($orderInfoData['created_at']) && $orderInfoData['created_at'] != $order->getCreatedAt()) {
            $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Order Date has been changed from %1 to %2',
                        $order->getCreatedAt(),
                        $orderInfoData['created_at']
                    )
                ]
            );
        }

        if (!empty($orderInfoData['status']) && $orderInfoData['status'] != $order->getStatus()) {
            $changeLog[] = $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Order Status has been changed from %1 to %2',
                        ucwords($order->getStatus()),
                        ucwords($orderInfoData['status'])
                    )
                ]
            );
        }

        if (!empty($orderInfoData['state']) && $orderInfoData['state'] != $order->getState()) {
            $changeLog[] = $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Order State has been changed from %1 to %2',
                        ucwords($order->getState()),
                        ucwords($orderInfoData['state'])
                    )
                ]
            );
        }

        if (!empty($changeLog)) {
            $this->eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::MESSAGES_KEY => $changeLog
                ]
            );
        }
    }
}
