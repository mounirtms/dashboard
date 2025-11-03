<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;

class InvoiceFinder implements \MageWorx\OrderEditor\Api\InvoiceFinderInterface
{
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        InvoiceRepositoryInterface   $invoiceRepository,
        OrderItemRepositoryInterface $orderItemRepository,
        SearchCriteriaBuilder        $searchCriteriaBuilder
    ) {
        $this->invoiceRepository     = $invoiceRepository;
        $this->orderItemRepository   = $orderItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getInvoiceByOrderItemId(int $orderItemId, ?int $orderId, ?float $qty = 1): array
    {
        if ($orderId === null) {
            $orderItem = $this->orderItemRepository->get($orderItemId);
            $orderId = $orderItem->getOrderId();
        }

        $invoicesList = $this->invoiceRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('order_id', $orderId)
                ->addFilter('state', [1,2], 'in')
                ->create()
        );

        /** @var \Magento\Sales\Model\Order\Invoice $invoiceItems */
        $invoiceItems = $invoicesList->getItems();
        $processedQty = 0;
        $invoices     = [];
        foreach ($invoiceItems as $invoice) {
            /** @var \Magento\Sales\Model\ResourceModel\Order\Invoice\Item\Collection $itemsCollection */
            $itemsCollection = $invoice->getItemsCollection();
            $invoiceItem     = $itemsCollection->getItemByColumnValue('order_item_id', $orderItemId);

            if ($invoiceItem === null) {
                continue; // There are no specified item in that invoice
            }

            $invoices[] = $invoice;
            $qtyLeft    = $qty - $processedQty;
            if ($invoiceItem->getData('qty') >= $qtyLeft) {
                break;
            } else {
                $processedQty += $invoiceItem->getData('qty');
            }
        }

        return $invoices;
    }
}
