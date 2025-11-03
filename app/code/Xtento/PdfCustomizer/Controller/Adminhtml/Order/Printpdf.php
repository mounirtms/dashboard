<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-06-05T18:47:51+00:00
 * File:          Controller/Adminhtml/Order/Printpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;

class Printpdf extends AbstractPdf
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::sales_order';

    /**
     * @var OrderCollectionFactory
     */
    public $collectionFactory;

    /**
     * @var ShipmentCollectionFactory
     */
    private $shipmentCollectionFactory;

    /**
     * @var InvoiceCollectionFactory
     */
    private $invoiceCollectionFactory;

    /**
     * @var CreditmemoCollectionFactory
     */
    private $creditmemoCollectionFactory;

    /**
     * Printpdf constructor.
     *
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param GeneratePdf $generatePdfHelper
     * @param OrderCollectionFactory $collectionFactory
     * @param InvoiceCollectionFactory $invoiceCollectionFactory
     * @param ShipmentCollectionFactory $shipmentCollectionFactory
     * @param CreditmemoCollectionFactory $creditmemoCollectionFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        GeneratePdf $generatePdfHelper,
        OrderCollectionFactory $collectionFactory,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        ShipmentCollectionFactory $shipmentCollectionFactory,
        CreditmemoCollectionFactory $creditmemoCollectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->creditmemoCollectionFactory = $creditmemoCollectionFactory;
        parent::__construct($context, $fileFactory, $generatePdfHelper);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $sourceField = 'order_id';
        $entity = $this->getRequest()->getParam('entity', null);
        $sourceId = $this->getRequest()->getParam($sourceField, null);

        if ($entity == TemplateType::TYPE_ORDER) {
            return $this->returnFile(OrderRepositoryInterface::class, $sourceField);
        }
        if ($entity == TemplateType::TYPE_INVOICE) {
            $invoices = $this->invoiceCollectionFactory->create()->setOrderFilter(['in' => [$sourceId]]);
            if (!$invoices->getSize()) {
                $this->messageManager->addErrorMessage(__('No invoices exist for this order. Cannot print invoice.'));
                return $this->_redirect('sales/order/view', ['order_id' => $sourceId]);
            }
            return $this->returnFile(InvoiceRepositoryInterface::class, $sourceField, $invoices->getFirstItem() ? $invoices->getFirstItem()->getId() : false);
        }
        if ($entity == TemplateType::TYPE_SHIPMENT) {
            $shipments = $this->shipmentCollectionFactory->create()->setOrderFilter(['in' => [$sourceId]]);
            if (!$shipments->getSize()) {
                $this->messageManager->addErrorMessage(__('No shipments exist for this order. Cannot print shipment.'));
                return $this->_redirect('sales/order/view', ['order_id' => $sourceId]);
            }
            return $this->returnFile(ShipmentRepositoryInterface::class, $sourceField, $shipments->getFirstItem() ? $shipments->getFirstItem()->getId() : false);
        }
        if ($entity == TemplateType::TYPE_CREDIT_MEMO) {
            $creditmemos = $this->creditmemoCollectionFactory->create()->setOrderFilter(['in' => [$sourceId]]);
            if (!$creditmemos->getSize()) {
                $this->messageManager->addErrorMessage(__('No credit memos exist for this order. Cannot print credit memo.'));
                return $this->_redirect('sales/order/view', ['order_id' => $sourceId]);
            }
            return $this->returnFile(CreditmemoRepositoryInterface::class, $sourceField, $creditmemos->getFirstItem() ? $creditmemos->getFirstItem()->getId() : false);
        }
    }
}
