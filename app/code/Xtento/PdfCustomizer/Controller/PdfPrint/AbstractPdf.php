<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-11-13T09:21:54+00:00
 * File:          Controller/PdfPrint/AbstractPdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\PdfPrint;

use Magento\Framework\App\Action\Action;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;

abstract class AbstractPdf extends Action
{
    /**
     * @var FileFactory
     */
    public $fileFactory;

    /**
     * @var GeneratePdf
     */
    protected $generatePdfHelper;

    /**
     * Product constructor.
     *
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param GeneratePdf $generatePdfHelper
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        GeneratePdf $generatePdfHelper
    ) {
        $this->fileFactory = $fileFactory;
        $this->generatePdfHelper = $generatePdfHelper;
        parent::__construct($context);
    }

    /**
     * @param $entity
     * @param $sourceField
     * @param bool $sourceId
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Zend_Pdf_Exception
     */
    public function returnFile($entity, $sourceField, $sourceId = false)
    {
        $templateId = $this->getRequest()->getParam('template_id', null);
        if (!$sourceId) {
            $sourceId = $this->getRequest()->getParam($sourceField, null);
        }

        $pdf = $this->generatePdfHelper->generatePdfForObject($entity, $sourceId, $templateId);
        if ($pdf === false) {
            $this->messageManager->addErrorMessage(__('Did you specify a default template? No PDF Template found or there are no printable documents related to selected objects.'));
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
}