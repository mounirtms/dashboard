<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Logger;

use MageWorx\OrderEditor\Api\ChangeLoggerInterface;

class LogMessage implements \MageWorx\OrderEditor\Api\Data\LogMessageInterface
{
    /**
     * @var string
     */
    private $message = '';

    /**
     * @var string
     */
    private $group = ChangeLoggerInterface::GENERAL_GROUP;

    /**
     * @var int
     */
    private $position = 0;

    /**
     * @var int
     */
    private $level = 0;

    /**
     * @var bool
     */
    private $couldBeEmpty = true;

    /**
     * LogMessage constructor.
     *
     * @param string $message
     * @param string $group
     * @param int $position
     * @param int $level
     * @param bool $couldBeEmpty
     */
    public function __construct(
        string $message = '',
        string $group = ChangeLoggerInterface::GENERAL_GROUP,
        int $position = 0,
        int $level = 0,
        bool $couldBeEmpty = true
    ) {
        $this->message      = $message;
        $this->group        = $group;
        $this->position     = $position;
        $this->level        = $level;
        $this->couldBeEmpty = $couldBeEmpty;
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @inheritDoc
     */
    public function setMessage(string $message): \MageWorx\OrderEditor\Api\Data\LogMessageInterface
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @inheritDoc
     */
    public function setGroup(string $group): \MageWorx\OrderEditor\Api\Data\LogMessageInterface
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setPosition(int $position = 0): \MageWorx\OrderEditor\Api\Data\LogMessageInterface
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): ?int
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function setLevel(int $level = 0): \MageWorx\OrderEditor\Api\Data\LogMessageInterface
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLevel(): ?int
    {
        return $this->level;
    }

    /**
     * @inheritDoc
     */
    public function getCouldBeEmpty(): bool
    {
        return (bool)$this->couldBeEmpty;
    }

    /**
     * @inheritDoc
     */
    public function setCouldBeEmpty(bool $value = true): \MageWorx\OrderEditor\Api\Data\LogMessageInterface
    {
        $this->couldBeEmpty = $value;

        return $this;
    }
}
