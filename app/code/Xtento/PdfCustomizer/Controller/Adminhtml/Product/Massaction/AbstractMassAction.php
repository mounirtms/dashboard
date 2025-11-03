<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-10-15T13:06:47+00:00
 * File:          Controller/Adminhtml/Product/Massaction/AbstractMassAction.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Product\Massaction;

use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction as SalesAbstractMassAction;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Xtento\PdfCustomizer\Helper\GeneratePdf;

/**
 * Class AbstractMassAction
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Product\Massaction
 */
abstract class AbstractMassAction extends SalesAbstractMassAction
{
    /**
     * @var FileFactory
     */
    public $fileFactory;

    /**
     * @var AbstractCollection
     */
    public $abstractCollection;

    /**
     * @var GeneratePdf
     */
    protected $generatePdfHelper;

    /**
     * AbstractMassAction constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param GeneratePdf $generatePdfHelper
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        GeneratePdf $generatePdfHelper
    ) {
        $this->fileFactory = $fileFactory;
        $this->generatePdfHelper = $generatePdfHelper;
        parent::__construct($context, $filter);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function generateFile()
    {
        $templateId = $this->getRequest()->getParam('template_id', null);
        $pdf = $this->generatePdfHelper->generatePdfForCollection($this->abstractCollection, $templateId);
        if ($pdf === false) {
            $this->messageManager->addErrorMessage(__('Did you specify a default template? No PDF Template found or there are no printable documents related to selected products.'));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $file = $this->fileFactory->create(
            $pdf['filename'],
            $pdf['output'],
            DirectoryList::TMP,
            'application/pdf'
        );

        return $file;
    }

    /**
     * @return bool
     */
    //@codingStandardsIgnoreLine
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Catalog::products');
    }
}
