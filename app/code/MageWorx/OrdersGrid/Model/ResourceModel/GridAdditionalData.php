<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Payment\Ui\Component\Listing\Column\Method\Options as PaymentMethodsSource;
use Magento\Sales\Ui\Component\Listing\Column\Status\Options as OrderStatusesSource;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;
use MageWorx\OrdersGrid\Model\Config\Source\ShippingMethods as ShippingMethodsSource;

class GridAdditionalData extends AbstractDb
{
    /**
     * @var ShippingMethodsSource
     */
    protected $shippingMethodsSource;

    /**
     * @var PaymentMethodsSource
     */
    protected $paymentMethodsSource;

    /**
     * @var OrderStatusesSource
     */
    protected $orderStatusesSource;

    /**
     * @var array
     */
    protected $shippingMethodsCodes = [];

    /**
     * @var array
     */
    protected $paymentMethodsCodes = [];

    /**
     * @var array
     */
    protected $orderStatuses = [];

    /**
     * Comma separated fields
     *
     * @var array
     */
    protected $commaSeparatedFields = [
        GridAdditionalDataInterface::KEY_SHIPPING_METHODS,
        GridAdditionalDataInterface::KEY_PAYMENT_METHODS,
        GridAdditionalDataInterface::KEY_ORDER_STATUSES
    ];

    public function __construct(
        Context               $context,
        ShippingMethodsSource $shippingMethodsSource,
        PaymentMethodsSource  $paymentMethodsSource,
        OrderStatusesSource   $orderStatusesSource,
                              $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->shippingMethodsSource = $shippingMethodsSource;
        $this->paymentMethodsSource  = $paymentMethodsSource;
        $this->orderStatusesSource   = $orderStatusesSource;
    }

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            GridAdditionalDataInterface::TABLE_NAME,
            GridAdditionalDataInterface::KEY_ID
        );
    }

    /**
     * @param AbstractModel $object
     * @return AbstractDb
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _afterLoad(AbstractModel $object)
    {
        /** @var GridAdditionalDataInterface $object */
        $this->unpackCommaSeparatedFields($object);

        return parent::_afterLoad($object);
    }

    /**
     * @param AbstractModel $object
     * @return GridAdditionalData
     */
    protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        return parent::_afterSave($object);
    }

    /**
     * Perform actions before object save
     *
     * @param AbstractModel $object
     * @return AbstractDb
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $this->packCommaSeparatedFields($object);

        return parent::_beforeSave($object);
    }

    /**
     * Unpack comma-separated fields in the object
     *
     * @param AbstractModel $object
     */
    private function unpackCommaSeparatedFields(AbstractModel $object)
    {
        foreach ($this->commaSeparatedFields as $field) {
            if (is_array($object->getData($field))) {
                $object->setData($field, explode(',', $object->getData($field)));
            }
        }
    }

    /**
     * Pack comma-separated fields in the object
     *
     * @param AbstractModel $object
     */
    private function packCommaSeparatedFields(AbstractModel $object)
    {
        foreach ($this->commaSeparatedFields as $field) {
            if (is_array($object->getData($field))) {
                $object->setData($field, implode(',', $object->getData($field)));
            }
        }
    }

    /**
     * @return string[]
     */
    public function getAllOrderStatuses(): array
    {
        if (empty($this->orderStatuses)) {
            $statuses = $this->orderStatusesSource->toOptionArray();
            $values = [];
            foreach ($statuses as $status) {
                $values[] = $status['value'];
            }

            $this->orderStatuses = $values;
        }

        return $this->orderStatuses;
    }

    /**
     * @return string[]
     */
    public function getAllShippingMethods(): array
    {
        if (empty($this->shippingMethodsCodes)) {
            $carriers    = $this->shippingMethodsSource->toOptionArray();
            $methodCodes = [];
            foreach ($carriers as $carrier) {
                if (empty($carrier['value'])) {
                    continue;
                }

                if (is_array($carrier['value'])) {
                    foreach ($carrier['value'] as $method) {
                        $methodCodes[] = $method['value'];
                    }
                } else {
                    $methodCodes[] = $carrier['value'];
                }
            }

            $this->shippingMethodsCodes = $methodCodes;
        }

        return $this->shippingMethodsCodes;
    }

    /**
     * @return string[]
     */
    public function getAllPaymentMethods(): array
    {
        if (empty($this->paymentMethodsCodes)) {
            $paymentMethods = $this->paymentMethodsSource->toOptionArray();
            $values = [];
            foreach ($paymentMethods as $paymentMethod) {
                $values[] = $paymentMethod['value'];
            }

            $this->paymentMethodsCodes = $values;
        }

        return $this->paymentMethodsCodes;
    }

    /**
     * @return void
     */
    public function rebuildIndexTable(): void
    {
        $connection      = $this->getConnection();
        $orderStatuses   = $this->getAllOrderStatuses();
        $shippingMethods = $this->getAllShippingMethods();
        $paymentMethods  = $this->getAllPaymentMethods();

        $keys = [];
        foreach ($orderStatuses as $orderStatus) {
            foreach ($paymentMethods as $paymentMethod) {
                foreach ($shippingMethods as $shippingMethod) {
                    $keys[] = [
                        'shipping_method' => $shippingMethod,
                        'payment_method'  => $paymentMethod,
                        'order_status'    => $orderStatus
                    ];
                }
            }
        }

        foreach ($keys as $keyIndex => $key) {
            $ruleId = $this->getRuleIdByKeys($key['shipping_method'], $key['payment_method'], $key['order_status']);
            if ($ruleId) {
                $keys[$keyIndex]['rule_id'] = $ruleId;
            } else {
                unset($keys[$keyIndex]);
            }
        }

        $connection->truncateTable($this->getTable('mageworx_ordersgrid_additional_data_index'));
        if (!empty($keys)) {
            $connection->insertOnDuplicate(
                $this->getTable('mageworx_ordersgrid_additional_data_index'),
                $keys,
                [
                    'shipping_method',
                    'payment_method',
                    'order_status',
                    'rule_id'
                ]
            );
        }
    }

    /**
     * @param string $shippingMethod
     * @param string $paymentMethod
     * @param string $orderStatus
     * @return int|null
     */
    public function getRuleIdByKeys(string $shippingMethod, string $paymentMethod, string $orderStatus): ?int
    {
        $connection = $this->getConnection();

        $select = $connection->select()
                             ->from($this->getTable('mageworx_ordersgrid_additional_data'), ['entity_id'])
                             ->where(
                                 'is_active = ?',
                                 1
                             )
                             ->where(
                                 'FIND_IN_SET(?, order_statuses) OR order_statuses IS NULL',
                                 $orderStatus
                             )
                             ->where(
                                 'FIND_IN_SET(?, payment_methods) OR payment_methods IS NULL',
                                 $paymentMethod
                             )
                             ->where(
                                 'FIND_IN_SET(?, shipping_methods) OR shipping_methods IS NULL',
                                 $shippingMethod
                             )
                             ->order('sort_order')
                             ->limit(1);

        $data = $connection->fetchOne($select);

        return $data ? (int)$data : null;
    }
}
