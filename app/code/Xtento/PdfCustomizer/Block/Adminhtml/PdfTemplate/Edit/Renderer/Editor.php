<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-03-25T15:25:16+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit/Renderer/Editor.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Renderer;

use Xtento\PdfCustomizer\Model\PdfTemplate;
use Magento\Backend\Model\UrlInterface;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Registry;
use Magento\Backend\Block\Widget\Button;

/**
 * Class Editor
 * @package Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\Renderer
 */
class Editor extends Element implements RendererInterface
{

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var PdfTemplate
     */
    private $pdfModel;

    /**
     * Editor constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param UrlInterface $url
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        UrlInterface $url,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->url = $url;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $this->_element = $element;
        $html = $this->toHtml();
        $htmlWithButtons = $html . "<strong>" . __('Show variables') . ":</strong> " . $this->addButtonHtml();
        return $htmlWithButtons;
    }

    /**
     * @return string
     */
    private function addButtonHtml()
    {
        $html = '';

        /** @var PdfTemplate $model */
        $model = $this->registry->registry('pdfcustomizer_template');
        $this->pdfModel = $model;

        if (!$model->getId()) {
            return __('Functionality disabled. Please save the template once, you will be able to see the variable buttons here afterwards.');
        }

        if ($model->getData('template_type') == TemplateType::TYPE_SECONDARY_ATTACHMENT) {
            return $html;
        }

        $entity = ucwords(TemplateType::TYPES[$model->getTemplateType()]);

        if ($model->getData('template_type') == TemplateType::TYPE_PRODUCT) {
            $html =
                $this->getVariableProductCustomButtonHtml() .
                $this->getVariableButtonHtml();
            return $html;
        }

        $html =
            $this->getVariableButtonHtml() .
            $this->getSourceButtonHtml($entity) .
            $this->getVariableItemsButtonHtml();

        return $html;
    }

    /**
     * @return string
     */
    private function getVariableButtonHtml()
    {
        $html_id = $this->_element->getData('name');

        $button = $this->getLayout()->createBlock(
            Button::class,
            '',
            [
                'data' => [
                    'name' => 'variable_button1',
                    'label' => __('Standard Variables'),
                    'type' => 'button',
                    'style' => 'margin:5px 0;',
                    'class' => 'action-wysiwyg',
                    'onclick' => 'PdfCustomizerVariablePlugin.loadChooser(\'' .
                        $this->url->getUrl(
                            'xtento_pdf/variable/standard',
                            ['template_id' => $this->pdfModel->getData('template_id')]
                        ) .
                        '\', \'' . $html_id . '\');',
                ]
            ]
        )->toHtml();

        return $button;
    }

    /**
     * @return string
     */
    private function getSourceButtonHtml($entity)
    {

        $html_id = $this->_element->getData('name');

        $button = $this->getLayout()->createBlock(
            Button::class,
            '',
            [
                'data' => [
                    'name' => 'variable_button2',
                    'label' => __('%1 Variables', $entity),
                    'type' => 'button',
                    'style' => 'margin:5px 0;',
                    'class' => 'action-wysiwyg',
                    'onclick' => 'PdfCustomizerVariablePlugin.loadChooser(\'' .
                        $this->url->getUrl(
                            'xtento_pdf/variable/source',
                            ['template_id' => $this->pdfModel->getData('template_id')]
                        ) .
                        '\', \'' . $html_id . '\');',
                ]
            ]
        )->toHtml();

        return $button;
    }

    /**
     * @return string
     */
    private function getVariableItemsButtonHtml()
    {

        $html_id = $this->_element->getData('name');

        $button = $this->getLayout()->createBlock(
            Button::class,
            '',
            [
                'data' => [
                    'name' => 'variable_button3',
                    'label' => __('Item Variables'),
                    'type' => 'button',
                    'style' => 'margin:5px 0;',
                    'class' => 'action-wysiwyg',
                    'onclick' => 'PdfCustomizerVariablePlugin.loadChooser(\'' .
                        $this->url->getUrl(
                            'xtento_pdf/variable/items',
                            ['template_id' => $this->pdfModel->getData('template_id')]
                        ) .
                        '\', \'' . $html_id . '\');',
                ]
            ]
        )->toHtml();

        return $button;
    }

    /**
     * @return string
     */
    private function getVariableProductCustomButtonHtml()
    {

        $html_id = $this->_element->getData('name');

        $button = $this->getLayout()->createBlock(
            Button::class,
            '',
            [
                'data' => [
                    'name' => 'variable_button3',
                    'label' => __('Source Variables'),
                    'type' => 'button',
                    'style' => 'margin:5px 0;',
                    'class' => 'action-wysiwyg',
                    'onclick' => 'PdfCustomizerVariablePlugin.loadChooser(\'' .
                        $this->url->getUrl(
                            'xtento_pdf/variable/productcustom',
                            ['template_id' => $this->pdfModel->getData('template_id')]
                        ) .
                        '\', \'' . $html_id . '\');',
                ]
            ]
        )->toHtml();

        return $button;
    }
}
