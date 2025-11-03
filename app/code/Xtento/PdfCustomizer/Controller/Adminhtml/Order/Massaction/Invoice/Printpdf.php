<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2021-04-03T04:41:13+00:00
 * File:          Controller/Adminhtml/Order/Massaction/Invoice/Printpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction\Invoice;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Ui\Component\MassAction\Filter;
use Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction\AbstractMassAction;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\App\ResponseInterface;
use Xtento\PdfCustomizer\Helper\GeneratePdf;

/**
 * Class Printpdf
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction\Invoice
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Printpdf extends AbstractMassAction
{
    /**
     * Printpdf constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param ForwardFactory $resultForwardFactory
     * @param GeneratePdf $generatePdfHelper
     * @param InvoiceCollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        ForwardFactory $resultForwardFactory,
        GeneratePdf $generatePdfHelper,
        InvoiceCollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $filter, $fileFactory, $resultForwardFactory, $generatePdfHelper);
    }

    /**
     * @param AbstractCollection $collection
     * @return ResponseInterface
     */
    //@codingStandardsIgnoreLine
    protected function massAction(AbstractCollection $collection)
    {
        $this->abstractCollection = $collection;
        return $this->generateFile();
    }
}
