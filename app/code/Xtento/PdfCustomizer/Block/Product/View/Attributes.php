<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-07-24T12:25:45+00:00
 * File:          Block/Product/View/Attributes.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Product\View;

use Magento\Catalog\Block\Product\View\Attributes as AttributesView;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Attributes
 * @package Xtento\PdfCustomizer\Block\Product\View
 */
class Attributes extends AttributesView
{
    /**
     * Attributes constructor.
     * @param Context $context
     * @param Registry $registry
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $registry, $priceCurrency, $data);
        $this->setTemplate('Xtento_PdfCustomizer::product/view/attributes.phtml');
    }

    /**
     * Retrieve block view from file (template)
     *
     * @param string $fileName
     *
     * @return string
     */
    public function fetchView($fileName)
    {
        if ($this->validator->isValid($fileName)) {
            return parent::fetchView($fileName);
        } else {
            return '';
        }
    }

    /**
     * @param $product
     * @return $this
     */

    public function setProduct($product)
    {
        $this->_product = $product;
        return $this;
    }

    /**
     * @param $product
     * @return array
     */
    public function attributeTableSource($product)
    {
        $data = [];
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront()) {
                // Attribute must have "Visible on Catalog Pages on Storefront" set to "Yes"
                $value = $attribute->getFrontend()->getValue($product);

                if (!$product->hasData($attribute->getAttributeCode())) {
                    continue;
                } elseif (!is_object($value) && !is_array($value) && (string)$value == '') {
                    continue;
                } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                    $value = $this->priceCurrency->convertAndFormat($value);
                } elseif (is_array($value) || is_object($value)) {
                    continue;
                }

                if ($value instanceof Phrase || $value) {
                    $data[$attribute->getAttributeCode()] = [
                        'label' => __($attribute->getStoreLabel()),
                        'value' => $value,
                        'code' => $attribute->getAttributeCode(),
                    ];
                }
            }
        }

        return $data;
    }
}
