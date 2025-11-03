<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Model/Plugin/Printcreditmemo.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Plugin;

use Xtento\PdfCustomizer\Helper\Data;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Registry;

class Printcreditmemo
{

    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * Printinvoice constructor.
     * @param Registry $coreRegistry
     * @param UrlInterface $urlInterface
     * @param Data $dataHelper
     */
    public function __construct(
        Registry $coreRegistry,
        UrlInterface $urlInterface,
        Data $dataHelper
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->urlInterface = $urlInterface;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @return mixed
     */
    public function getCreditmemo()
    {
        return $this->coreRegistry->registry('current_creditmemo');
    }

    /**
     * @param $subject
     * @param $result
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    //@codingStandardsIgnoreLine
    public function afterGetPrintUrl($subject, $result)
    {
        if (!$this->dataHelper->isEnabled(Data::ENABLE_CREDITMEMO)) {
            return $result;
        }

        $lastItem = $this->dataHelper->getDefaultTemplate(
            $this->getCreditmemo(),
            TemplateType::TYPE_CREDIT_MEMO
        );

        if (empty($lastItem->getId())) {
            return $result;
        }

        return $this->_print($lastItem);
    }

    /**
     * @param $lastItem
     * @return string
     */
    private function _print($lastItem)
    {
        return $this->urlInterface->getUrl(
            'xtento_pdf/*/printpdf',
            [
                'template_id' => $lastItem->getId(),
                'order_id' => $this->getCreditmemo()->getOrder()->getId(),
                'creditmemo_id' => $this->getCreditmemo()->getId()
            ]
        );
    }
}
