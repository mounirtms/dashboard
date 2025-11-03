<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api;

interface LogMessageGroupInterface extends \MageWorx\OrderEditor\Api\Data\LogMessageGroupDataInterface
{
    /**
     * @param \MageWorx\OrderEditor\Api\Data\LogMessageInterface $message
     * @return \MageWorx\OrderEditor\Api\LogMessageGroupInterface
     */
    public function addMessage(\MageWorx\OrderEditor\Api\Data\LogMessageInterface $message
    ): \MageWorx\OrderEditor\Api\LogMessageGroupInterface;

    /**
     * @param \MageWorx\OrderEditor\Api\Data\LogMessageInterface[] $messages
     * @return \MageWorx\OrderEditor\Api\LogMessageGroupInterface
     */
    public function addMessages(array $messages): \MageWorx\OrderEditor\Api\LogMessageGroupInterface;
}
