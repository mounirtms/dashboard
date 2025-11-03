<?php
namespace Mab\VisualEffects\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class EffectIntensity implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'subtle', 'label' => __('Subtle')],
            ['value' => 'moderate', 'label' => __('Moderate')],
            ['value' => 'intense', 'label' => __('Intense')],
            ['value' => 'extreme', 'label' => __('Extreme')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'subtle' => __('Subtle'),
            'moderate' => __('Moderate'),
            'intense' => __('Intense'),
            'extreme' => __('Extreme'),
        ];
    }
}