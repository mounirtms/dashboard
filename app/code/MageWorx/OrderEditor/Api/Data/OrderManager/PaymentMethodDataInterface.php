<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

use Magento\Framework\Api\CustomAttributesDataInterface;

interface PaymentMethodDataInterface extends CustomAttributesDataInterface
{
    const CODE = 'code';

    /**
     * Payment method code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Get payment method code
     *
     * @param string $value
     * @return PaymentMethodDataInterface
     */
    public function setCode(string $value): PaymentMethodDataInterface;
}
