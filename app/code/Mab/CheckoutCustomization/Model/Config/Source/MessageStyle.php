<?php
namespace Mab\CheckoutCustomization\Model\Config\Source;

class MessageStyle implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'notice', 'label' => __('Notice')],
            ['value' => 'warning', 'label' => __('Warning')],
            ['value' => 'error', 'label' => __('Error')],
            ['value' => 'success', 'label' => __('Success')],
            ['value' => 'custom', 'label' => __('Custom')]
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
            'notice' => __('Notice'),
            'warning' => __('Warning'),
            'error' => __('Error'),
            'success' => __('Success'),
            'custom' => __('Custom')
        ];
    }
}
