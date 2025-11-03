<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Api;

interface QuoteItemOptionsInterface
{
    /**
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return \Magento\Quote\Model\Quote\Item\Option[]|\Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface[]
     */
    public function getOptionsByItem(\Magento\Quote\Api\Data\CartItemInterface $item): array;
}
