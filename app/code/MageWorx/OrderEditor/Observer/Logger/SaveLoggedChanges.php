<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Observer\Logger;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;

class SaveLoggedChanges implements ObserverInterface
{
    /**
     * @var ChangeLoggerInterface
     */
    private $changeLogger;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * LogOrderChanges constructor.
     *
     * @param ChangeLoggerInterface $changeLogger
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\ChangeLoggerInterface $changeLogger,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->changeLogger = $changeLogger;
        $this->logger       = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $orderId        = (int)$observer->getData('order_id');
        $notifyCustomer = (bool)$observer->getData('notify_customer');
        if (!$orderId) {
            $this->logger->alert('Unable to save order changes log because the Order ID is not set!');

            return;
        }

        $this->changeLogger->saveLog($orderId, $notifyCustomer);
    }
}
