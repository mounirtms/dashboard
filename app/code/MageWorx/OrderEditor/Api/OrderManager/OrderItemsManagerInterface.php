<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\OrderManager;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Item as OriginalOrderItem;

interface OrderItemsManagerInterface
{
    /**
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderItemsByOrderId(int $orderId): array;

    /**
     * @param int $orderId
     * @return \Magento\Quote\Api\Data\CartItemInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteItemsByOrderId(int $orderId): array;

    /**
     * @param int $orderId
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return \Magento\Quote\Api\Data\CartItemInterface[]
     */
    public function addItemToOrderById(int $orderId, \Magento\Quote\Api\Data\CartItemInterface $item): array;

    /**
     * @param int $orderId
     * @param \Magento\Quote\Api\Data\CartItemInterface[] $items
     * @return \Magento\Quote\Api\Data\CartItemInterface[]
     */
    public function addItemsToOrderById(int $orderId, array $items): array;

    /**
     * @param int $orderId
     * @param \Magento\Quote\Api\Data\CartItemInterface[] $items
     * @return OriginalOrderItem[]
     * @throws NoSuchEntityException
     */
    public function addItemsToOrderByIdAndReturnOrderItems(int $orderId, array $items): array;

    /**
     * @param int $orderId
     * @param int $itemId
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     * @throws Exception
     */
    public function removeItemFromOrderById(int $orderId, int $itemId): array;

    /**
     * @param int $orderId
     * @param string $itemIds
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     * @throws Exception
     */
    public function removeItemsFromOrderById(int $orderId, string $itemIds): array;

    /**
     * @param int $orderId
     * @param \MageWorx\OrderEditor\Api\Data\OrderManager\EditOrderItemDataInterface $item
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     */
    public function editItem(
        int $orderId,
        \MageWorx\OrderEditor\Api\Data\OrderManager\EditOrderItemDataInterface $item
    ): array;

    /**
     * Apply changes from corresponding quote to the order
     *
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function commit(int $orderId): \Magento\Sales\Api\Data\OrderInterface;
}
