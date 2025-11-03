<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Api\Search\Document;
use MageWorx\OrderEditor\Api\Data\OrderManager\OrderInfoInterface;

class OrderInfo extends Document implements OrderInfoInterface
{

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->_get(static::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $value): OrderInfoInterface
    {
        return $this->setData(static::CREATED_AT, $value);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): ?string
    {
        return $this->_get(static::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $value): OrderInfoInterface
    {
        return $this->setData(static::STATUS, $value);
    }

    /**
     * @inheritDoc
     */
    public function getState(): ?string
    {
        return $this->_get(static::STATE);
    }

    /**
     * @inheritDoc
     */
    public function setState(string $value): OrderInfoInterface
    {
        return $this->setData(static::STATE, $value);
    }
}
