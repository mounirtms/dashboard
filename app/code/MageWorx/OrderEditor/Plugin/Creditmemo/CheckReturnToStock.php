<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Creditmemo;

use Magento\Sales\Block\Adminhtml\Order\Creditmemo\Create\Items;
use Magento\Sales\Model\Order\Creditmemo\Item;
use MageWorx\OrderEditor\Helper\Data as Helper;

/**
 * Check the "Return To Stock" checkbox in the items grid according settings.
 */
class CheckReturnToStock
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * @param Items $subject
     * @param Item $item
     * @return array
     */
    public function beforeGetItemHtml(
        Items $subject,
        Item $item
    ): array {
        if (!$item->getBackToStock() && $this->helper->getReturnToStock()) {
            $item->setData('back_to_stock', true);
        }

        return [$item];
    }
}
