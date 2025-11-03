<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Plugin;

use Magento\Payment\Model\MethodInterface;

/**
 * As a result of using an "invoice without capture" feature of the OrdersGrid module some invoices could
 * stay in "pending" status but with offline payment method in use. For that case we are removing come-case-incorrect
 * flag "canCapture" in value of false to give an option for the customer (admin) to make capture for the invoice
 * made with offline payment method.
 */
class FixCaptureOfflinePaymentMethods
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $httpRequest;

    /**
     * @var array
     */
    protected $restrictedInvoiceActionNames;

    /**
     * @param \Magento\Framework\App\Request\Http $httpRequest
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $httpRequest,
        array                               $restrictedInvoiceActionNames = []
    ) {
        $this->httpRequest                  = $httpRequest;
        $this->restrictedInvoiceActionNames = $restrictedInvoiceActionNames;
    }

    /**
     * @param MethodInterface $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanCapture(
        MethodInterface $subject,
        bool            $result
    ): bool {
        if (!$result && $subject->isOffline() && $this->isOrdersGridInAction()) {
            return true;
        }

        return $result;
    }

    /**
     * We suppose to fix the capture action on all pages where it can be used by the customer (admin) as a result of
     * manipulation with OrdersGrid module features (invoice and capture, capture only etc.).
     *
     * @return bool
     */
    private function isOrdersGridInAction(): bool
    {
        $isInvoiceAction = $this->httpRequest->getModuleName() === 'sales' &&
            $this->httpRequest->getControllerName() === 'order_invoice' &&
            !in_array($this->httpRequest->getActionName(), $this->getRestrictedInvoiceActionNames());

        $isGridMassAction = $this->httpRequest->getModuleName() === 'mageworx_ordersgrid' &&
            $this->httpRequest->getControllerName() === 'order_grid';

        return $isGridMassAction || $isInvoiceAction;
    }

    /**
     * @return string[]
     */
    private function getRestrictedInvoiceActionNames(): array
    {
        return $this->restrictedInvoiceActionNames;
    }
}
