<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use MageWorx\OrderEditor\Api\OrderRepositoryInterface;

class OrderManager implements \MageWorx\OrderEditor\Api\OrderManagerInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \MageWorx\OrderEditor\Api\RestoreQuoteInterface
     */
    private $restoreQuoteModel;

    /**
     * OrderManager constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param \MageWorx\OrderEditor\Api\RestoreQuoteInterface $restoreQuoteModel
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\OrderRepositoryInterface $orderRepository,
        \MageWorx\OrderEditor\Api\RestoreQuoteInterface $restoreQuoteModel
    ) {
        $this->orderRepository   = $orderRepository;
        $this->restoreQuoteModel = $restoreQuoteModel;
    }

    /**
     * @inheritDoc
     */
    public function backupOrdersQuoteByOrderId(int $orderId): void
    {
        $order = $this->orderRepository->getById($orderId);
        $quote = $order->getQuote();
        $quote->setOrigOrderId($orderId);
        $this->restoreQuoteModel->backupInitialQuoteState($quote);
    }

    /**
     * @inheritDoc
     */
    public function restoreOrdersQuoteByOrderId(int $orderId): void
    {
        $order = $this->orderRepository->getById($orderId);
        $quote = $order->getQuote();
        $quote->setOrigOrderId($orderId);
        $this->restoreQuoteModel->restore($quote);
    }
}
