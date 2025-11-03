<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\InvoiceService;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceManagementInterface;

class CheckTotals
{
    /**
     * @var
     */
    private $paymentMethodProcessorFactory;

    /**
     * @param \MageWorx\OrderEditor\Model\Invoice\PaymentMethodProcessorFactory $paymentMethodProcessorFactory
     */
    public function __construct(
        \MageWorx\OrderEditor\Model\Invoice\PaymentMethodProcessorFactory $paymentMethodProcessorFactory
    ) {
        $this->paymentMethodProcessorFactory = $paymentMethodProcessorFactory;
    }

    /**
     * @param InvoiceManagementInterface $subject
     * @param InvoiceInterface $result
     * @param OrderInterface $order
     * @param array $orderItemsQtyToInvoice
     * @return InvoiceInterface
     */
    public function afterPrepareInvoice(
        InvoiceManagementInterface $subject,
        InvoiceInterface $result,
        OrderInterface $order,
        array $orderItemsQtyToInvoice = []
    ): InvoiceInterface {
        // Here we are checking payment authorized totals with invoice totals
        // and set flag to use vault in case it a PayPal (or other available payment provider)
        $paymentMethodCode = $order->getPayment()->getMethod();
        $paymentMethodProcessor = $this->paymentMethodProcessorFactory->create(
            ['invoice' => $result, 'order' => $order, 'payment' => $paymentMethodCode]
        );

        if ($paymentMethodProcessor) {
            $needReauthorization = $paymentMethodProcessor->isReauthorizationRequired();
            if ($needReauthorization) {
                $vaultAvailable = $paymentMethodProcessor->isVaultAvailable();
                if ($vaultAvailable) {
                    $paymentMethodProcessor->setUseVaultForReauthorizationFlag();
                } else {
                    // Show error: reauthorization required but vault is not available
                    return $result;
                }
            }
        }

        return $result;
    }
}
