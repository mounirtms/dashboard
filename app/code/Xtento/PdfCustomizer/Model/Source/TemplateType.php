<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Model/Source/TemplateType.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Source;

use Magento\Framework\View\Model\PageLayout\Config\BuilderInterface;

/**
 * Class PageLayout
 */
class TemplateType extends AbstractSource
{
    /**
     * Types
     */
    const TYPE_INVOICE = 1;
    const TYPE_ORDER = 2;
    const TYPE_SHIPMENT = 3;
    const TYPE_CREDIT_MEMO = 4;
    const TYPE_PRODUCT = 5;
    const TYPE_SECONDARY_ATTACHMENT = 6;

    const TYPES = [
        self::TYPE_INVOICE => 'invoice',
        self::TYPE_ORDER => 'order',
        self::TYPE_SHIPMENT => 'shipment',
        self::TYPE_CREDIT_MEMO => 'creditmemo',
        self::TYPE_PRODUCT => 'product',
        self::TYPE_SECONDARY_ATTACHMENT => 'attachment',
    ];

    /**
     * @var \Magento\Framework\View\Model\PageLayout\Config\BuilderInterface
     */
    private $pageLayoutBuilder;

    /**
     * Constructor
     *
     * @param BuilderInterface $pageLayoutBuilder
     */
    public function __construct(BuilderInterface $pageLayoutBuilder)
    {
        $this->pageLayoutBuilder = $pageLayoutBuilder;
    }

    /**
     * @return array
     */
    public function getAvailable()
    {
        return [
            self::TYPE_ORDER => __('Order'),
            self::TYPE_INVOICE => __('Invoice'),
            self::TYPE_SHIPMENT => __('Shipment'),
            self::TYPE_CREDIT_MEMO => __('Credit Memo'),
            self::TYPE_PRODUCT => __('Product'),
            self::TYPE_SECONDARY_ATTACHMENT => __('Attachment')
        ];
    }
}
