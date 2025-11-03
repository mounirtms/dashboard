<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit/Tabs.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit;

use Magento\Backend\Block\Widget\Tabs as WidgetTabs;
use Xtento\PdfCustomizer\Model\Source\TemplateType;

/**
 * Admin page left menu
 */
class Tabs extends WidgetTabs
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Tabs constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $jsonEncoder, $authSession, $data);
    }

    /**
     * @return void
     */
    public function _construct()
    {
        parent::_construct();
        $this->setId('pdfcustomizer_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Edit Template'));
    }

    protected function _beforeToHtml()
    {
        $model = $this->registry->registry('pdfcustomizer_template');
        if ($model->getTemplateType() == TemplateType::TYPE_SECONDARY_ATTACHMENT) {
            $this->removeTab('preview_section');
        }

        return parent::_beforeToHtml();
    }
}
