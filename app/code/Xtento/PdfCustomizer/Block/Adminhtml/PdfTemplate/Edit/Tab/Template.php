<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit/Tab/Template.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Tab;

use Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Renderer\Editor;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Magento\Backend\Model\UrlInterface as ButtonsVariable;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\View\Asset\Repository;
use Xtento\PdfCustomizer\Model\Source\TemplateType;

/**
 * Class Template
 * @package Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Tab
 */
class Template extends Generic implements TabInterface
{
    /**
     * @var ButtonsVariable
     */
    private $buttonsVariable;

    /**
     * @var Repository
     */
    private $assetRepo;

    /**
     * Body constructor.
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param ButtonsVariable $buttonsVariable
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        ButtonsVariable $buttonsVariable,
        array $data = []
    ) {
        $this->buttonsVariable = $buttonsVariable;
        $this->assetRepo       = $context->getAssetRepository();
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @return $this
     */
    public function _prepareForm()
    {

        /** @var PdfTemplate $model */
        $model = $this->_coreRegistry->registry('pdfcustomizer_template');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('template_');

        // Variables
        if ($model->getData('template_type') != TemplateType::TYPE_SECONDARY_ATTACHMENT) {
            $fieldSet = $form->addFieldset('base_fieldset', ['legend' => __('Variables / Test PDF')]);

            if ($model->getId()) {
                $fieldSet->addField('template_id', 'hidden', ['name' => 'template_id']);
            }

            $url = $this->getUrl(
                'xtento_pdf/variable/ajaxload',
                ['template_type' => $model->getData('template_type')]
            );

            $model->setData('ajax_search', $url);
            $model->setData('type_id', $model->getData('template_type'));

            $fieldSet->addField(
                'type_id',
                'hidden',
                ['name' => 'type_id']
            );

            $fieldSet->addField(
                'ajax_search',
                'hidden',
                ['name' => 'ajax_search']
            );

            $source = $fieldSet->addField(
                'source',
                'text',
                [
                    'name' => 'source',
                    'label' => __('%1 ID for variables/testing', ucwords(TemplateType::TYPES[$model->getData('template_type')])),
                    'title' => __('%1 ID for variables/testing', ucwords(TemplateType::TYPES[$model->getData('template_type')])),
                    'required' => true,
                    'disabled' => false,
                    'after_element_html' => __(
                        'In order to show you the variables available, enter the increment ID of a %1 here. Then click one of the below buttons to see the variables that can be used in your PDF template.', ucwords(TemplateType::TYPES[$model->getData('template_type')])
                    ),
                ]
            );
            $renderer = $this->getLayout()->createBlock(
                Editor::class
            );
            $source->setRenderer($renderer);
        }

        // Template
        $fieldSet = $form->addFieldset('body_fieldset', ['legend' => __('PDF Template')]);
        $fieldSet->addField('template_html', 'textarea', [
            'name' => 'template_html',
            'label' => '',
            'required' => false
        ]);

        // Additional CSS
        $fieldSet = $form->addFieldset('css_fieldset', ['legend' => __('Template CSS')]);
        $fieldSet->addField('template_css', 'textarea', [
            'name' => 'template_css',
            'label' => '',
            'required' => false
        ]);

        $form->setValues($model->getData());
        $this->setForm($form);

        parent::_prepareForm();

        return $this;
    }

    /**
     * @return Phrase
     */
    public function getTabLabel()
    {
        return __('PDF Template');
    }

    /**
     * Prepare title for tab
     *
     * @return Phrase
     */
    public function getTabTitle()
    {
        return __('PDF Template');
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

    protected function getFormMessages()
    {
        $formMessages = [];

        /** @var PdfTemplate $model */
        $model = $this->_coreRegistry->registry('pdfcustomizer_template');
        if ($model->getData('template_type') == TemplateType::TYPE_SECONDARY_ATTACHMENT) {
            $formMessages[] = [
                'type' => 'notice',
                'message' => __(
                    'If you upload a static PDF file in the "PDF Configuration" tab, the settings you make in this tab will be ignored, and instead the uploaded PDF file will be used.'
                )
            ];
        }
        return $formMessages;
    }

    protected function _toHtml()
    {
        if ($this->getRequest()->getParam('ajax')) {
            return parent::_toHtml();
        }
        return $this->_getFormMessages() . parent::_toHtml();
    }

    protected function _getFormMessages()
    {
        $html = '<div id="messages"><div class="messages">';
        foreach ($this->getFormMessages() as $formMessage) {
            $html .= '<div class="message message-' . $formMessage['type'] . ' ' . $formMessage['type'] . '"><div>' . $formMessage['message'] . '</div></div>';
        }
        $html .= '</div></div>';
        return $html;
    }
}
