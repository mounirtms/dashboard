<?php
namespace Mab\VisualEffects\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ProgressBarStyle implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'modern', 'label' => __('Modern (Gradient)')],
            ['value' => 'classic', 'label' => __('Classic (Solid)')],
            ['value' => 'animated', 'label' => __('Animated (Moving Stripes)')],
            ['value' => 'neon', 'label' => __('Neon (Glowing)')],
            ['value' => 'minimal', 'label' => __('Minimal (Thin Line)')],
            ['value' => 'circular', 'label' => __('Circular Progress')],
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
            'modern' => __('Modern (Gradient)'),
            'classic' => __('Classic (Solid)'),
            'animated' => __('Animated (Moving Stripes)'),
            'neon' => __('Neon (Glowing)'),
            'minimal' => __('Minimal (Thin Line)'),
            'circular' => __('Circular Progress'),
        ];
    }
}