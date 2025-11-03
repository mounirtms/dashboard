<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrdersCollectionFactory;
use MageWorx\OrdersGrid\Helper\Data as Helper;
use MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\CollectionFactory as OrderGridCustomCollectionFactory;
use Psr\Log\LoggerInterface;

class SynchronizeRecentOrders
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var OrderGridCustomCollectionFactory
     */
    private $ordersGridCollectionFactory;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var OrdersCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SynchronizeRecentOrders constructor.
     *
     * @param Helper $helper
     * @param OrderGridCustomCollectionFactory $ordersGridCollectionFactory
     * @param OrdersCollectionFactory $orderCollectionFactory
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Helper $helper,
        OrderGridCustomCollectionFactory $ordersGridCollectionFactory,
        OrdersCollectionFactory $orderCollectionFactory,
        TimezoneInterface $timezone,
        LoggerInterface $logger
    ) {
        $this->helper                      = $helper;
        $this->ordersGridCollectionFactory = $ordersGridCollectionFactory;
        $this->orderCollectionFactory      = $orderCollectionFactory;
        $this->timezone                    = $timezone;
        $this->logger                      = $logger;
    }

    /**
     * Orders synchronization
     *
     * @return void
     */
    public function execute()
    {
        if ($this->helper->isSyncByCronEnabled()) {

            $storeTimeNowObject = new \DateTime('now', new \DateTimeZone('UTC'));
            $storeTimeNowObject->modify('-10 minutes');

            $ordersCollection = $this->orderCollectionFactory->create();
            $ordersCollection->addFieldToSelect('entity_id');
            $ordersCollection->addFieldToFilter('created_at', ['gteq' => $storeTimeNowObject]);
            $orderIdsToSync = $ordersCollection->getAllIds();

            if (!empty($orderIdsToSync)) {
                $this->logger->info(
                    sprintf(
                        'Sync orders by cron. Ids: %s',
                        implode(', ', $orderIdsToSync)
                    )
                );
                /** @var \MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection $ordersGridCollection */
                $ordersGridCollection = $this->ordersGridCollectionFactory->create();
                $ordersGridCollection->syncOrdersData($orderIdsToSync);
            } else {
                $this->logger->info(
                    sprintf(
                        'Sync orders by cron. No order from %s',
                        $storeTimeNowObject->format('Y-m-d H:i:s')
                    )
                );
            }
        }
    }
}
