<?php
/**
 * Copyright © 2017 MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersBase\Model;

use Magento\Framework\Model\AbstractModel;
use MageWorx\OrdersBase\Api\Data\DeviceDataInterface;

class DeviceData extends AbstractModel implements DeviceDataInterface
{
    const TABLE_NAME = 'mageworx_order_base_device_data';

    /**
     * @var \MageWorx\OrdersBase\Api\DeviceTypeParserInterface
     */
    private $deviceTypeParser;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \MageWorx\OrdersBase\Api\DeviceTypeParserInterface $deviceDataParser
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \MageWorx\OrdersBase\Api\DeviceTypeParserInterface $deviceDataParser,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->deviceTypeParser = $deviceDataParser;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Set resource model and Id field name
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('MageWorx\OrdersBase\Model\ResourceModel\DeviceData');
        $this->setIdFieldName('entity_id');
    }

    /**
     * Set used device code
     *
     * @param string|null $name
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setDeviceName(string $name = null): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
    {
        return $this->setData('device_name', $name);
    }

    /**
     * Set used area code from where the order was placed
     *
     * @param string|null $name
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setAreaName(string $name = null): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
    {
        return $this->setData('area_name', $name);
    }

    /**
     * Get linked order id
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return (int)$this->getData('order_id');
    }

    /**
     * Set linked order id
     *
     * @param int|null $id
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setOrderId(int $id = null): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
    {
        return $this->setData('order_id', $id);
    }

    /**
     * Return value.
     *
     * @return array
     */
    public function getValue(): array
    {
        return $this->getData() ?? [];
    }

    /**
     * Set value.
     *
     * @param array $value
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setValue(array $value = []): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
    {
        return $this->addData($value);
    }

    /**
     * Get human readable device name using parser
     *
     * @return string
     */
    public function getDeviceName(): string
    {
        $name = $this->getData('device_name');
        if (!$name) {
            return 'n/a';
        }

        return ucwords($name);
    }

    /**
     * Get human readable area name (from where order was placed)
     *
     * @return string|null
     */
    public function getAreaName(): ?string
    {
        $area = $this->getData('area_name');
        if (!$area) {
            return 'n/a';
        }

        return ucwords($area);
    }
}
