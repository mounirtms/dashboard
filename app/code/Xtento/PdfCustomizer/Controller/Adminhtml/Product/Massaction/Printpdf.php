<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Controller/Adminhtml/Product/Massaction/Printpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Product\Massaction;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\App\ResponseInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Class Printpdf
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Product\Massaction
 */
class Printpdf extends AbstractMassAction
{
    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * Printpdf constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param GeneratePdf $generatePdfHelper
     * @param OrderCollectionFactory $collectionFactory
     * @param CollectionFactory $productCollectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        GeneratePdf $generatePdfHelper,
        OrderCollectionFactory $collectionFactory,
        CollectionFactory $productCollectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($context, $filter, $fileFactory, $generatePdfHelper);
    }

    /**
     * @param AbstractCollection $collection
     *
     * @return ResponseInterface
     */
    protected function massAction(AbstractCollection $collection)
    {
        $collection = $this->filter->getCollection(
            $this->productCollectionFactory->create()->addAttributeToSelect('*')
        );

        $this->abstractCollection = $collection;
        return $this->generateFile();
    }
}
