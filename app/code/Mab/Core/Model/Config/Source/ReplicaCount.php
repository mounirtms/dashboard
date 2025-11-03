<?php
/**
 * Copyright © MAB, Inc. All rights reserved.
 */
namespace Mab\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for Elasticsearch replica count options
 */
class ReplicaCount implements OptionSourceInterface
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
                'value' => '0',
                'label' => __('0 (No replicas) - Best for indexing performance')
            ],
            [
                'value' => '1',
                'label' => __('1 (One replica) - Default setting')
            ],
            [
                'value' => '2',
                'label' => __('2 (Two replicas) - High availability')
            ],
            [
                'value' => '3',
                'label' => __('3 (Three replicas) - Maximum redundancy')
            ]
        ];
    }
}