<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/AddButton.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml;

use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;

/**
 * Class AddButton
 * @package Xtento\PdfCustomizer\Block\Adminhtml
 */
class AddButton extends Container
{
    /**
     * @var TemplateType
     */
    private $templateType;

    /**
     * AddButton constructor.
     * @param Context $context
     * @param TemplateType $templateType
     * @param array $data
     */
    public function __construct(
        Context $context,
        TemplateType $templateType,
        array $data = []
    ) {
        $this->templateType = $templateType;
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    public function _prepareLayout()
    {
        $addButtonProps = [
            'id' => 'add_new_template',
            'label' => __('Add Template'),
            'class' => 'add',
            'button_class' => '',
            'class_name' => 'Magento\Backend\Block\Widget\Button\SplitButton',
            'options' => $this->templateOptions(),
        ];
        $this->buttonList->add('add_new', $addButtonProps);

        return parent::_prepareLayout();
    }

    /**
     * @return array
     */
    public function templateOptions()
    {
        $splitButtonOptions = [];
        $types = $this->templateType->getAvailable();

        foreach ($types as $typeId => $type) {
            $splitButtonOptions[$typeId] = [
                'label' => $type,
                'onclick' => "setLocation('" . $this->templateUrl($typeId) . "')",
                'default' => TemplateType::TYPE_INVOICE == $typeId,
            ];
        }

        return $splitButtonOptions;
    }

    /**
     * @param $typeId
     * @return string
     */
    private function templateUrl($typeId)
    {
        return $this->getUrl(
            '*/*/newtemplate',
            ['type' => $typeId]
        );
    }
}
