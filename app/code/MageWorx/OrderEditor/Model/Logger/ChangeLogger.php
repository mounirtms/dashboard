<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Logger;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use MageWorx\OrderEditor\Api\Data\LogMessageInterfaceFactory;
use MageWorx\OrderEditor\Api\LogMessageGroupInterfaceFactory;
use MageWorx\OrderEditor\Api\LogMessageGroupInterface;
use Psr\Log\LoggerInterface;
use MageWorx\OrderEditor\Helper\Data as Helper;

class ChangeLogger implements \MageWorx\OrderEditor\Api\ChangeLoggerInterface
{
    /**
     * @var LogMessageInterfaceFactory
     */
    protected $logMessageFactory;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    protected $orderStatusRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderStatusHistoryInterfaceFactory
     */
    protected $orderStatusHistoryFactory;

    /**
     * @var LogMessageGroupInterfaceFactory
     */
    protected $logMessageGroupFactory;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Session
     */
    protected $authSession;

    /**
     * ChangeLogger constructor.
     *
     * @param LogMessageInterfaceFactory $logMessageFactory
     * @param LogMessageGroupInterfaceFactory $logMessageGroupFactory
     * @param OrderStatusHistoryRepositoryInterface $orderStatusRepository
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param Session $authSession
     * @param Helper $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        LogMessageInterfaceFactory $logMessageFactory,
        LogMessageGroupInterfaceFactory $logMessageGroupFactory,
        OrderStatusHistoryRepositoryInterface $orderStatusRepository,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        OrderRepositoryInterface $orderRepository,
        Session $authSession,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->logMessageFactory         = $logMessageFactory;
        $this->logMessageGroupFactory    = $logMessageGroupFactory;
        $this->orderStatusRepository     = $orderStatusRepository;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderRepository           = $orderRepository;
        $this->authSession               = $authSession;
        $this->helper                    = $helper;
        $this->logger                    = $logger;
    }

    /**
     * @var array
     */
    private $messages = [];

    /**
     * @inheritDoc
     */
    public function addMessagesToLog(
        array $messages,
        string $group = null
    ): \MageWorx\OrderEditor\Api\ChangeLoggerInterface {
        if ($group === null || $group === '') {
            $group = static::GENERAL_GROUP;
        }

        /** @var LogMessageGroupInterface $groupInstance */
        $groupInstance = $this->getGroupInstance($group);
        $groupInstance->addMessages($messages);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(string $group): LogMessageGroupInterface
    {
        /** @var LogMessageGroupInterface $groupInstance */
        $groupInstance = $this->getGroupInstance($group);

        return $groupInstance;
    }

    /**
     * @inheritDoc
     */
    public function saveLog(
        int $orderId,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false
    ): \MageWorx\OrderEditor\Api\ChangeLoggerInterface {
        if ($this->helper->isLoggerEnabled()) {
            $contents = $this->prepareCurrentContent();
            if (empty($contents)) {
                return $this;
            }

            /** @var OrderStatusHistoryInterface $comment */
            $comment = $this->orderStatusHistoryFactory->create();
            $comment->setParentId($orderId)
                    ->setComment($contents)
                    ->setIsCustomerNotified($notifyCustomer)
                    ->setIsVisibleOnFront($visibleOnFront);

            try {
                $this->orderStatusRepository->save($comment);
            } catch (LocalizedException $exception) {
                $this->logger->error($exception->getLogMessage());
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    private function prepareCurrentContent(): string
    {
        $fullLogMessage   = '';
        $allMessageGroups = $this->getMessages();
        $lastElementKey   = $this->getLastElementKey($allMessageGroups);

        foreach ($allMessageGroups as $key => $messageGroup) {
            $groupMessages = $messageGroup->getMessages();

            if ($this->isEmptyItemGroup($messageGroup)) {
                continue;
            }

            foreach ($groupMessages as $messageInstance) {
                $indent         = str_repeat('&nbsp;', ($messageInstance->getLevel() * static::LEVEL_MODIFIER));
                $fullLogMessage .= $indent . $messageInstance->getMessage() . '<br/>' . PHP_EOL;
            }

            if ((string)$key !== $lastElementKey) {
                $fullLogMessage .= '<br/>' . PHP_EOL;
            }
        }

        if (!empty($fullLogMessage) && $this->helper->isNeedToAddAdminNameToLog()) {
            $fullLogMessage .= '<br/>' . PHP_EOL . '<i>' . __('Made by %1', $this->getAdminName()) . '</i><br/>' . PHP_EOL;
        }

        return $fullLogMessage;
    }

    /**
     * @inheritDoc
     */
    public function addSimpleMessageToLog(
        string $simpleMessage,
        string $group = null
    ): \MageWorx\OrderEditor\Api\ChangeLoggerInterface {
        if ($group === null || $group === '') {
            $group = static::GENERAL_GROUP;
        }

        /** @var \MageWorx\OrderEditor\Api\Data\LogMessageInterface $message */
        $message = $this->logMessageFactory->create(['message' => $simpleMessage, 'group' => $group]);
        $this->addMessagesToLog([$message], $group);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cleanMessages(string $group = null): \MageWorx\OrderEditor\Api\ChangeLoggerInterface
    {
        if ($group === null) {
            $this->messages = [];
        } elseif (isset($this->messages[$group])) {
            unset($this->messages[$group]);
        }

        return $this;
    }

    /**
     * @param string $groupCode
     * @return LogMessageGroupInterface
     */
    private function getGroupInstance(string $groupCode): LogMessageGroupInterface
    {
        /** @var LogMessageGroupInterface|null $group */
        $group = isset($this->messages[$groupCode]) && $this->messages[$groupCode] instanceof LogMessageGroupInterface ?
            $this->messages[$groupCode] :
            $this->logMessageGroupFactory->create();

        $this->messages[$groupCode] = $group;

        return $group;
    }

    /**
     * @param LogMessageGroupInterface $group
     * @return bool
     */
    private function isEmptyItemGroup(LogMessageGroupInterface $group): bool
    {
        if ($group->getType() === static::TYPE_ITEM) {
            $messages = $group->getMessages();
            if (empty($messages)) {
                return true;
            }

            if (count($messages) === 1 && !$messages[0]->getCouldBeEmpty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the last element key in array
     *
     * @param $array
     * @return string
     */
    private function getLastElementKey($array): string
    {
        if (function_exists('array_key_last')) {
            $lastArrayKey = call_user_func('array_key_last', $array);
        } else {
            $arrayKeys    = array_keys($array);
            $lastArrayKey = array_pop($arrayKeys);
        }

        return (string)$lastArrayKey;
    }

    /**
     * @return string
     */
    private function getAdminName(): string
    {
        $admin = $this->authSession->getUser();
        if (!$admin) {
            return 'Unknown admin';
        }

        $name = $admin->getFirstName() . ' ' . $admin->getLastName();
        if (!$name) {
            $name = $admin->getUserName();
        }

        return $name ?? 'No username';
    }
}
