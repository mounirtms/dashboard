<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-29T13:52:40+00:00
 * File:          Controller/Adminhtml/Product/AbstractPdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Product;

use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class AbstractPdf
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Order
 * @SuppressWarnings(CouplingBetweenObjects)
 */
abstract class AbstractPdf extends Action
{
    /**
     * @var FileFactory
     */
    public $fileFactory;

    /**
     * @var int
     */
    public $templateId;

    /**
     * @var PdfTemplate
     */
    public $templateModel;

    /**
     * @var int
     */
    public $sourceId;

    /**
     * @var object
     */
    public $sourceModel;

    /**
     * @var GeneratePdf
     */
    protected $generatePdfHelper;

    /**
     * AbstractPdf constructor.
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
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function returnFile($entity, $sourceField)
    {
        $templateId = $this->getRequest()->getParam('template_id', null);
        $sourceId = $this->getRequest()->getParam($sourceField, null);

        $pdf = $this->generatePdfHelper->generatePdfForObject($entity, $sourceId, $templateId);
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
     * @param $entity
     * @param $sourceId
     * @param $templateModel
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function returnFileInline($entity, $sourceId, $templateModel)
    {
        $pdf = $this->generatePdfHelper->generatePdfForObject($entity, $sourceId, $templateModel);

        /** @var \Magento\Framework\Controller\Result\Raw $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $resultPage->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Content-type', 'application/pdf', true)
            ->setHeader('Content-Length', strlen($pdf['output']))
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-Disposition', 'inline; filename="preview.pdf"')
            ->setHeader('Last-Modified', date('r'));
        $resultPage->setContents($pdf['output']);

        // Get as JPG (see: https://stackoverflow.com/a/52677573/1320365)
        if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'admin.magento2.dev') {
            $img = new \Imagick();
            $img->setResolution(200, 200);
            $img->readImageBlob($pdf['output']);
            //$img->setImageCompressionQuality(0);
            $img->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $img->setImageCompressionQuality(100);
            $img->setImageFormat('jpg');
            $img->setImageAlphaChannel(\Imagick::VIRTUALPIXELMETHOD_WHITE);
            if ($templateModel->getData('template_paper_ori') == 2) {
                // Landscape
                $img->adaptiveResizeImage(1754, 1240);
            } else {
                $img->adaptiveResizeImage(620, 877);
            }
            $imageStr = $img->getImageBlob();
            $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
            $resultPage->setHttpResponseCode(200)
                ->setHeader('Pragma', 'public', true)
                ->setHeader('Content-type', 'image/jpeg', true)
                ->setHeader('Content-Length', strlen($imageStr))
                ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
                ->setHeader('Content-Disposition', 'inline; filename="preview.jpg"')
                ->setHeader('Last-Modified', date('r'));
            $resultPage->setContents($imageStr);
        }

        return $resultPage;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Catalog::products') || $this->_authorization->isAllowed(Templates::ADMIN_RESOURCE_VIEW);
    }
}
