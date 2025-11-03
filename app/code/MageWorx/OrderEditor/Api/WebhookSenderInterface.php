<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace MageWorx\OrderEditor\Api;

/**
 * Send webhook
 */
interface WebhookSenderInterface
{
    /**
     * @param Data\WebhookQueueEntityInterface $webhookQueueEntity
     * @return bool
     */
    public function send(\MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface $webhookQueueEntity): bool;
}
