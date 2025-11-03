<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

use Magento\Framework\Api\CustomAttributesDataInterface;

interface CustomerInfoInterface extends CustomAttributesDataInterface
{
    /**
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * @param int $value
     * @return CustomerInfoInterface
     */
    public function setCustomerId(int $value): CustomerInfoInterface;

    /**
     * @return string|null
     */
    public function getCustomerEmail(): ?string;

    /**
     * @param string $value
     * @return CustomerInfoInterface
     */
    public function setCustomerEmail(string $value): CustomerInfoInterface;

    /**
     * @return int|null
     */
    public function getCustomerGroup(): ?int;

    /**
     * @param int $value
     * @return CustomerInfoInterface
     */
    public function setCustomerGroup(int $value): CustomerInfoInterface;

    /**
     * @return string|null
     */
    public function getCustomerFirstname(): ?string;

    /**
     * @param string $value
     * @return CustomerInfoInterface
     */
    public function setCustomerFirstname(string $value): CustomerInfoInterface;

    /**
     * @return string|null
     */
    public function getCustomerLastname(): ?string;

    /**
     * @param string $value
     * @return CustomerInfoInterface
     */
    public function setCustomerLastname(string $value): CustomerInfoInterface;
}
