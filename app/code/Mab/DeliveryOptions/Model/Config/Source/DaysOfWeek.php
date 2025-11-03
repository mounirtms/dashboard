<?php
/**
 * MAB Delivery Options - Days of Week Source Model
 * 
 * @category    Mab
 * @package     Mab_DeliveryOptions
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\DeliveryOptions\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class DaysOfWeek
 * 
 * Provides days of the week options for delivery configuration
 */
class DaysOfWeek implements ArrayInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Monday')],
            ['value' => '2', 'label' => __('Tuesday')],
            ['value' => '3', 'label' => __('Wednesday')],
            ['value' => '4', 'label' => __('Thursday')],
            ['value' => '5', 'label' => __('Friday')],
            ['value' => '6', 'label' => __('Saturday')],
            ['value' => '0', 'label' => __('Sunday')]
        ];
    }
}