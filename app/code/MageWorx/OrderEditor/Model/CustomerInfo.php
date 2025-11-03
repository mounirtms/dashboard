<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Api\Search\Document;
use MageWorx\OrderEditor\Api\Data\OrderManager\CustomerInfoInterface;

class CustomerInfo extends Document implements CustomerInfoInterface
{
    /**
     * @inheritDoc
     */
    public function getCustomerId(): ?int
    {
        return $this->_get('customer_id');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(int $value): CustomerInfoInterface
    {
        return $this->setData('customer_id', $value);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerGroup(): ?int
    {
        return $this->_get('group_id');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerGroup(int $value): CustomerInfoInterface
    {
        return $this->setData('group_id', $value);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerFirstname(): ?string
    {
        return $this->_get('customer_firstname');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerFirstname(string $value): CustomerInfoInterface
    {
        return $this->setData('customer_firstname', $value);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerLastname(): ?string
    {
        return $this->_get('customer_lastname');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerLastname(string $value): CustomerInfoInterface
    {
        return $this->setData('customer_lastname', $value);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerEmail(): ?string
    {
        return $this->_get('email');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerEmail(string $value): CustomerInfoInterface
    {
        return $this->setData('email', $value);
    }
}
