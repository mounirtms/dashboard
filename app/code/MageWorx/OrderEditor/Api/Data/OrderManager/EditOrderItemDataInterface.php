<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

use Magento\Framework\Api\CustomAttributesDataInterface;
use MageWorx\OrderEditor\Api\AsArrayInterface;

interface EditOrderItemDataInterface
    extends
    CustomAttributesDataInterface,
    AsArrayInterface
{
    const ITEM_ID            = 'item_id';
    const PRICE              = 'price';
    const PRICE_INCL_TAX     = 'price_incl_tax';
    const FACT_QTY           = 'fact_qty';
    const SUBTOTAL           = 'subtotal';
    const SUBTOTAL_INCL_TAX  = 'subtotal_incl_tax';
    const TAX_AMOUNT         = 'tax_amount';
    const TAX_PERCENT        = 'tax_percent';
    const DISCOUNT_AMOUNT    = 'discount_amount';
    const DISCOUNT_PERCENT   = 'discount_percent';
    const ROW_TOTAL          = 'row_total';
    const ROW_TOTAL_INCL_TAX = 'row_total_incl_tax';
    const ACTION             = 'action';
    const BACK_TO_STOCK      = 'back_to_stock';
    const TAX_RATES          = 'tax_rates';
    const PRODUCT_OPTION     = 'product_option';

    /**
     * @return string
     */
    public function getItemId(): string;

    /**
     * @param string $id
     * @return EditOrderItemDataInterface
     */
    public function setItemId(string $id): EditOrderItemDataInterface;

    /**
     * @return float|null
     */
    public function getPrice(): ?float;

    /**
     * @return float|null
     */
    public function getPriceInclTax(): ?float;

    /**
     * @return float|null
     */
    public function getFactQty(): ?float;

    /**
     * @return float|null
     */
    public function getSubtotal(): ?float;

    /**
     * @return float|null
     */
    public function getSubtotalInclTax(): ?float;

    /**
     * @return float|null
     */
    public function getTaxAmount(): ?float;

    /**
     * @return float|null
     */
    public function getTaxPercent(): ?float;

    /**
     * @return float|null
     */
    public function getDiscountAmount(): ?float;

    /**
     * @return float|null
     */
    public function getDiscountPercent(): ?float;

    /**
     * @return float|null
     */
    public function getRowTotal(): ?float;

    /**
     * @return float|null
     */
    public function getRowTotalInclTax(): ?float;

    /**
     * @return string|null
     */
    public function getAction(): ?string;

    /**
     * @return bool|null
     */
    public function getBackToStock(): ?bool;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setPrice(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setPriceInclTax(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setFactQty(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setSubtotal(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setSubtotalInclTax(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setTaxAmount(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setTaxPercent(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setDiscountAmount(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setDiscountPercent(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setRowTotal(float $value): EditOrderItemDataInterface;

    /**
     * @param float $value
     * @return EditOrderItemDataInterface
     */
    public function setRowTotalInclTax(float $value): EditOrderItemDataInterface;

    /**
     * @param string $value
     * @return EditOrderItemDataInterface
     */
    public function setAction(string $value): EditOrderItemDataInterface;

    /**
     * @param bool $value
     * @return EditOrderItemDataInterface
     */
    public function setBackToStock(bool $value): EditOrderItemDataInterface;

    /**
     * @return \MageWorx\OrderEditor\Api\Data\OrderManager\TaxRateDataInterface[]
     */
    public function getTaxRates(): array;

    /**
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\TaxRateDataInterface[] $value
     * @return EditOrderItemDataInterface
     */
    public function setTaxRates(array $value = []): EditOrderItemDataInterface;

    /**
     * @return \Magento\Quote\Api\Data\ProductOptionInterface|null
     */
    public function getProductOption(): ?\Magento\Quote\Api\Data\ProductOptionInterface;

    /**
     * @param \Magento\Quote\Api\Data\ProductOptionInterface $value
     * @return EditOrderItemDataInterface
     */
    public function setProductOption(\Magento\Quote\Api\Data\ProductOptionInterface $value): EditOrderItemDataInterface;
}
