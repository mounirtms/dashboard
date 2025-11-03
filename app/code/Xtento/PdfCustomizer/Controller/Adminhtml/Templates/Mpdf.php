<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-09-06T13:06:43+00:00
 * File:          Controller/Adminhtml/Templates/Mpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\Module;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;

class Mpdf extends Templates
{
    /**
     * @var Module
     */
    public $moduleHelper;

    /**
     * Mpdf constructor.
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
                    'Warning: You forgot to install mPDF. Please see our <a href="https://support.xtento.com/wiki/Magento_2_Extensions:PDF_Customizer#Setting_up_the_extension" target="_blank">wiki</a> for more information on how to fix this. Then, refresh this page.'
                )
            ]
        );

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        return $resultPage;
    }

    protected function healthCheck()
    {
        if (!class_exists('\Mpdf\Mpdf')) {
            if ($this->getRequest()->getActionName() !== 'mpdf') {
                return '*/templates/mpdf';
            }
        } else {
            if ($this->getRequest()->getActionName() == 'mpdf') {
                return '*/templates/index';
            }
        }
        return true;
    }
}
