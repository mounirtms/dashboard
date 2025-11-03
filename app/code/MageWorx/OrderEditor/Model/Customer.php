<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\Data\LogMessageInterfaceFactory;

class Customer extends \Magento\Framework\DataObject implements \MageWorx\OrderEditor\Api\CustomerInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    protected $customerGroupRepository;

    /**
     * @var array
     */
    protected $dataMap = [
        'customer_group_id'  => 'group_id',
        'customer_firstname' => 'customer_firstname',
        'customer_lastname'  => 'customer_lastname',
        'customer_email'     => 'email',
        'customer_id'        => 'customer_id',
    ];

    /**
     * Labels for logging
     *
     * @var string[]
     */
    protected $fieldLabels = [
        'customer_group_id'  => 'Customer Group',
        'customer_firstname' => 'Customer First Name',
        'customer_lastname'  => 'Customer Last Name',
        'customer_email'     => 'Email',
        'customer_id'        => 'Customer ID',
    ];

    /**
     * @var \Magento\Framework\DataObject\Factory
     */
    protected $dataObjectFactory;

    /**
     * @var LogMessageInterfaceFactory
     */
    protected $logMessageFactory;

    /**
     * @var EventManager
     */
    protected $_eventManager;

    /**
     * Customer constructor.
     *
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\DataObject\Factory $dataObjectFactory
     * @param LogMessageInterfaceFactory $logMessageFactory
     * @param EventManager $eventManager
     * @param array $data
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository,
        \Magento\Framework\DataObject\Factory $dataObjectFactory,
        LogMessageInterfaceFactory $logMessageFactory,
        EventManager $eventManager,
        array $data = []
    ) {
        parent::__construct($data);
        $this->orderRepository         = $orderRepository;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->dataObjectFactory       = $dataObjectFactory;
        $this->logMessageFactory       = $logMessageFactory;
        $this->_eventManager           = $eventManager;
    }

    /**
     * Performs update of the customer's records in the corresponding order
     *
     * @return \MageWorx\OrderEditor\Api\CustomerInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order           = $this->getOrder();
        $customerData    = $this->getCustomerData();
        $orderHasChanges = false;
        $changeLog       = [];
        $isOldCustomerId = ((int)$order->getCustomerId() === $customerData->getData('customer_id')
            || empty($customerData->getData('customer_id')));

        foreach ($this->getDataMap() as $field => $dep) {
            $newData = $customerData->getData($dep);
            if ($dep == 'customer_id' && !$newData) {
                continue;
            } elseif ($dep == 'customer_id' && $newData) {
                $order->setCustomerIsGuest(false);
            }

            if ($newData !== null) {
                if ($order->getData($field) != $newData && $isOldCustomerId) {
                    if ($dep === 'group_id') {
                        $oldGroupName = $this->customerGroupRepository->getById($order->getCustomerGroupId())->getCode(
                        );
                        $newGroupName = $this->customerGroupRepository->getById($newData)->getCode();
                        $logMessage   = __(
                            'Customer Group has been changed from %1 to %2',
                            $oldGroupName,
                            $newGroupName
                        );
                    } else {
                        $logFieldName = !empty($this->fieldLabels[$field]) ?
                            __($this->fieldLabels[$field]) :
                            ucwords(str_replace('_', ' ', $field));
                        $logMessage   = __(
                            '%1 has been changed from %2 to %3',
                            $logFieldName,
                            $order->getData($field),
                            $newData
                        );
                    }
                    $changeLog[] = $this->logMessageFactory->create(['message' => $logMessage]);
                }

                $order->setData($field, $newData);
                $orderHasChanges = true;
            }
        }

        if ($orderHasChanges) {
            $this->orderRepository->save($order);
            $this->_eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::MESSAGES_KEY => $changeLog
                ]
            );
        }

        $this->_eventManager->dispatch(
            'mageworx_order_updated',
            [
                'action' => \MageWorx\OrderEditor\Api\WebhookProcessorInterface::ACTION_UPDATE_ORDER_CUSTOMER_DATA,
                'object' => $order,
                'initial_params' => $customerData->getData()
            ]
        );

        $this->_eventManager->dispatch(
            'mageworx_save_logged_changes_for_order',
            [
                'order_id'        => $order->getId(),
                'notify_customer' => false
            ]
        );

        return $this;
    }

    /**
     * Set the corresponding order id (which will be modified)
     *
     * @param int $orderId
     * @return \MageWorx\OrderEditor\Api\CustomerInterface
     */
    public function setOrderId($orderId)
    {
        return $this->setData('order_id', $orderId);
    }

    /**
     * Get the corresponding order id (which will be modified)
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->getData('order_id');
    }

    /**
     * Set a customer data which will replace the order's customer records during update
     *
     * @param array $data
     * @return \MageWorx\OrderEditor\Api\CustomerInterface
     */
    public function setCustomerData(array $data = [])
    {
        $data = $this->dataObjectFactory->create($data);

        return $this->setData('customer_data', $data);
    }

    /**
     * Get the customer data which will replace the order's customer records during update
     *
     * @return \Magento\Framework\DataObject
     */
    public function getCustomerData()
    {
        $data = $this->getData('customer_data') ? $this->getData('customer_data') : [];
        if (!$data instanceof \Magento\Framework\DataObject) {
            $data = $this->dataObjectFactory->create($data);
        }

        return $data;
    }

    /**
     * Set the customer id. In case it is exists, the corresponding order's customer id will be replaced to this one.
     *
     * @param int|null $customerId
     * @return \MageWorx\OrderEditor\Api\CustomerInterface
     */
    public function setCustomerId($customerId = null)
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Get the id of a customer, which has been associated with a current order.
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws LocalizedException
     */
    private function getOrder()
    {
        if (!$this->getOrderId()) {
            throw new LocalizedException(__('To perform update the order id should be specified'));
        }

        $orderId = $this->getOrderId();
        $order   = $this->orderRepository->get($orderId);

        return $order;
    }

    /**
     * Get data map which using to set form data to the corresponding order field
     *
     * @return array
     */
    private function getDataMap()
    {
        return $this->dataMap;
    }
}
