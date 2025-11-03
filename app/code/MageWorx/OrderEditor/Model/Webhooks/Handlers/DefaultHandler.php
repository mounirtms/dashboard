<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Webhooks\Handlers;

use Magento\Framework\Exception\LocalizedException;
use MageWorx\OrderEditor\Api\WebhookActionHandlerInterface;

class DefaultHandler implements WebhookActionHandlerInterface
{
    /**
     * @var \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterfaceFactory
     */
    protected $queueEntityFactory;

    /**
     * @var \MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity
     */
    protected $queueEntityResource;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonSerializer;

    /**
     * @var array|string[]
     */
    private $sensitiveDataKeys;


    /**
     * @param \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterfaceFactory $queueEntityFactory
     * @param \MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity $webhookQueueEntityResource
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param array|string[] $sensitiveDataKeys
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\Data\WebhookQueueEntityInterfaceFactory $queueEntityFactory,
        \MageWorx\OrderEditor\Model\ResourceModel\WebhookQueueEntity $webhookQueueEntityResource,
        \Magento\Framework\Serialize\Serializer\Json $json,
        array $sensitiveDataKeys = []
    ) {
        $this->queueEntityFactory  = $queueEntityFactory;
        $this->queueEntityResource = $webhookQueueEntityResource;
        $this->jsonSerializer      = $json;
        $this->sensitiveDataKeys   = $sensitiveDataKeys;
    }

    /**
     * @inheritDoc
     */
    public function run(\Magento\Framework\DataObject $dataObject, string $action): void
    {
        $order = $dataObject;
        if (!$order instanceof \Magento\Sales\Model\Order) {
            throw new LocalizedException(
                __('Incorrect object: "%1" received when the "\Magento\Sales\Model\Order" expected', get_class($order))
            );
        }

        $queueEntity = $this->queueEntityFactory->create(
            [
                'data' => [
                    'event_name'         => $action,
                    'created_at'         => time(),
                    'data_serialized'    => $this->getJsonDataFromObject($order),
                    'number_of_attempts' => 0,
                    'status'             => false,
                    'website_id'         => $order->getStore()->getWebsiteId()
                ]
            ]
        );
        $queueEntity->setDataChanges(true);
        $queueEntity->isObjectNew(true);

        $this->queueEntityResource->save($queueEntity);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getJsonDataFromObject(\Magento\Sales\Model\Order $order): string
    {
        $data = $order->getData();

        /**
         * Remove stored data, get it from database
         */
        $items = $order->getAllItems();
        unset($data['items']);
        foreach ($items as $id => $item) {
            $data['items'][$id] = $item->getData();
        }

        /**
         * Remove stored data, get it from database
         */
        $addresses = $order->getAddressesCollection();
        unset($data['addresses']);
        foreach ($addresses as $id => $address) {
            $data['addresses'][$id] = $address->getData();
        }

        foreach ($this->sensitiveDataKeys as $key) {
            unset($data[$key]);
        }

        $dataJson = $this->jsonSerializer->serialize($data);
        if (!$dataJson) {
            throw new LocalizedException(__('Unable to serialize data'));
        }

        return $dataJson;
    }
}
