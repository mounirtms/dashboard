<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Quote;

class QuoteItemOptionsManager implements \MageWorx\OrderEditor\Api\QuoteItemOptionsInterface
{
    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\Item\Option\CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param \Magento\Quote\Model\ResourceModel\Quote\Item\Option\CollectionFactory $collectionFactory
     */
    public function __construct(
        \Magento\Quote\Model\ResourceModel\Quote\Item\Option\CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function getOptionsByItem(\Magento\Quote\Api\Data\CartItemInterface $item): array
    {
        /** @var \Magento\Quote\Model\ResourceModel\Quote\Item\Option\Collection $collection */
        $collection = $this->collectionFactory->create();

        $options = $collection->getOptionsByItem($item) ?? [];

        $optionsByCode = [];
        foreach ($options as $option) {
            $optionsByCode[$option->getCode()] = $option;
        }

        return $optionsByCode;
    }
}
