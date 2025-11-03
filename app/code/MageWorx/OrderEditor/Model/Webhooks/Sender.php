<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Webhooks;

use MageWorx\OrderEditor\Api\Data;
use MageWorx\OrderEditor\Helper\Data as Helper;

class Sender implements \MageWorx\OrderEditor\Api\WebhookSenderInterface
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var \Magento\Framework\HTTP\ClientInterface
     */
    private $httpClient;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper,
        \Magento\Framework\HTTP\ClientInterface $httpClient
    ) {
        $this->helper = $helper;
        $this->httpClient = $httpClient;
    }

    /**
     * @inheritDoc
     */
    public function send(Data\WebhookQueueEntityInterface $webhookQueueEntity): bool
    {
        $url = $this->helper->getWebhookEndpoint();
        $body = $webhookQueueEntity->getDataSerialized();
        $this->httpClient->addHeader('Content-length', strlen($body));
        $this->httpClient->addHeader('Content-Type', 'application/json');
        $this->httpClient->addHeader('Expect', ''); // Prevent 100-continue

        if ($this->helper->isWebhookAuthorizationRequired()) {
            $this->httpClient->setCredentials($this->helper->getWebhookLogin(), $this->helper->getWebhookPassword());
        }

        $this->httpClient->post($url, $body);
        $responseStatus = $this->httpClient->getStatus();

        return $responseStatus < 400;
    }
}
