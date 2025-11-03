<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-11-15T21:43:48+00:00
 * File:          Helper/Variable/Custom/Items.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Custom;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item;

class Items implements CustomInterface
{

    /**
     * @var Object
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
            return $this;
        }

        $this->addTaxPercent();
    }

    /**
     * @return Object
     */
    public function processAndReadVariables()
    {
        $this->addTaxPercent();
        $this->addItemOptions();
        $this->addMissingVariables();
        return $this->source;
    }

    /**
     * Sometimes some variables are missing. We add them here as they are required.
     */
    protected function addMissingVariables()
    {
        $item = $this->source;
        $qty = $item->getQty() ? $item->getQty() : $item->getQtyOrdered();
        if ($qty && $item->getBasePriceInclTax() && is_null($item->getBaseRowTotalInclTax())) {
            $item->setBaseRowTotalInclTax($item->getBasePriceInclTax() * $qty);
        }
        if ($qty && $item->getPriceInclTax() && is_null($item->getRowTotalInclTax())) {
            $item->setRowTotalInclTax($item->getPriceInclTax() * $qty);
        }
        if ($qty && $item->getBasePriceInclTax()) {
            $item->setBaseRowTotalInclTaxFinal($item->getBaseRowTotalInclTax() - $item->getBaseDiscountAmount());
        }
        if ($qty && $item->getPriceInclTax()) {
            $item->setRowTotalInclTaxFinal($item->getRowTotalInclTax() - $item->getDiscountAmount());
        }
        if ($qty && $item->getBasePriceInclTax()) {
            $item->setBaseRowTotalFinal($item->getBaseRowTotal() - $item->getBaseDiscountAmount() + $item->getBaseTaxAmount());
        }
        if ($qty && $item->getPriceInclTax()) {
            $item->setRowTotalFinal($item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount());
        }
        if ($qty > 0 && $item->getTaxAmount()) {
            $item->setTaxAmountDivQty($item->getTaxAmount() / $qty);
        }
        if ($qty > 0 && $item->getBaseTaxAmount()) {
            $item->setBaseTaxAmountDivQty($item->getBaseTaxAmount() / $qty);
        }
    }

    /**
     * @return Item|Object
     */
    protected function addTaxPercent()
    {
        if (!$this->source instanceof Item && $this->source->getOrderItem()) {
            $orderItem = $this->source->getOrderItem();
        } else {
            $orderItem = $this->source;
        }

        $taxPercent = (float)$orderItem->getTaxPercent();
        if (floor($taxPercent) != $taxPercent) {
            $taxPercent = number_format((float)$orderItem->getTaxPercent(), 2);
        } else {
            $taxPercent = number_format((float)$orderItem->getTaxPercent(), 0);
        }

        $this->source->setData(
            OrderItemInterface::TAX_PERCENT,
            $taxPercent
        );

        return $this->source;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function addItemOptions()
    {
        if (!$this->source instanceof Item && $this->source->getOrderItem()) {
            $orderItem = $this->source->getOrderItem();
        } else {
            $orderItem = $this->source;
        }

        $result = [];
        if ($options = $orderItem->getProductOptions()) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }

        $data = '';

        if (!empty($result)) {
            foreach ($result as $option => $value) {
                $data .= $value['label'] . ' - ' . $value['value'] . '<br>';
            }

            $this->source->setData(
                'item_options',
                $data
            );
        }

        $this->source->setData(
            'item_options',
            $data
        );
    }
}
