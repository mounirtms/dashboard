<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Order/Massaction/PrintpdfDocuments.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction;

use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Class PrintpdfDocuments: Used for "Print All" massaction
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class PrintpdfDocuments extends AbstractMassAction
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    public $fileFactory;

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
     * AbstractMassAction constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param ForwardFactory $resultForwardFactory
     * @param GeneratePdf $generatePdfHelper
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        ForwardFactory $resultForwardFactory,
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
        parent::__construct($context, $filter, $fileFactory, $resultForwardFactory, $generatePdfHelper);
    }


    /**
     * @param AbstractCollection $collection
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function massAction(AbstractCollection $collection)
    {
        $orderIds = $collection->getAllIds();

        $shipments = $this->shipmentCollectionFactory->create()->setOrderFilter(['in' => $orderIds]);
        $invoices = $this->invoiceCollectionFactory->create()->setOrderFilter(['in' => $orderIds]);
        $creditmemos = $this->creditmemoCollectionFactory->create()->setOrderFilter(['in' => $orderIds]);

        if ($invoices->getSize()) {
            foreach ($invoices as $invoiceItem) {
                $lastItemId = $collection->getLastItem()->getId();
                $invoiceItem->setPdfOriginalId($invoiceItem->getId());
                $invoiceItem->setId($lastItemId + 1);
                $collection->addItem($invoiceItem);
            }
        }
        if ($shipments->getSize()) {
            foreach ($shipments as $shipmentItem) {
                $lastItemId = $collection->getLastItem()->getId();
                $shipmentItem->setPdfOriginalId($shipmentItem->getId());
                $shipmentItem->setId($lastItemId + 1);
                $collection->addItem($shipmentItem);
            }
        }
        if ($creditmemos->getSize()) {
            foreach ($creditmemos as $creditmemoItem) {
                $lastItemId = $collection->getLastItem()->getId();
                $creditmemoItem->setPdfOriginalId($creditmemoItem->getId());
                $creditmemoItem->setId($lastItemId + 1);
                $collection->addItem($creditmemoItem);
            }
        }

        if (empty($collection)) {
            $this->messageManager->addErrorMessage(__('There are no printable documents related to selected orders.'));
            return $this->_redirect('sales/order/');
        }

        $this->abstractCollection = $collection;
        return $this->generateFile();
    }
}
