<?php
/**
 * Copyright © MAB, Inc. All rights reserved.
 */
namespace Mab\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for Elasticsearch refresh interval options
 */
class RefreshInterval implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => '-1',
                'label' => __('Disabled (-1) - Best for heavy indexing')
            ],
            [
                'value' => '1s',
                'label' => __('1 second - Default Elasticsearch setting')
            ],
            [
                'value' => '5s',
                'label' => __('5 seconds - Reduced refresh frequency')
            ],
            [
                'value' => '10s',
                'label' => __('10 seconds - Low resource usage')
            ],
            [
                'value' => '30s',
                'label' => __('30 seconds - Minimal resource usage')
            ]
        ];
    }
}