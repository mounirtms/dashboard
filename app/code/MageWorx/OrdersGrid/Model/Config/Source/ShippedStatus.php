<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\Config\Source;

class ShippedStatus implements \Magento\Framework\Data\OptionSourceInterface
{
    const NOT_SHIPPED = 0;
    const FULLY_SHIPPED = 1;
    const PARTIALLY_SHIPPED = 2;

    /**
     * Return array of options as value-label pairs
     *
     * Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('No'),
                'value' => static::NOT_SHIPPED
            ],
            [
                'label' => __('Yes'),
                'value' => static::FULLY_SHIPPED
            ],
            [
                'label' => __('Partially'),
                'value' => static::PARTIALLY_SHIPPED
            ],
        ];
    }
}
