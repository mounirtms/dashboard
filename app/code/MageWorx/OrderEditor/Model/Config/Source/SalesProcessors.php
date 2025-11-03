<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SalesProcessors implements OptionSourceInterface
{
    /**
     * @var array
     */
    protected $salesProcessors = [];

    /**
     * @param array $salesProcessors
     */
    public function __construct(
        array $salesProcessors = []
    ) {
        $this->salesProcessors = $salesProcessors;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray(): array
    {
        return $this->salesProcessors;
    }
}
