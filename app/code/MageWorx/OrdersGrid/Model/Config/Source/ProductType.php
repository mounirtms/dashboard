<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\Config\Source;

class ProductType implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var \Magento\Catalog\Model\ProductTypes\ConfigInterface
     */
    protected $productTypeConfig;

    /**
     * @var array
     */
    protected $types = [];

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
     */
    public function __construct(
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
    ) {
        $this->productTypeConfig = $productTypeConfig;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray(): array
    {
        if (!empty($this->options)) {
            return $this->options;
        }

        $types = $this->getAllTypes();
        $this->options = [];

        foreach ($types as $type) {
            $this->options[] = [
                'label' => $type['label'],
                'value' => $type['name']
            ];
        }

        return $this->options;
    }

    /**
     * @return array
     */
    public function getAllTypes(): array
    {
        if (empty($this->types)) {
            $this->types = $this->productTypeConfig->getAll();
        }

        return $this->types;
    }
}
