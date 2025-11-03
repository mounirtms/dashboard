<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Helper/Variable/Custom/SalesCollect.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Custom;

use Magento\Sales\Block\Order\Creditmemo;
use Magento\Sales\Model\Order;
use Magento\Tax\Helper\Data as TaxData;

/**
 * Class SalesCollect
 * @package Xtento\PdfCustomizer\Helper\Variable\Custom
 */
class SalesCollect implements CustomInterface
{
    /**
     * @var Order|Order\Invoice|Creditmemo
     */
    private $source;

    /**
     * @var TaxData
     */
    private $taxData;

    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var Totals
     */
    private $totals;

    /**
     * SalesCollect constructor.
     * @param TaxData $taxData
     * @param Comments $comments
     * @param Totals $totals
     */
    public function __construct(
        TaxData $taxData,
        Comments $comments,
        Totals $totals
    ) {
        $this->taxData  = $taxData;
        $this->comments = $comments;
        $this->totals   = $totals;
    }

    /**
     * @param $source
     * @return $this
     */
    public function entity($source)
    {

        if (is_object($source)) {
            $this->source = $source;
            $this->setTaxDetails();
            $this->comments->entity($source);
            $this->totals->entity($source);
            return $this;
        }
    }

    /**
     * @return Order|Order\Invoice|Creditmemo
     */
    public function processAndReadVariables()
    {
        return $this->source;
    }

    /**
     * @return $this
     */
    public function setTaxDetails()
    {
        if ($this->source instanceof \Magento\Catalog\Model\Product) {
            return $this;
        }
        $taxes = $this->taxData->getCalculatedTaxes($this->source);

        if (!empty($taxes)) {
            foreach ($taxes as $tax) {
                foreach ($tax as $key => $value) {
                    $title = strtolower(str_replace(
                        ' ',
                        '_',
                        preg_replace("/[^A-Za-z0-9 ]/", '', $tax['title'])
                    ));
                    if ($key === 'percent') {
                        $this->source->setData(
                            'tax_' .
                            $key .
                            '_' .
                            $title .
                            '_' .
                            round($tax['percent']),
                            round($value, 2) . '%'
                        );
                        continue;
                    }
                    $this->source->setData('tax_' . $key . '_' . $title . '_' . round($tax['percent']), $value);
                }
            }
        }

        return $this;
    }
}