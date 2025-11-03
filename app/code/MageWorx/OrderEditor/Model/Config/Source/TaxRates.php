<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Config\Source;


class TaxRates implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \MageWorx\OrderEditor\Api\TaxManagerInterface
     */
    private $taxManager;

    /**
     * TaxRates constructor.
     *
     * @param \MageWorx\OrderEditor\Api\TaxManagerInterface $taxManager
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\TaxManagerInterface $taxManager
    ) {
        $this->taxManager = $taxManager;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->taxManager->getAllAvailableTaxRateCodes();
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $rates = $this->toOptionArray();
        $options = [];
        foreach ($rates as $rate) {
            $options[] = $rate['value'];
        }

        return $options;
    }
}
