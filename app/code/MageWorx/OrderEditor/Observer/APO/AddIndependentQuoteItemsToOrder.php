<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Observer\APO;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class AddIndependentQuoteItemsToOrder implements ObserverInterface
{
    /**
     * @var \MageWorx\OrderEditor\Api\OrderManager\OrderItemsManagerInterface
     */
    protected $itemsManager;

    /**
     * @var \MageWorx\OrderEditor\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @param \MageWorx\OrderEditor\Api\OrderManager\OrderItemsManagerInterface $itemsManager
     * @param \MageWorx\OrderEditor\Helper\Data $helper
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\OrderManager\OrderItemsManagerInterface $itemsManager,
        \Magento\Framework\App\RequestInterface $request,
        \MageWorx\OrderEditor\Helper\Data $helper
    ) {
        $this->itemsManager = $itemsManager;
        $this->request = $request;
        $this->helper = $helper;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $newQuoteItems = $observer->getData('new_items');
        if (empty($newQuoteItems)) {
            return;
        }

        $firstItem = reset($newQuoteItems);
        if (!$firstItem instanceof \MageWorx\OrderEditor\Model\Quote\Item) {
            return;
        }

        $quote = $firstItem->getQuote();
        if (!$quote instanceof \MageWorx\OrderEditor\Model\Quote) {
            return;
        }

        $orderId = (int)($this->helper->getOrderId() ?? $this->request->getParam('order_id'));
        if (!$orderId) {
            throw new LocalizedException(__('Unable to locate order ID in observer %1', get_class($this)));
        }

        $orderItems = $this->itemsManager->addItemsToOrderByIdAndReturnOrderItems($orderId, $newQuoteItems);
        $this->helper->setNewOrderItems($orderItems);
    }
}
