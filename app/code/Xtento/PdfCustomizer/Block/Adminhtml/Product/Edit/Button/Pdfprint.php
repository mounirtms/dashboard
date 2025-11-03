<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-03-04T10:42:54+00:00
 * File:          Block/Adminhtml/Product/Edit/Button/Pdfprint.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\Product\Edit\Button;

use Magento\Catalog\Model\Product;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Helper\Data;
use Magento\Catalog\Block\Adminhtml\Product\Edit\Button\Generic;
use Magento\Framework\View\Element\UiComponent\Context;
use Magento\Framework\Registry;

class Pdfprint extends Generic
{
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * Core registry
     *
     * @var Registry
     */
    private $coreRegistry = null;

    /**
     * Pdfprint constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param Data $dataHelper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Data $dataHelper
    ) {
        $this->coreRegistry = $registry;
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $registry);
    }

    /**
     * @return string
     */
    public function getPdfPrintUrl($id)
    {
        return $this->getUrl(
            'xtento_pdf/*/printpdf',
            [
                'template_id' => $id,
                'product_id' => $this->registry->registry('current_product')->getId(),
            ]
        );
    }

    public function getButtonData()
    {
        if (!$this->dataHelper->isEnabled(Data::ENABLE_PRODUCT)) {
            return [];
        }

        /** @var Product $product */
        $product = $this->registry->registry('current_product');
        if (!$product || !$product->getId()) {
            return [];
        }

        $defaultTemplate = $this->dataHelper->getDefaultTemplate(
            $product,
            TemplateType::TYPE_PRODUCT
        );

        if (empty($defaultTemplate->getId())) {
            return [];
        }

        return [
            'label' => __('Print PDF'),
            'on_click' => sprintf("location.href = '%s';", $this->getPdfPrintUrl($defaultTemplate->getId())),
            'sort_order' => 100
        ];
    }
}
