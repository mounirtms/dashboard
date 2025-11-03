<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Block/Product/Pdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Product;

use Xtento\PdfCustomizer\Helper\Data;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;

class Pdf extends Template
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var PdfTemplate
     */
    private $pdfTemplate;

    /**
     * Pdf constructor.
     * @param Context $context
     * @param Registry $registry
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->helper = $helper;
        $this->storeManager = $context->getStoreManager();
    }

    /**
     * @return bool
     */
    public function addPDFLink()
    {
        $product = $this->getProduct();
        $helper = $this->helper;

        if ($helper->isEnabled(false, $this->storeManager->getStore()->getId())
            && $helper->isEnabled('xtento_pdfcustomizer/product/frontend_enabled', $this->storeManager->getStore()->getId())
        ) {
            $pdfTemplate = $helper->getDefaultTemplate(
                $product,
                TemplateType::TYPE_PRODUCT
            );

            if ($pdfTemplate->getId()) {
                $this->pdfTemplate = $pdfTemplate;
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getPrintPDFUrl()
    {
        $product = $this->getProduct();

        return $this->getUrl('xtento_pdf/pdfPrint/product', [
            'template_id' => $this->pdfTemplate->getId(),
            'product_id' => $product->getId()
        ]);
    }

    private function getProduct()
    {
        $product = $this->registry->registry('current_product');
        $storeId = $this->storeManager->getStore()->getId();
        $product->setStoreId($storeId);

        return $product;
    }
}
