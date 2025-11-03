<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Controller/Adminhtml/Templates/GetDefaultTemplates.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Magento\Framework\Controller\Result\JsonFactory;
use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Magento\Backend\App\Action;
use Magento\Framework\Registry;
use Xtento\PdfCustomizer\Model\Files\TemplateReader;

class GetDefaultTemplates extends Templates
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var TemplateReader
     */
    protected $templateReader;

    /**
     * GetDefaultTemplates constructor.
     *
     * @param Action\Context $context
     * @param Registry $registry
     * @param JsonFactory $jsonResultFactory
     * @param TemplateReader $templateReader
     */
    public function __construct(
        Action\Context $context,
        Registry $registry,
        JsonFactory $jsonResultFactory,
        TemplateReader $templateReader
    ) {
        $this->templateReader = $templateReader;
        $this->jsonResultFactory = $jsonResultFactory;
        parent::__construct($context, $registry);
    }

    public function getDefaultTemplates($templateType)
    {
        $templates = [];
        foreach ($this->templateReader->directoryParser(true) as $template) {
            if ($templateType == $template['template_type']) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    public function execute()
    {
        $templateType = $this->getRequest()->getParam('template_type');
        return $this->jsonResultFactory->create()->setData(['templates' => $this->getDefaultTemplates($templateType)]);
    }

    /**
     * Check the permission to run it
     *
     * @return boolean
     */
    //@codingStandardsIgnoreLine
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE_SAVE);
    }
}
