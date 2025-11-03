<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

interface ShippingMethodDataInterface
{
    const CODE            = 'code';
    const TITLE           = 'title';
    const PRICE_EXCL_TAX  = 'price_excl_tax';
    const PRICE_INCL_TAX  = 'price_incl_tax';
    const TAX_PERCENT     = 'tax_percent';
    const DISCOUNT_AMOUNT = 'discount_amount';
    const TAX_RATES       = 'tax_rates';

    /**
     * Shipping method code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Set shipping method code
     *
     * @param string $value
     * @return ShippingMethodDataInterface
     */
    public function setCode(string $value): ShippingMethodDataInterface;

    /**
     * Shipping method title
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Set shipping method title
     *
     * @param string $value
     * @return ShippingMethodDataInterface
     */
    public function setTitle(string $value): ShippingMethodDataInterface;

    /**
     * Get shipping method price excluding tax (in store currency)
     *
     * @return float
     */
    public function getPriceExclTax(): float;

    /**
     * Set shipping method price excluding tax (in store currency)
     *
     * @param float $value
     * @return ShippingMethodDataInterface
     */
    public function setPriceExclTax(float $value): ShippingMethodDataInterface;

    /**
     * Get shipping method price including tax (in store currency)
     *
     * @return float
     */
    public function getPriceInclTax(): float;

    /**
     * Set shipping method price including tax (in store currency)
     *
     * @param float $value
     * @return ShippingMethodDataInterface
     */
    public function setPriceInclTax(float $value): ShippingMethodDataInterface;

    /**
     * Get shipping method tax percent
     *
     * @return float
     */
    public function getTaxPercent(): float;

    /**
     * Set shipping method tax percent
     *
     * @param float $value
     * @return ShippingMethodDataInterface
     */
    public function setTaxPercent(float $value = 0): ShippingMethodDataInterface;

    /**
     * Get shipping method discount amount (in store currency)
     *
     * @return float
     */
    public function getDiscountAmount(): float;

    /**
     * Set shipping method discount amount (in store currency)
     *
     * @param float $value
     * @return ShippingMethodDataInterface
     */
    public function setDiscountAmount(float $value = 0): ShippingMethodDataInterface;

    /**
     * @return \MageWorx\OrderEditor\Api\Data\OrderManager\TaxRateDataInterface[]
     */
    public function getTaxRates(): array;

    /**
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\TaxRateDataInterface[] $value
     * @return ShippingMethodDataInterface
     */
    public function setTaxRates(array $value = []): ShippingMethodDataInterface;
}
