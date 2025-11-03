<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Order/Massaction/PrintpdfShipment.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ChildCollectionFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\App\ResponseInterface;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Ui\Component\MassAction\Filter;
use Xtento\PdfCustomizer\Helper\GeneratePdf;

/**
 * Class Printpdf
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class PrintpdfShipment extends AbstractMassAction
{
    /**
     * @var ChildCollectionFactory
     */
    private $childCollectionFactory;

    /**
     * PrintpdfShipment constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param ForwardFactory $resultForwardFactory
     * @param GeneratePdf $generatePdfHelper
     * @param OrderCollectionFactory $collectionFactory
     * @param ChildCollectionFactory $childCollectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        ForwardFactory $resultForwardFactory,
        GeneratePdf $generatePdfHelper,
        OrderCollectionFactory $collectionFactory,
        ChildCollectionFactory $childCollectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->childCollectionFactory = $childCollectionFactory;
        parent::__construct($context, $filter, $fileFactory, $resultForwardFactory, $generatePdfHelper);
    }

    /**
     * @param AbstractCollection $collection
     * @return ResponseInterface
     */
    //@codingStandardsIgnoreLine
    protected function massAction(AbstractCollection $collection)
    {
        $orderIds = $collection->getAllIds();

        $shipments = $this->childCollectionFactory->create()->setOrderFilter(['in' => $orderIds]);

        if (!$shipments->getSize()) {
            $this->messageManager->addErrorMessage(__('There are no shipments related to selected orders.'));
            return $this->_redirect('sales/order/');
        }

        $this->abstractCollection = $shipments;
        return $this->generateFile();
    }
}
