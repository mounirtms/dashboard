<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Order;

use MageWorx\OrderEditor\Model\Order\SalesProcessor\DeleteAndCreateSalesProcessor;

/**
 * Class Sales
 *
 * Used to perform operations with related entities like: invoice, creditmemo, shipment
 */
class Sales extends DeleteAndCreateSalesProcessor
{
    // Backward compatibility for old-styled sales processor
}
