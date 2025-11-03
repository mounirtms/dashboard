<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Api\Data;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;

interface PaymentMethodProcessorDataInterface
{
    /**
     * @return InvoiceInterface
     */
    public function getInvoice(): InvoiceInterface;

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface;

    /**
     * @return string
     */
    public function getPaymentMethodCode(): string;
}
