<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Api\Data;

interface WebhookQueueEntityInterface
{
    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return WebhookQueueEntityInterface
     */
    public function setEntityId($entityId): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

    /**
     * @return string|null
     */
    public function getEventName(): ?string;

    /**
     * @param string $value
     * @return WebhookQueueEntityInterface
     */
    public function setEventName(string $value): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $value
     * @return WebhookQueueEntityInterface
     */
    public function setCreatedAt(string $value): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

    /**
     * @return string|null
     */
    public function getDataSerialized(): ?string;

    /**
     * @param string $value
     * @return WebhookQueueEntityInterface
     */
    public function setDataSerialized(string $value): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

    /**
     * @return int|null
     */
    public function getNumberOfAttempts(): ?int;

    /**
     * @param int $value
     * @return WebhookQueueEntityInterface
     */
    public function setNumberOfAttempts(int $value): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

    /**
     * @return bool|null
     */
    public function getStatus(): ?bool;

    /**
     * @param bool $value
     * @return WebhookQueueEntityInterface
     */
    public function setStatus(bool $value): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

    /**
     * @return int|null
     */
    public function getWebsiteId(): ?int;

    /**
     * @param int $value
     * @return WebhookQueueEntityInterface
     */
    public function setWebsiteId(int $value): \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;
}
