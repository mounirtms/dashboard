<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterface;
use Magento\Framework\Api\Search\Document;

class ShippingMethodData extends Document implements ShippingMethodDataInterface
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
    public function setCode(string $value): ShippingMethodDataInterface
    {
        return $this->setData(static::CODE, $value);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->_get(static::TITLE);
    }

    /**
     * @inheritDoc
     */
    public function setTitle(string $value): ShippingMethodDataInterface
    {
        return $this->setData(static::TITLE, $value);
    }

    /**
     * @inheritDoc
     */
    public function getPriceExclTax(): float
    {
        return $this->_get(static::PRICE_EXCL_TAX) ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function setPriceExclTax(float $value): ShippingMethodDataInterface
    {
        return $this->setData(static::PRICE_EXCL_TAX, $value);
    }

    /**
     * @inheritDoc
     */
    public function getPriceInclTax(): float
    {
        return $this->_get(static::PRICE_INCL_TAX) ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function setPriceInclTax(float $value): ShippingMethodDataInterface
    {
        return $this->setData(static::PRICE_INCL_TAX, $value);
    }

    /**
     * @inheritDoc
     */
    public function getTaxPercent(): float
    {
        return $this->_get(static::TAX_PERCENT) ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function setTaxPercent(float $value = 0): ShippingMethodDataInterface
    {
        return $this->setData(static::TAX_PERCENT, $value);
    }

    /**
     * @inheritDoc
     */
    public function getDiscountAmount(): float
    {
        return $this->_get(static::DISCOUNT_AMOUNT) ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function setDiscountAmount(float $value = 0): ShippingMethodDataInterface
    {
        return $this->setData(static::DISCOUNT_AMOUNT, $value);
    }

    /**
     * @inheritDoc
     */
    public function getTaxRates(): array
    {
        return $this->_get(static::TAX_RATES) ?? [];
    }

    /**
     * @inheritDoc
     */
    public function setTaxRates(array $value = []): ShippingMethodDataInterface
    {
        return $this->setData(static::TAX_RATES, $value);
    }
}
