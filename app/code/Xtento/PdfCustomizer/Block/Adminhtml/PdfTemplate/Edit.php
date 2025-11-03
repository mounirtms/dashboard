<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate;

use Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\DuplicateButton;
use Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit\SaveAndContinueButton;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;
use Xtento\PdfCustomizer\Model\Source\TemplateType;

/**
 * Class Edit
 * @package Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate
 */
class Edit extends Container
{

    private $duplicateButton;

    /**
     * Core registry
     *
     * @var Registry
     */
    private $coreRegistry = null;

    public function __construct(
        Context $context,
        Registry $registry,
        DuplicateButton $duplicateButton,
        array $data = []
    ) {
        $this->coreRegistry = $registry;
        $this->duplicateButton = $duplicateButton;
        parent::__construct($context, $data);
    }

    /**
     *
     * @return void
     */
    public function _construct()
    {
        $this->_objectId = 'template_id';
        $this->_blockGroup = 'Xtento_PdfCustomizer';
        $this->_controller = 'adminhtml_pdfTemplate';

        parent::_construct();

        $this->buttonList->remove('reset');
        $this->buttonList->update('save', 'label', __('Save Template'));
        if (!empty($this->duplicateButton->getButtonData())) {
            $this->buttonList->add(
                'duplicate',
                $this->duplicateButton->getButtonData()
            );
        }

        $model = $this->coreRegistry->registry('pdfcustomizer_template');
        if ($model->getTemplateType() != TemplateType::TYPE_SECONDARY_ATTACHMENT) {
            $this->buttonList->add(
                'load_default_template',
                [
                    'label' => __('Load Default Template'),
                    'onclick' => 'window.previewPopupModal.open()'
                ],
                -200
            );
        }

        $this->buttonList->add(
            'saveandcontinue',
            [
                'label' => __('Save and Continue Edit'),
                'class' => 'save',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => ['event' => 'saveAndContinueEdit', 'target' => '#edit_form'],
                    ],
                ]
            ],
            -100
        );

        $this->buttonList->update(
            'delete',
            'label',
            __('Delete Template')
        );
    }
}
