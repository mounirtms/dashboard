<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Catch all events and direct it to webhooks processor
 */
class WebhooksCollector implements ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \MageWorx\OrderEditor\Api\WebhookProcessorInterface
     */
    protected $webhookProcessor;

    /**
     * @param \MageWorx\OrderEditor\Api\WebhookProcessorInterface $webhookProcessor
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\WebhookProcessorInterface $webhookProcessor,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->webhookProcessor = $webhookProcessor;
        $this->logger           = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $actualAction = $observer->getData('action');
        if ($actualAction) {
            try {
                $object = $observer->getData('object');
                if (!empty($object)) {
                    $this->webhookProcessor->process($actualAction, $object);
                } else {
                    throw new LocalizedException(
                        __('Unable to send webhook: empty object for the %1 action.', $actualAction)
                    );
                }
            } catch (LocalizedException $localizedException) {
                $this->logger->alert($localizedException->getLogMessage());
            }
        } else {
            $this->logger->notice(__('Empty webhook action code in %1 observer', $observer->getEventName()));
        }
    }
}
