<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data;

interface LogMessageInterface
{
    /**
     * Get formatted log message
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Set log message
     *
     * @param string $message
     * @return LogMessageInterface
     */
    public function setMessage(string $message): \MageWorx\OrderEditor\Api\Data\LogMessageInterface;

    /**
     * Get group name if specified
     *
     * @return string|null
     */
    public function getGroup(): ?string;

    /**
     * Set group name
     *
     * @param string $group
     * @return LogMessageInterface
     */
    public function setGroup(string $group): \MageWorx\OrderEditor\Api\Data\LogMessageInterface;

    /**
     * @param int $position
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageInterface
     */
    public function setPosition(int $position = 0): \MageWorx\OrderEditor\Api\Data\LogMessageInterface;

    /**
     * @return int|null
     */
    public function getPosition(): ?int;

    /**
     * @param int $level
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageInterface
     */
    public function setLevel(int $level = 0): \MageWorx\OrderEditor\Api\Data\LogMessageInterface;

    /**
     * @return int|null
     */
    public function getLevel(): ?int;

    /**
     * Is that message must be rendered in case it has no child messages?
     *
     * @return bool
     */
    public function getCouldBeEmpty(): bool;

    /**
     * Is that message must be rendered in case it has no child messages?
     *
     * @param bool $value
     * @return LogMessageInterface
     */
    public function setCouldBeEmpty(bool $value = true): \MageWorx\OrderEditor\Api\Data\LogMessageInterface;
}
