<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Api\Search\Document;
use MageWorx\OrderEditor\Api\Data\OrderManager\TaxRateDataInterface;

class TaxRateData extends Document implements TaxRateDataInterface
{
    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->_get(static::CODE);
    }

    /**
     * @inheritDoc
     */
    public function setCode(string $value): TaxRateDataInterface
    {
        return $this->setData(static::CODE, $value);
    }

    /**
     * @inheritDoc
     */
    public function getPercent(): float
    {
        return $this->_get(static::PERCENT);
    }

    /**
     * @inheritDoc
     */
    public function setPercent(float $value): TaxRateDataInterface
    {
        return $this->setData(static::PERCENT, $value);
    }

    /**
     * Convert the rate to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            static::CODE => $this->getCode(),
            static::PERCENT => $this->getPercent()
        ];
    }
}
