<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Templates/ExportSettings.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\Tools;
use Xtento\XtCore\Helper\Utils;

class ExportSettings extends Templates
{
    /**
     * @var \Xtento\PdfCustomizer\Helper\Tools
     */
    protected $toolsHelper;

    /**
     * @var Utils
     */
    protected $utilsHelper;

    /**
     * ExportSettings constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Tools $toolsHelper
     * @param Utils $utilsHelper
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Tools $toolsHelper,
        Utils $utilsHelper
    ) {
        $this->toolsHelper = $toolsHelper;
        $this->utilsHelper = $utilsHelper;
        parent::__construct($context, $coreRegistry);
    }


    /**
     * Export action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\Controller\Result\Raw
     * @throws \Exception
     */
    public function execute()
    {
        $templateIds = $this->getRequest()->getPost('template_ids', []);
        if (empty($templateIds) && empty($destinationIds)) {
            $this->messageManager->addErrorMessage(__('No templates to export specified.'));
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('*/*/importExport');
            return $resultRedirect;
        }

        $exportData = $this->toolsHelper->exportSettingsAsJson($templateIds);

        /** @var \Magento\Framework\Controller\Result\Raw $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $file = $this->utilsHelper->prepareFilesForDownload(['xtento_pdfcustomizer_settings.json' => $exportData]);
        $resultPage->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Content-type', 'application/octet-stream', true)
            ->setHeader('Content-Length', strlen($file['data']))
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file['filename'] . '"')
            ->setHeader('Last-Modified', date('r'));
        $resultPage->setContents($file['data']);
        return $resultPage;
    }
}