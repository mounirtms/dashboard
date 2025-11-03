<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Invoice\PaymentMethodProcessor;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use MageWorx\OrderEditor\Api\PaymentMethodProcessorInterface;

class DefaultProcessor implements \MageWorx\OrderEditor\Api\PaymentMethodProcessorInterface
{
    /**
     * @var OrderInterface
     */
    protected $order;
    /**
     *
     * @var InvoiceInterface
     */
    protected $invoice;

    /**
     * @var string
     */
    protected $payment;

    /**
     * @param OrderInterface $order
     * @param InvoiceInterface $invoice
     * @param string $payment
     */
    public function __construct(
        OrderInterface $order,
        InvoiceInterface $invoice,
        string $payment
    ) {
        $this->order = $order;
        $this->invoice = $invoice;
        $this->payment = $payment;
    }

    /**
     * @inheritDoc
     */
    public function getInvoice(): InvoiceInterface
    {
        return $this->invoice;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodCode(): string
    {
        return $this->payment;
    }

    /**
     * @inheritDoc
     */
    public function isReauthorizationRequired(): bool
    {
        // TODO: Implement isReauthorizationRequired() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isVaultAvailable(): bool
    {
        // TODO: Implement isVaultAvailable() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function setUseVaultForReauthorizationFlag(): PaymentMethodProcessorInterface
    {
        // TODO: Implement setUseVaultForReauthorizationFlag() method.
        return $this;
    }
}
