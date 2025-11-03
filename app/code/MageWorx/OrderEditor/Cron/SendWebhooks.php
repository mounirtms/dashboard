<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace MageWorx\OrderEditor\Cron;

use MageWorx\OrderEditor\Api\WebhookSenderInterface;
use MageWorx\OrderEditor\Helper\Data as Helper;
use MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity\CollectionFactory as QueueCollectionFactory;
use Psr\Log\LoggerInterface;

class SendWebhooks
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var QueueCollectionFactory
     */
    private $queueCollectionFactory;

    /**
     * @var WebhookSenderInterface
     */
    private $webhookSender;

    /**
     * SynchronizeRecentOrders constructor.
     *
     * @param Helper $helper
     * @param QueueCollectionFactory $collectionFactory
     * @param WebhookSenderInterface $webhookSender
     * @param LoggerInterface $logger
     */
    public function __construct(
        Helper $helper,
        QueueCollectionFactory $collectionFactory,
        WebhookSenderInterface $webhookSender,
        LoggerInterface $logger
    ) {
        $this->helper                 = $helper;
        $this->queueCollectionFactory = $collectionFactory;
        $this->webhookSender          = $webhookSender;
        $this->logger                 = $logger;
    }

    /**
     * Orders synchronization
     *
     * @return void
     */
    public function execute()
    {
        if ($this->helper->isWebhookEnabled() && $this->helper->getWebhookEndpoint()) {
            try {
                /** @var \MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity\Collection $queueCollection */
                $queueCollection = $this->queueCollectionFactory->create();
                $queueCollection->setOrder(
                    'created_at',
                    \Magento\Framework\Data\Collection\AbstractDb::SORT_ORDER_ASC
                );
                $queueCollection->addFieldToFilter('status', ['eq' => 0]);
                $queueCollection->addFieldToFilter('number_of_attempts', ['lteq' => 10]);
                $queueCollection->setPageSize(1)
                                ->setCurPage(1);

                /** @var \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface[] $items */
                $items = $queueCollection->getItems();
                foreach ($items as $item) {
                    $status = $this->webhookSender->send($item);
                    if ($status) {
                        $queueCollection->getResource()->delete($item);
                    } else {
                        $item->setStatus(0)
                             ->setNumberOfAttempts($item->getNumberOfAttempts() + 1);
                        $queueCollection->getResource()->save($item);
                    }
                }
            } catch (\Exception $exception) {
                $this->logger->critical($exception);
            }
        }
    }
}
