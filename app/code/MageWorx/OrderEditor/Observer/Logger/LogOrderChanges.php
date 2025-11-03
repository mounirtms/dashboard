<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Observer\Logger;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\Data\LogMessageInterface;

class LogOrderChanges implements ObserverInterface
{
    /**
     * @var ChangeLoggerInterface
     */
    private $changeLogger;

    /**
     * LogOrderChanges constructor.
     *
     * @param ChangeLoggerInterface $changeLogger
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\ChangeLoggerInterface $changeLogger
    ) {
        $this->changeLogger = $changeLogger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $simpleMessage = $observer->getData(ChangeLoggerInterface::SIMPLE_MESSAGE_KEY);
        $groupCode     = $observer->getData(ChangeLoggerInterface::GROUP_CODE);
        $groupType     = $observer->getData(ChangeLoggerInterface::TYPE_CODE);

        if ($groupType) {
            $groupInstance = $this->changeLogger->getGroup($groupCode);
            $groupInstance->setType($groupType);
        }

        if ($simpleMessage) {
            $this->changeLogger->addSimpleMessageToLog($simpleMessage, $groupCode);
        }

        $messages = $observer->getData(ChangeLoggerInterface::MESSAGES_KEY);
        if (!empty($messages)) {
            $messagesFiltered = array_filter(
                $messages,
                function ($message) {
                    return $message instanceof \MageWorx\OrderEditor\Api\Data\LogMessageInterface;
                }
            );
            if (!empty($messagesFiltered)) {
                $this->changeLogger->addMessagesToLog($messagesFiltered, $groupCode);
            }
        }
    }
}
