<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit/Tab/Preview.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Tab;

use Magento\Framework\Data\Form;
use Magento\Framework\Phrase;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Backend\Model\UrlInterface;
use Xtento\PdfCustomizer\Model\Source\TemplateType;

/**
 * Class Preview
 * @package Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Tab
 */
class Preview extends Generic implements TabInterface
{
    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        UrlInterface $url,
        array $data = []
    ) {
        $this->url = $url;
        parent::__construct($context, $registry, $formFactory, $data);
    }


    /**
     * @return $this
     */
    public function _prepareForm()
    {
        /** @var Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('preview_');
        $form->addFieldset('base_fieldset', ['legend' => __('PDF Preview')]);
        $this->setForm($form);

        parent::_prepareForm();
        return $this;
    }

    public function _toHtml()
    {
        $model = $this->_coreRegistry->registry('pdfcustomizer_template');

        if ($model->getTemplateType() == TemplateType::TYPE_PRODUCT) {
            $pdfTemplateType = 'product';
        } else {
            $pdfTemplateType = 'order';
        }

        if (!$model->getData('template_id')) {
            return parent::_toHtml() . "<strong>" . __('Please save the profile once using the "Save and Continue Edit" button before trying to preview your template.') . "</strong>";
        }

        $previewUrl = $this->url->getUrl(
            'xtento_pdf/' . $pdfTemplateType . '/testPdf',
            [
                'template_id' => $model->getData('template_id'),
                'template_type' => $model->getData('template_type')
            ]
        );

        $pdfForm = '<form target="pdf-preview" method="POST" action="' . $previewUrl . '" id="preview-form" style="display:none">
            <input name="form_key" id="form-key" type="hidden"/>
            <input name="entity_id" id="entity-id" type="hidden"/>
            <textarea name="template_html" id="template-html"></textarea>
            <textarea name="template_css" id="template-css"></textarea>
            <input name="template_paper_ori" id="template-paper-ori" type="hidden"/>
            <input name="template_custom_t" id="template-custom-t" type="hidden"/>
            <input name="template_custom_b" id="template-custom-b" type="hidden"/>
            <input name="template_custom_l" id="template-custom-l" type="hidden"/>
            <input name="template_custom_r" id="template-custom-r" type="hidden"/>
            <input type="submit" name="submit-form" id="submit-form"/>
        </form><button id="manual-preview" onclick="initPdfPreview()">Generate PDF preview</button>';
        return parent::_toHtml() . $pdfForm;
    }

    /**
     * @return Phrase
     */
    public function getTabLabel()
    {
        return __('Preview PDF');
    }

    /**
     * Prepare title for tab
     *
     * @return Phrase
     */
    public function getTabTitle()
    {
        return __('Preview PDF');
    }

    /**
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }
}
