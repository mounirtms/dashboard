<?php
namespace Mab\SourceSelector\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Succursale implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 16, 'label' => __('Centre')],
            ['value' => 25, 'label' => __('Est')],
            ['value' => 31, 'label' => __('Ouest')],
            ['value' => 47, 'label' => __('Sud')],
        ];
    }
}
