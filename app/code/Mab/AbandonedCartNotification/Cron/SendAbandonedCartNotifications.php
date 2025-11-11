<?php
namespace Mab\AbandonedCartNotification\Cron;

use Mab\AbandonedCartNotification\Model\AbandonedCartNotification;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Quote\Model\QuoteFactory;
use Psr\Log\LoggerInterface;

class SendAbandonedCartNotifications
{
    protected $abandonedCartNotification;
    protected $quoteCollectionFactory;
    protected $quoteFactory;
    protected $logger;

    public function __construct(
        AbandonedCartNotification $abandonedCartNotification,
        QuoteCollectionFactory $quoteCollectionFactory,
        QuoteFactory $quoteFactory,
        LoggerInterface $logger
    ) {
        $this->abandonedCartNotification = $abandonedCartNotification;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->quoteFactory = $quoteFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->info('Abandoned Cart Notification Cron Started');

        try {
            $timeThreshold = $this->abandonedCartNotification->getTimeThreshold();
            $timeThresholdHoursAgo = date('Y-m-d H:i:s', strtotime("-{$timeThreshold} hours"));

            $quoteCollection = $this->quoteCollectionFactory->create();
            $quoteCollection->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('items_count', ['gt' => 0])
                ->addFieldToFilter('updated_at', ['lt' => $timeThresholdHoursAgo])
                ->addFieldToFilter('customer_email', ['notnull' => true]);

            $sentCount = 0;
            foreach ($quoteCollection as $quote) {
                // Load the full quote to ensure all data is available
                $fullQuote = $this->quoteFactory->create();
                $fullQuote->loadByIdWithoutStore($quote->getId());
                
                if ($this->abandonedCartNotification->sendNotification($fullQuote)) {
                    $sentCount++;
                }
            }

            $this->logger->info("Abandoned Cart Notification Cron Completed. Sent {$sentCount} notifications.");
        } catch (\Exception $e) {
            $this->logger->error('Abandoned Cart Notification Cron Error: ' . $e->getMessage());
        }

        return $this;
    }
}