<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Interface ShipmentManagerInterface
 *
 * Add or remove shipments for the order after edit
 */
interface ShipmentManagerInterface
{
    /**
     * @param \MageWorx\OrderEditor\Model\Order $order
     * @return \MageWorx\OrderEditor\Model\Order
     * @throws LocalizedException
     */
    public function updateShipmentsOnOrderEdit(
        \MageWorx\OrderEditor\Model\Order $order
    ): \MageWorx\OrderEditor\Model\Order;
}
