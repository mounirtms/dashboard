<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\InvoiceCommentCreationInterface;
use Magento\Sales\Api\Data\InvoiceCreationArgumentsInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Exception\CouldNotInvoiceException;
use Magento\Sales\Exception\DocumentValidationException;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\NotifierInterface;
use Magento\Sales\Model\Order\InvoiceDocumentFactory;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use Magento\Sales\Model\Order\PaymentAdapterInterface;
use Magento\Sales\Model\Order\Validation\InvoiceOrderInterface as InvoiceOrderValidator;
use Psr\Log\LoggerInterface;

class InvoiceOrder implements InvoiceOrderInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var InvoiceDocumentFactory
     */
    private $invoiceDocumentFactory;

    /**
     * @var PaymentAdapterInterface
     */
    private $paymentAdapter;

    /**
     * @var OrderStateResolverInterface
     */
    private $orderStateResolver;

    /**
     * @var OrderConfig
     */
    private $config;

    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * @var InvoiceOrderValidator
     */
    private $invoiceOrderValidator;

    /**
     * @var NotifierInterface
     */
    private $notifierInterface;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * InvoiceOrder constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceDocumentFactory $invoiceDocumentFactory
     * @param PaymentAdapterInterface $paymentAdapter
     * @param OrderStateResolverInterface $orderStateResolver
     * @param OrderConfig $config
     * @param InvoiceRepository $invoiceRepository
     * @param InvoiceOrderValidator $invoiceOrderValidator
     * @param NotifierInterface $notifierInterface
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResourceConnection          $resourceConnection,
        OrderRepositoryInterface    $orderRepository,
        InvoiceDocumentFactory      $invoiceDocumentFactory,
        PaymentAdapterInterface     $paymentAdapter,
        OrderStateResolverInterface $orderStateResolver,
        OrderConfig                 $config,
        InvoiceRepository           $invoiceRepository,
        InvoiceOrderValidator       $invoiceOrderValidator,
        NotifierInterface           $notifierInterface,
        LoggerInterface             $logger
    ) {
        $this->resourceConnection     = $resourceConnection;
        $this->orderRepository        = $orderRepository;
        $this->invoiceDocumentFactory = $invoiceDocumentFactory;
        $this->paymentAdapter         = $paymentAdapter;
        $this->orderStateResolver     = $orderStateResolver;
        $this->config                 = $config;
        $this->invoiceRepository      = $invoiceRepository;
        $this->invoiceOrderValidator  = $invoiceOrderValidator;
        $this->notifierInterface      = $notifierInterface;
        $this->logger                 = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(
        $orderId,
        $capture = false,
        array $items = [],
        $notify = false,
        $appendComment = false,
        InvoiceCommentCreationInterface $comment = null,
        InvoiceCreationArgumentsInterface $arguments = null
    ): int {
        $connection = $this->resourceConnection->getConnection('sales');
        $order      = $this->orderRepository->get($orderId);

        $invoice = $this->invoiceDocumentFactory->create(
            $order,
            $items,
            $comment,
            ($appendComment && $notify),
            $arguments
        );

        $errorMessages = $this->invoiceOrderValidator->validate(
            $order,
            $invoice,
            $capture,
            $items,
            $notify,
            $appendComment,
            $comment,
            $arguments
        );

        if ($errorMessages->hasMessages()) {
            throw new DocumentValidationException(
                __("Invoice Document Validation Error(s):\n" . implode("\n", $errorMessages->getMessages()))
            );
        }
        $connection->beginTransaction();
        try {
            if ($capture) {
                $order = $this->paymentAdapter->pay($order, $invoice, $capture);
            } else {
                $invoiceItems = $invoice->getAllItems() ?? [];
                $this->calculateOrderItemsTotals($invoiceItems);
                $this->calculateOrderTotals($order, $invoice);
            }
            $order->setState(
                $this->orderStateResolver->getStateForOrder($order, [OrderStateResolverInterface::IN_PROGRESS])
            );
            $order->setStatus($this->config->getStateDefaultStatus($order->getState()));
            // Set state based on capture flag
            $invoice->setState($capture ? Invoice::STATE_PAID : Invoice::STATE_OPEN);

            $this->invoiceRepository->save($invoice);
            $this->orderRepository->save($order);

            $connection->commit();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $connection->rollBack();
            throw new CouldNotInvoiceException(
                __('Could not save an invoice, see error log for details')
            );
        }
        if ($notify) {
            if (!$appendComment) {
                $comment = null;
            }
            $this->notifierInterface->notify($order, $invoice, $comment);
        }

        return (int)$invoice->getEntityId();
    }

    /**
     * Calculates Order totals according to given Invoice.
     *
     * @param OrderInterface $order
     * @param InvoiceInterface $invoice
     *
     * @return void
     */
    protected function calculateOrderTotals(
        OrderInterface   $order,
        InvoiceInterface $invoice
    ): void {
        $order->setTotalInvoiced(
            $order->getTotalInvoiced() + $invoice->getGrandTotal()
        );
        $order->setBaseTotalInvoiced(
            $order->getBaseTotalInvoiced() + $invoice->getBaseGrandTotal()
        );

        $order->setSubtotalInvoiced(
            $order->getSubtotalInvoiced() + $invoice->getSubtotal()
        );
        $order->setBaseSubtotalInvoiced(
            $order->getBaseSubtotalInvoiced() + $invoice->getBaseSubtotal()
        );

        $order->setTaxInvoiced(
            $order->getTaxInvoiced() + $invoice->getTaxAmount()
        );
        $order->setBaseTaxInvoiced(
            $order->getBaseTaxInvoiced() + $invoice->getBaseTaxAmount()
        );

        $order->setDiscountTaxCompensationInvoiced(
            $order->getDiscountTaxCompensationInvoiced() + $invoice->getDiscountTaxCompensationAmount()
        );
        $order->setBaseDiscountTaxCompensationInvoiced(
            $order->getBaseDiscountTaxCompensationInvoiced() + $invoice->getBaseDiscountTaxCompensationAmount()
        );

        $order->setShippingTaxInvoiced(
            $order->getShippingTaxInvoiced() + $invoice->getShippingTaxAmount()
        );
        $order->setBaseShippingTaxInvoiced(
            $order->getBaseShippingTaxInvoiced() + $invoice->getBaseShippingTaxAmount()
        );

        $order->setShippingInvoiced(
            $order->getShippingInvoiced() + $invoice->getShippingAmount()
        );
        $order->setBaseShippingInvoiced(
            $order->getBaseShippingInvoiced() + $invoice->getBaseShippingAmount()
        );

        $order->setDiscountInvoiced(
            $order->getDiscountInvoiced() + $invoice->getDiscountAmount()
        );
        $order->setBaseDiscountInvoiced(
            $order->getBaseDiscountInvoiced() + $invoice->getBaseDiscountAmount()
        );

        $order->setBaseTotalInvoicedCost(
            $order->getBaseTotalInvoicedCost() + $invoice->getBaseCost()
        );
    }

    /**
     * Calculates totals of Order Items according to given Invoice Items.
     *
     * @param InvoiceItemInterface[] $items
     *
     * @return void
     */
    protected function calculateOrderItemsTotals(array $items): void
    {
        foreach ($items as $item) {
            if ($item->isDeleted()) {
                continue;
            }

            if ($item->getQty() > 0) {
                $item->register();
            } else {
                $item->isDeleted(true);
            }
        }
    }
}
