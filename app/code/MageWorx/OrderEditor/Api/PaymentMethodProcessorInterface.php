<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Api;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use MageWorx\OrderEditor\Api\Data\PaymentMethodProcessorDataInterface;

interface PaymentMethodProcessorInterface extends PaymentMethodProcessorDataInterface
{
    /**
     * Check order and payment to resolve is need to reauthorize that order (typically after edit).
     *
     * @return bool
     */
    public function isReauthorizationRequired(): bool;

    /**
     * Check is vault available for the payment method from order.
     *
     * @return bool
     */
    public function isVaultAvailable(): bool;

    /**
     * Set flag from anywhere to use vault for reauthorization.
     * Without that flag reauthorization will be skipped during order edit process.
     *
     * @return PaymentMethodProcessorInterface
     */
    public function setUseVaultForReauthorizationFlag(): PaymentMethodProcessorInterface;
}
