<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api;

/**
 * Interface ChangeLoggerInterface
 *
 * Log order changes during edit process.
 * Must be used as singleton object.
 */
interface ChangeLoggerInterface
{
    const SIMPLE_MESSAGE_KEY = 'simple_message';
    const MESSAGES_KEY       = 'messages';

    const GROUP_CODE    = 'group';
    const GENERAL_GROUP = 'general';

    const TYPE_CODE = 'type';
    const TYPE_GENERAL = 'general';
    const TYPE_ITEM = 'item';

    const LEVEL_MODIFIER = 4;

    /**
     * Add log messages (LogMessageInterface) to queue
     *
     * @param \MageWorx\OrderEditor\Api\Data\LogMessageInterface[] $messages
     * @param string|null $group
     * @return \MageWorx\OrderEditor\Api\ChangeLoggerInterface
     */
    public function addMessagesToLog(
        array $messages,
        string $group = null
    ): \MageWorx\OrderEditor\Api\ChangeLoggerInterface;

    /**
     * Add simple string message.
     * Would be converted to LogMessageInterface by Logger automatically.
     *
     * @param string $simpleMessage
     * @param string|null $group
     * @return ChangeLoggerInterface
     */
    public function addSimpleMessageToLog(
        string $simpleMessage,
        string $group = null
    ): \MageWorx\OrderEditor\Api\ChangeLoggerInterface;

    /**
     * Return current log messages
     *
     * @return \MageWorx\OrderEditor\Api\LogMessageGroupInterface[]
     */
    public function getMessages(): array;

    /**
     * @param string $group
     * @return LogMessageGroupInterface
     */
    public function getGroup(string $group): \MageWorx\OrderEditor\Api\LogMessageGroupInterface;

    /**
     * Save actual (current) log messages for the specified order and clean queue.
     *
     * @param int $orderId
     * @param bool $notifyCustomer false by default
     * @param bool $visibleOnFront
     * @return ChangeLoggerInterface
     */
    public function saveLog(
        int $orderId,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false
    ): \MageWorx\OrderEditor\Api\ChangeLoggerInterface;

    /**
     * Remove all messages in current instance
     *
     * @param string|null $group
     * @return \MageWorx\OrderEditor\Api\ChangeLoggerInterface
     */
    public function cleanMessages(string $group = null): \MageWorx\OrderEditor\Api\ChangeLoggerInterface;
}
