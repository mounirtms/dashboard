<?php
/**
 * Copyright © 2017 MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersBase\Api\Data;

interface DeviceDataInterface
{
    /**
     * @return int|null
     */
    public function getEntityId();

    /**
     * Get linked order id
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Get human readable device name
     *
     * @return string
     */
    public function getDeviceName(): ?string;

    /**
     * Get human readable area name (from where order was placed)
     *
     * @return string|null
     */
    public function getAreaName(): ?string;

    /**
     * @param int|null $id
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setEntityId($id);

    /**
     * Set used device code
     *
     * @param string|null $name
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setDeviceName(string $name = null): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface;

    /**
     * Set used area code from where the order was placed
     *
     * @param string|null $name
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setAreaName(string $name = null): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface;

    /**
     * Set linked order id
     *
     * @param int|null $id
     * @return \MageWorx\OrdersBase\Api\Data\DeviceDataInterface
     */
    public function setOrderId(int $id = null): \MageWorx\OrdersBase\Api\Data\DeviceDataInterface;
}
