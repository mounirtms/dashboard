<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-09-06T13:07:32+00:00
 * File:          Controller/Adminhtml/Templates/Mbstring.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\Module;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;

class Mbstring extends Templates
{
    /**
     * @var Module
     */
    public $moduleHelper;

    /**
     * Mbstring constructor.
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

        $this->messageManager->addComplexErrorMessage(
            'backendHtmlMessage',
            [
                'html' => (string)__(
                    'Warning: You forgot to install the PHP "mbstring" extension. Please contact your server admin to install it, the extension won\'t be operational without it. Also, it\'s a core requirement of Magento 2, so other things will break without it as well.'
                )
            ]
        );

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        return $resultPage;
    }

    protected function healthCheck()
    {
        if (!extension_loaded('mbstring')) {
            if ($this->getRequest()->getActionName() !== 'mbstring') {
                return '*/templates/mbstring';
            }
        } else {
            if ($this->getRequest()->getActionName() == 'mbstring') {
                return '*/templates/index';
            }
        }
        return true;
    }
}
