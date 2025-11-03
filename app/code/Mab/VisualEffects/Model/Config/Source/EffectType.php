<?php
namespace Mab\VisualEffects\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class EffectType implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'none', 'label' => __('None')],
            ['value' => 'confetti', 'label' => __('Confetti')],
            ['value' => 'fireworks', 'label' => __('Fireworks')],
            ['value' => 'sparkles', 'label' => __('Sparkles')],
            ['value' => 'bounce', 'label' => __('Bounce')],
            ['value' => 'pulse', 'label' => __('Pulse')],
            ['value' => 'glow', 'label' => __('Glow')],
            ['value' => 'shake', 'label' => __('Shake')],
            ['value' => 'zoom', 'label' => __('Zoom')],
            ['value' => 'slide', 'label' => __('Slide')],
            ['value' => 'fade', 'label' => __('Fade')],
            ['value' => 'celebration', 'label' => __('Celebration (Combined)')],
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
            'none' => __('None'),
            'confetti' => __('Confetti'),
            'fireworks' => __('Fireworks'),
            'sparkles' => __('Sparkles'),
            'bounce' => __('Bounce'),
            'pulse' => __('Pulse'),
            'glow' => __('Glow'),
            'shake' => __('Shake'),
            'zoom' => __('Zoom'),
            'slide' => __('Slide'),
            'fade' => __('Fade'),
            'celebration' => __('Celebration (Combined)'),
        ];
    }
}