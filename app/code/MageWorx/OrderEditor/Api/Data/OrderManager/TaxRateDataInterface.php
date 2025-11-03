<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

interface TaxRateDataInterface
{
    const CODE = 'code';
    const PERCENT = 'percent';

    /**
     * Get Tax Rate code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Set Tax Rate code
     *
     * @param string $value
     * @return TaxRateDataInterface
     */
    public function setCode(string $value): TaxRateDataInterface;

    /**
     * Get Tax Rate percent
     *
     * @return float
     */
    public function getPercent(): float;

    /**
     * Set Tax Rate percent
     *
     * @param float $value
     * @return TaxRateDataInterface
     */
    public function setPercent(float $value): TaxRateDataInterface;

    /**
     * Convert the rate to array
     *
     * @return array
     */
    public function toArray(): array;
}
