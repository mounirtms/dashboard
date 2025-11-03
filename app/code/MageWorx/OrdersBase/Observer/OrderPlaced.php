<?php
/**
 * Copyright © 2017 MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersBase\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class OrderPlaced implements ObserverInterface
{
    /**
     * @var \MageWorx\OrdersBase\Api\DeviceDataRepositoryInterface
     */
    protected $deviceDataRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \MageWorx\OrdersBase\Api\DataParserInterface[]
     */
    private $parsers;

    /**
     * OrderPlaced constructor.
     *
     * @param \MageWorx\OrdersBase\Api\DeviceDataRepositoryInterface $deviceDataRepository
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $parsers
     */
    public function __construct(
        \MageWorx\OrdersBase\Api\DeviceDataRepositoryInterface $deviceDataRepository,
        \Psr\Log\LoggerInterface $logger,
        array $parsers = []
    ) {
        $this->deviceDataRepository = $deviceDataRepository;
        $this->logger               = $logger;
        $this->parsers              = $parsers;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order   = $observer->getEvent()->getOrder();
        $orderId = $order->getId();

        try {
            $deviceDataEntity = $this->deviceDataRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException $exception) {
            $deviceDataEntity = $this->deviceDataRepository->getEmptyEntity();
        }

        // Do not save many times in case when event was trigger twice
        if ($deviceDataEntity->getId()) {
            return;
        }

        $deviceDataEntity->setOrderId($orderId);

        foreach ($this->parsers as $dataParser) {
            $dataParser->parseData($order, $deviceDataEntity);
        }

        try {
            $this->deviceDataRepository->save($deviceDataEntity);
        } catch (\Exception $e) {
            // Do not break the checkout porcess in case any error fired
            $this->logger->warning($e->getMessage());
        }
    }
}
