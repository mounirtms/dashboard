<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Api;

/**
 * Process webhooks by action code
 */
interface WebhookProcessorInterface
{
    const ACTION_UPDATE_ORDER_ITEMS             = 'update_order_items';
    const ACTION_UPDATE_ORDER_BILLING_ADDRESS   = 'update_order_billing_address';
    const ACTION_UPDATE_ORDER_SHIPPING_ADDRESS  = 'update_order_shipping_address';
    const ACTION_UPDATE_ORDER_SHIPPING_METHOD   = 'update_order_shipping_method';
    const ACTION_UPDATE_ORDER_PAYMENT_METHOD    = 'update_order_payment_method';
    const ACTION_UPDATE_ORDER_CUSTOMER_DATA     = 'update_order_customer_data';
    const ACTION_UPDATE_ORDER_INFO              = 'update_order_info';

    /**
     * @param string $actionCode
     * @param \Magento\Framework\DataObject $object
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process(string $actionCode, \Magento\Framework\DataObject $object): void;

    /**
     * @param string $action
     * @return \MageWorx\OrderEditor\Api\WebhookActionHandlerInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getHandler(string $action): \MageWorx\OrderEditor\Api\WebhookActionHandlerInterface;
}
