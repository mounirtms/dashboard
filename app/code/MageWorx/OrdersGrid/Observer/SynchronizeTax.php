<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageWorx\OrdersGrid\Helper\Data as Helper;
use MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection;

/**
 * Class Synchronize Tax
 * @package MageWorx\OrdersGrid\Observer
 *
 * Observer class for the automatically synchronization of additional table of the orders grid for the Tax
 *
 * @see \MageWorx\OrdersGrid\Observer\Synchronize::CLASS_MAPPER
 */
class SynchronizeTax implements ObserverInterface
{
    /**
     * @var Collection
     */
    private $customOrderGridCollection;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * SynchronizeTax constructor.
     *
     * @param Collection $customOrderGridCollection
     * @param Helper $helper
     */
    public function __construct(
        Collection $customOrderGridCollection,
        Helper $helper
    ) {
        $this->customOrderGridCollection = $customOrderGridCollection;
        $this->helper = $helper;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        if ($this->helper->isSyncByCronEnabled()) {
            // Do not sync order automatically if sync by cron enabled
            return $this;
        }

        $event = $observer->getEvent();
        /** @var \Magento\Tax\Model\Sales\Order\Tax $object */
        $object = $event->getDataObject();
        if (!is_object($object)) {
            return $this;
        }

        if ($object instanceof \Magento\Tax\Model\Sales\Order\Tax
            || $object instanceof \Magento\Sales\Model\Order\Tax
        ) {
            $orderId = $object->getOrderId();
            if (!$orderId) {
                return $this;
            }
            $this->customOrderGridCollection->grabDataFromSalesOrderTaxTable([$orderId]);
        }

        return $this;
    }
}
