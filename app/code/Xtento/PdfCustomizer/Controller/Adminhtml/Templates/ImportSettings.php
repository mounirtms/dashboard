<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Templates/ImportSettings.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\Tools;

class ImportSettings extends Templates
{
    /**
     * @var \Xtento\PdfCustomizer\Helper\Tools
     */
    protected $toolsHelper;

    /**
     * ImportSettings constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Tools $toolsHelper
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Tools $toolsHelper
    ) {
        $this->toolsHelper = $toolsHelper;
        parent::__construct($context, $coreRegistry);
    }

    /**
     * Import action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\Controller\Result\Raw
     * @throws \Exception
     */
    public function execute()
    {
        // Check for uploaded file
        $settingsFile = $this->_request->getFiles('settings_file');
        if (!isset($settingsFile['tmp_name']) || empty($settingsFile['tmp_name'])) {
            $this->messageManager->addErrorMessage(__('No settings file has been uploaded.'));
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('*/*/importExport');
            return $resultRedirect;
        }
        $uploadedFile = $settingsFile['tmp_name'];
        // Check if data should be updated or added
        $updateByName = false;
        if ($this->getRequest()->getPost('update_by_name', false) == 'on') {
            $updateByName = true;
        }
        // Counters
        $addedCounter = ['templates' => 0];
        $updatedCounter = ['templates' => 0];
        $errorMessage = "";
        // Load JSON settings
        $jsonData = file_get_contents($uploadedFile);
        if (!$this->toolsHelper->importSettingsFromJson($jsonData, $addedCounter, $updatedCounter, $updateByName, $errorMessage)) {
            $this->messageManager->addErrorMessage($errorMessage);
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('*/*/importExport');
            return $resultRedirect;
        }
        // Done
        $this->messageManager->addSuccessMessage(__('%1 templates have been added, %2 templates have been updated.', $addedCounter['templates'], $updatedCounter['templates']));
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/importExport');
        return $resultRedirect;
    }
}