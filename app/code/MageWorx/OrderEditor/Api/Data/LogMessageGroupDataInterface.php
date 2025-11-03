<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data;

interface LogMessageGroupDataInterface
{
    /**
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * @param string $code
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
     */
    public function setCode(string $code): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface;

    /**
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageInterface[]
     */
    public function getMessages(): array;

    /**
     * @param int $level
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
     */
    public function setLevel(int $level = 0): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface;

    /**
     * @return int|null
     */
    public function getLevel(): ?int;

    /**
     * @param int $position
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
     */
    public function setPosition(int $position = 0): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface;

    /**
     * @return int|null
     */
    public function getPosition(): ?int;

    /**
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * @param string $type
     * @return \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
     */
    public function setType(string $type): \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface;
}
