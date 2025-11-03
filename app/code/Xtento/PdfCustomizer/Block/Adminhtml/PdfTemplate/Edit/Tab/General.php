<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit/Tab/General.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Tab;

use Xtento\PdfCustomizer\Model\PdfTemplate;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Source\Barcode as BarcodeTypes;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Store\Model\System\Store as SystemStore;
use Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset\Element;

/**
 * Class Main
 * @package Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Tab
 */
class General extends Generic implements TabInterface
{
    /**
     * @var TemplateType
     */
    private $templateType;

    /**
     * @var Yesno
     */
    private $yesNo;

    /**
     * @var BarcodeTypes
     */
    private $barcodeTypes;

    /**
     * @var SystemStore
     */
    private $systemStore;

    /**
     * General constructor.
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TemplateType $templateType
     * @param Yesno $yesNo
     * @param BarcodeTypes $barcodeTypes
     * @param SystemStore $systemStore
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TemplateType $templateType,
        Yesno $yesNo,
        BarcodeTypes $barcodeTypes,
        SystemStore $systemStore,
        array $data = []
    ) {
        $this->templateType = $templateType;
        $this->yesNo        = $yesNo;
        $this->barcodeTypes = $barcodeTypes;
        $this->systemStore  = $systemStore;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @return $this
     *  * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function _prepareForm()
    {

        /** @var PdfTemplate $model */
        $model = $this->_coreRegistry->registry('pdfcustomizer_template');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('template_');

        $fieldSet = $form->addFieldset('base_fieldset', ['legend' => __('General Settings')]);

        if ($model->getId()) {
            $fieldSet->addField('template_id', 'hidden', ['name' => 'template_id']);
        }

        $fieldSet->addField(
            'template_name',
            'text',
            [
                'name' => 'template_name',
                'label' => __('Template Name'),
                'title' => __('Template Name'),
                'required' => true,
            ]
        );

        $types = $this->templateType->toOptionArray();

        $onlyType = [];
        if ($type = $model->getData('template_type')) {
            $onlyType[] = $types[$type];
        }

        $fieldSet->addField(
            'template_type',
            'select',
            [
                'name' => 'template_type',
                'label' => __('Template Type'),
                'title' => __('Template Type'),
                'values' => $onlyType,
                'required' => true,
                'readonly' => true,
            ]
        );

        /*$fieldSet->addField(
            'template_description',
            'text',
            [
                'name' => 'template_description',
                'label' => __('Template Description'),
                'title' => __('Template Description'),
                'required' => false,
            ]
        );*/

        $fieldSet->addField(
            'is_active',
            'select',
            [
                'name' => 'is_active',
                'label' => __('Template Enabled'),
                'title' => __('Template Enabled'),
                'note' => __('If disabled, the template cannot be printed anywhere.'),
                'values' => $this->yesNo->toOptionArray(),
                'required' => true,
            ]
        );

        $templateType = $model->getData('template_type');
        if ($templateType !== TemplateType::TYPE_SECONDARY_ATTACHMENT) {
            $fieldSet->addField(
                'template_default',
                'select',
                [
                    'name' => 'template_default',
                    'label' => __('Default Template'),
                    'title' => __('Default Template'),
                    'note' => __('You can have one default template per entity (Orders, invoices, ...). This is the template that is going to be attached to emails, etc.'),
                    'values' => $this->yesNo->toOptionArray(),
                    'required' => true,
                ]
            );
        }

        if (!$this->_storeManager->isSingleStoreMode()) {
            $field = $fieldSet->addField(
                'store_id',
                'multiselect',
                [
                    'name' => 'store_id',
                    'label' => __('Store View(s)'),
                    'title' => __('Store View(s)'),
                    'required' => true,
                    'note' => __('To which store views should this template be applied?'),
                    'values' => $this->systemStore->getStoreValuesForForm(false, true)
                ]
            );
            $renderer = $this->getLayout()->createBlock(
                Element::class
            );
            $field->setRenderer($renderer);
        } else {
            $fieldSet->addField(
                'stores',
                'hidden',
                ['name' => 'stores[]', 'value' => $this->_storeManager->getStore(true)->getId()]
            );
            $model->setStoreId($this->_storeManager->getStore(true)->getId());
        }

        if (!$model->getId()) {
            $model->setIsActive(1);
            $model->setStoreId(0);
        }

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
        return __('General Settings');
    }

    /**
     * Prepare title for tab
     *
     * @return Phrase
     */
    public function getTabTitle()
    {
        return __('General Settings');
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
