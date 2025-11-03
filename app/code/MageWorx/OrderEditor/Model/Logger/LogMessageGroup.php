<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Logger;

use MageWorx\OrderEditor\Api\ChangeLoggerInterface;

class LogMessageGroup implements \MageWorx\OrderEditor\Api\LogMessageGroupInterface
{
    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $type;

    /**
     * @var int
     */
    private $level;

    /**
     * @var int
     */
    private $position;

    /**
     * @var \MageWorx\OrderEditor\Api\Data\LogMessageInterface[]
     */
    private $messages = [];

    /**
     * LogMessageGroup constructor.
     *
     * @param string $code
     * @param string $type
     * @param int $level
     * @param int $position
     * @param \MageWorx\OrderEditor\Api\Data\LogMessageInterface[] $messages
     */
    public function __construct(
        string $code = ChangeLoggerInterface::GENERAL_GROUP,
        string $type = ChangeLoggerInterface::TYPE_GENERAL,
        int $level = 0,
        int $position = 0,
        array $messages = []
    ) {
        $this->code     = $code;
        $this->type = $type;
        $this->level    = $level;
        $this->position = $position;
        $this->messages = $messages;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function setCode(string $code): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
    {
        $this->code = $code;

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
    public function setLevel(int $level = 1): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
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
    public function setPosition(int $position = 1): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
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
    public function addMessage(\MageWorx\OrderEditor\Api\Data\LogMessageInterface $message
    ): \MageWorx\OrderEditor\Api\LogMessageGroupInterface {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addMessages(array $messages): \MageWorx\OrderEditor\Api\LogMessageGroupInterface
    {
        $this->messages = array_merge($this->messages, $messages);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
    {
        $this->type = $type;

        return $this;
    }
}
