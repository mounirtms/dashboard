<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Api;

interface WebhookActionHandlerInterface
{
    /**
     * @param \Magento\Framework\DataObject $dataObject
     * @param string $action
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function run(\Magento\Framework\DataObject $dataObject, string $action): void;
}
