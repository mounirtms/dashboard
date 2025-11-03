<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/Tools/Export.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\Tools;

class Export extends \Magento\Backend\Block\Template
{
    /**
     * @var \Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory
     */
    protected $templateCollectionFactory;

    /**
     * Export constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory $templateCollectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory $templateCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->templateCollectionFactory = $templateCollectionFactory;
    }

    public function getTemplates()
    {
        $templateCollection = $this->templateCollectionFactory->create();
        $templateCollection->getSelect()->order('template_name ASC');
        return $templateCollection;
    }
}
