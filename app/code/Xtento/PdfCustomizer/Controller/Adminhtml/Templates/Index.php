<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-09-06T13:06:01+00:00
 * File:          Controller/Adminhtml/Templates/Index.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Xtento\PdfCustomizer\Helper\Module;
use Xtento\PdfCustomizer\Model\Files\Synchronization;
use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Index extends Templates
{

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var Synchronization
     */
    public $synchronization;

    /**
     * @var Context
     */
    public $context;

    /**
     * @var Module
     */
    public $moduleHelper;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param Module $moduleHelper
     * @param Synchronization $synchronization
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        Module $moduleHelper,
        Synchronization $synchronization
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->synchronization = $synchronization;
        $this->context = $context;
        $this->moduleHelper = $moduleHelper;
        parent::__construct($context, $coreRegistry);
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // Check module status
        $healthCheck = $this->healthCheck();
        if ($healthCheck !== true) {
            $resultRedirect = $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
            );
            return $resultRedirect->setPath($healthCheck);
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage)->getConfig()->getTitle()->prepend(__('PDF Customizer - Templates'));

        return $resultPage;
    }

    protected function healthCheck()
    {
        if (!$this->moduleHelper->confirmEnabled(true) || !$this->moduleHelper->isModuleEnabled()) {
            if ($this->getRequest()->getActionName() !== 'disabled') {
                return '*/templates/disabled';
            }
        }
        if (!class_exists('\Mpdf\Mpdf')) {
            return '*/templates/mpdf';
        }
        if (!extension_loaded('mbstring')) {
            return '*/templates/mbstring';
        }
        return true;
    }
}
