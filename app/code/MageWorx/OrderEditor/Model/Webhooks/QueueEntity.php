<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Webhooks;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterface;

class QueueEntity extends \Magento\Framework\Model\AbstractExtensibleModel
    implements WebhookQueueEntityInterface
{
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Set resource model and Id field name
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity::class);
        $this->setIdFieldName('entity_id');
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return $this->getData('entity_id');
    }

    /**
     * @inheritDoc
     */
    public function setEntityId($entityId): WebhookQueueEntityInterface
    {
        return $this->setData('entity_id', $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getEventName(): ?string
    {
        return $this->getData('event_name');
    }

    /**
     * @inheritDoc
     */
    public function setEventName(string $value): WebhookQueueEntityInterface
    {
        return $this->setData('event_name', $value);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $value): WebhookQueueEntityInterface
    {
        return $this->setData('created_at', $value);
    }

    /**
     * @inheritDoc
     */
    public function getDataSerialized(): ?string
    {
        return $this->getData('data_serialized');
    }

    /**
     * @inheritDoc
     */
    public function setDataSerialized(string $value): WebhookQueueEntityInterface
    {
        return $this->setData('data_serialized', $value);
    }

    /**
     * @inheritDoc
     */
    public function getNumberOfAttempts(): ?int
    {
        return $this->getData('number_of_attempts');
    }

    /**
     * @inheritDoc
     */
    public function setNumberOfAttempts(int $value): WebhookQueueEntityInterface
    {
        return $this->setData('number_of_attempts', $value);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): ?bool
    {
        return $this->getData('status');
    }

    /**
     * @inheritDoc
     */
    public function setStatus(bool $value): WebhookQueueEntityInterface
    {
        return $this->setData('status', $value);
    }

    /**
     * @inheritDoc
     */
    public function getWebsiteId(): ?int
    {
        return $this->getData('website_id');
    }

    /**
     * @inheritDoc
     */
    public function setWebsiteId(int $value): WebhookQueueEntityInterface
    {
        return $this->setData('website_id', $value);
    }
}
