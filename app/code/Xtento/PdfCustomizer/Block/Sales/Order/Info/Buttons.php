<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-30T13:51:37+00:00
 * File:          Block/Sales/Order/Info/Buttons.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Sales\Order\Info;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Xtento\PdfCustomizer\Helper\Data;
use Magento\Framework\View\Element\Template\Context;
use Xtento\PdfCustomizer\Model\PdfTemplate;

class Buttons extends \Magento\Framework\View\Element\Template
{
    /**
     * @var PdfTemplate
     */
    private $pdfTemplate;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var string
     */
    //@codingStandardsIgnoreLine
    protected $_template = 'Xtento_PdfCustomizer::order/info/buttons.phtml';

    /**
     * Buttons constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        Registry $registry,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }


    /**
     * @param $source
     *
     * @return bool
     */
    public function addPDFLink($source)
    {
        $helper = $this->helper;

        if ($helper->isEnabled(false, $this->_storeManager->getStore()->getId())
            && $helper->isEnabled('xtento_pdfcustomizer/order/frontend_enabled', $this->_storeManager->getStore()->getId())
        ) {
            $defaultTemplate = $helper->getDefaultTemplate($source);

            if ($defaultTemplate->getId()) {
                $this->pdfTemplate = $defaultTemplate;
                return true;
            }
        }

        return false;
    }

    /**
     * @param $source
     *
     * @return string
     */
    public function getPrintPDFUrl($source)
    {
        return $this->getUrl(
            'xtento_pdf/pdfPrint/sales',
            [
                'template_id' => $this->pdfTemplate->getId(),
                'order_id' => $source->getId(),
                'entity_id' => $source->getId()
            ]
        );
    }

    /**
     * Retrieve current order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Check whether built-in print action of Magento should be hidden
     */
    public function hideBuiltInPrintActions()
    {
        return $this->helper->isEnabled('xtento_pdfcustomizer/advanced/disable_default_print_actions');
    }
}
