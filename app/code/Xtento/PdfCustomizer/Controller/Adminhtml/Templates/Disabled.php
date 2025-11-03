<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Templates/Disabled.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\Module;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;

class Disabled extends Templates
{
    /**
     * @var Module
     */
    public $moduleHelper;

    /**
     * Disabled constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Module $moduleHelper
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Module $moduleHelper
    ) {
        $this->moduleHelper = $moduleHelper;
        parent::__construct($context, $coreRegistry);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        $healthCheck = $this->healthCheck();
        if ($healthCheck !== true) {
            $resultRedirect = $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
            );
            return $resultRedirect->setPath($healthCheck);
        }

        $this->messageManager->addWarningMessage(
            __(
                'The extension is currently disabled. Please go to System > XTENTO Extensions > PDF Customizer to enable it. After that access the module at Stores > Manage PDF Templates again.'
            )
        );
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        return $resultPage;
    }

    protected function healthCheck()
    {
        if (!$this->moduleHelper->confirmEnabled(true) || !$this->moduleHelper->isModuleEnabled()) {
            if ($this->getRequest()->getActionName() !== 'disabled') {
                return '*/templates/disabled';
            }
        } else {
            if ($this->getRequest()->getActionName() == 'disabled') {
                return '*/templates/index';
            }
        }
        return true;
    }
}
