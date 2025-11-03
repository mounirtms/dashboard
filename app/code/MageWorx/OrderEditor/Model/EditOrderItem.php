<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use MageWorx\OrderEditor\Api\Data\OrderManager\EditOrderItemDataInterface;
use MageWorx\OrderEditor\Traits\AsArrayTrait;

class EditOrderItem
    extends \Magento\Framework\Api\Search\Document
    implements \MageWorx\OrderEditor\Api\Data\OrderManager\EditOrderItemDataInterface
{
    use AsArrayTrait;

    /**
     * @inheritDoc
     */
    public function getItemId(): string
    {
        return $this->_get(static::ITEM_ID);
    }

    /**
     * @inheritDoc
     */
    public function setItemId(string $id): EditOrderItemDataInterface
    {
        return $this->setData(static::ITEM_ID, $id);
    }

    /**
     * @inheritDoc
     */
    public function getPrice(): ?float
    {
        return $this->_get(static::PRICE);
    }

    /**
     * @inheritDoc
     */
    public function getPriceInclTax(): ?float
    {
        return $this->_get(static::PRICE_INCL_TAX);
    }

    /**
     * @inheritDoc
     */
    public function getFactQty(): ?float
    {
        return $this->_get(static::FACT_QTY);
    }

    /**
     * @inheritDoc
     */
    public function getSubtotal(): ?float
    {
        return $this->_get(static::SUBTOTAL);
    }

    /**
     * @inheritDoc
     */
    public function getSubtotalInclTax(): ?float
    {
        return $this->_get(static::SUBTOTAL_INCL_TAX);
    }

    /**
     * @inheritDoc
     */
    public function getTaxAmount(): ?float
    {
        return $this->_get(static::TAX_AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function getTaxPercent(): ?float
    {
        return $this->_get(static::TAX_PERCENT);
    }

    /**
     * @inheritDoc
     */
    public function getDiscountAmount(): ?float
    {
        return $this->_get(static::DISCOUNT_AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function getDiscountPercent(): ?float
    {
        return $this->_get(static::DISCOUNT_PERCENT);
    }

    /**
     * @inheritDoc
     */
    public function getRowTotal(): ?float
    {
        return $this->_get(static::ROW_TOTAL);
    }

    /**
     * @inheritDoc
     */
    public function getAction(): ?string
    {
        return $this->_get(static::ACTION);
    }

    /**
     * @inheritDoc
     */
    public function getBackToStock(): ?bool
    {
        return $this->_get(static::BACK_TO_STOCK);
    }

    /**
     * @inheritDoc
     */
    public function setPrice(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::PRICE, $value);
    }

    /**
     * @inheritDoc
     */
    public function setPriceInclTax(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::PRICE_INCL_TAX, $value);
    }

    /**
     * @inheritDoc
     */
    public function setFactQty(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::FACT_QTY, $value);
    }

    /**
     * @inheritDoc
     */
    public function setSubtotal(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::SUBTOTAL, $value);
    }

    /**
     * @inheritDoc
     */
    public function setSubtotalInclTax(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::SUBTOTAL_INCL_TAX, $value);
    }

    /**
     * @inheritDoc
     */
    public function setTaxAmount(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::TAX_AMOUNT, $value);
    }

    /**
     * @inheritDoc
     */
    public function setTaxPercent(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::TAX_PERCENT, $value);
    }

    /**
     * @inheritDoc
     */
    public function setDiscountAmount(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::DISCOUNT_AMOUNT, $value);
    }

    /**
     * @inheritDoc
     */
    public function setDiscountPercent(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::DISCOUNT_PERCENT, $value);
    }

    /**
     * @inheritDoc
     */
    public function setRowTotal(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::ROW_TOTAL, $value);
    }

    /**
     * @inheritDoc
     */
    public function setAction(string $value): EditOrderItemDataInterface
    {
        return $this->setData(static::ACTION, $value);
    }

    /**
     * @inheritDoc
     */
    public function setBackToStock(bool $value): EditOrderItemDataInterface
    {
        return $this->setData(static::BACK_TO_STOCK, $value);
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
    public function setTaxRates(array $value = []): EditOrderItemDataInterface
    {
        return $this->setData(static::TAX_RATES, $value);
    }

    /**
     * @inheritDoc
     */
    public function getProductOption(): ?\Magento\Quote\Api\Data\ProductOptionInterface
    {
        return $this->_get(static::PRODUCT_OPTION);
    }

    /**
     * @inheritDoc
     */
    public function setProductOption(\Magento\Quote\Api\Data\ProductOptionInterface $value): EditOrderItemDataInterface
    {
        return $this->setData(static::PRODUCT_OPTION, $value);
    }

    /**
     * @inheritDoc
     */
    public function getRowTotalInclTax(): ?float
    {
        return $this->_get(static::ROW_TOTAL_INCL_TAX);
    }

    /**
     * @inheritDoc
     */
    public function setRowTotalInclTax(float $value): EditOrderItemDataInterface
    {
        return $this->setData(static::ROW_TOTAL_INCL_TAX, $value);
    }
}
