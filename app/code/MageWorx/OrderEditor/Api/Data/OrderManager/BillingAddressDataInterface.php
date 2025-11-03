<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

/**
 * Interface BillingAddressDataInterface
 *
 * Includes all billing address data.
 * Default attributes located on first level and available directly by own codes.
 * Additional attributes stored in the custom_attributes container.
 */
interface BillingAddressDataInterface extends CustomAttributesDataInterface, OrderAddressInterface
{

}
