<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-23T10:14:40+00:00
 * File:          Helper/Variable/Custom/Totals.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Custom;

use Magento\Sales\Block\Order\Creditmemo;
use Magento\Sales\Model\Order;

class Totals implements CustomInterface
{
    /**
     * @var Order|Order\Invoice|Creditmemo
     */
    private $source;

    /**
     * @param $source
     * @return $this
     */
    public function entity($source)
    {
        if (is_object($source)) {
            $this->source = $source;
            $this->totals();
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
    public function totals()
    {
        $source = $this->source;

        if ($source instanceof \Magento\Catalog\Model\Product) {
            return $this;
        }

        $order = $this->source->getOrder();

        if ($source instanceof Order) {
            $order = $this->source;
        }

        $total = $order->getData('grand_total');
        $taxAmount = $order->getData('tax_amount');
        $grandTotalExclTax = $total - $taxAmount;

        $this->source->setData('formatted_total_grand', $grandTotalExclTax);

        return $grandTotalExclTax;
    }
}