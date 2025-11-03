<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Webhooks\Processors;

use Magento\Framework\Exception\NoSuchEntityException;
use MageWorx\OrderEditor\Api\WebhookActionHandlerInterface;

class DefaultProcessor implements \MageWorx\OrderEditor\Api\WebhookProcessorInterface
{
    /**
     * @var WebhookActionHandlerInterface[]
     */
    protected $actionHandlers;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $actionHandlers
     */
    public function __construct(
        array $actionHandlers = []
    ) {
        $this->actionHandlers = $actionHandlers;
    }

    /**
     * @inheritDoc
     */
    public function process(string $actionCode, \Magento\Framework\DataObject $object): void
    {
        $handler = $this->getHandler($actionCode);
        if ($handler) {
            $handler->run($object, $actionCode);
        }
    }

    /**
     * @param string $action
     * @return WebhookActionHandlerInterface
     * @throws NoSuchEntityException
     */
    public function getHandler(string $action): WebhookActionHandlerInterface
    {
        if (isset($this->actionHandlers[$action])
            && $this->actionHandlers[$action] instanceof WebhookActionHandlerInterface
        ) {
            return $this->actionHandlers[$action];
        }

        throw new NoSuchEntityException(__('No handler for the "%1" action', $action));
    }
}
