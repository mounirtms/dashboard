<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Helper/Variable/ProductFormatted.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable;

use Xtento\PdfCustomizer\Helper\AbstractPdf;
use IntlDateFormatter;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Pricing\Helper\Data as PricingHelperData;

class ProductFormatted extends AbstractHelper
{

    /**
     * @var Order
     */
    private $order;

    /**
     * @var TimezoneInterface
     */
    private $timezoneInterface;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var DataObjectFactory
     */
    private $dataObject;

    /**
     * @var PricingHelperData
     */
    private $pricingHelperData;

    /**
     * ProductFormatted constructor.
     * @param Context $context
     * @param Order $order
     * @param TimezoneInterface $timezoneInterface
     * @param DateTime $dateTime
     * @param DataObjectFactory $dataObject
     * @param PricingHelperData $pricingHelperData
     */
    public function __construct(
        Context $context,
        Order $order,
        TimezoneInterface $timezoneInterface,
        DateTime $dateTime,
        DataObjectFactory $dataObject,
        PricingHelperData $pricingHelperData
    ) {
        $this->order             = $order;
        $this->dateTime          = $dateTime;
        $this->timezoneInterface = $timezoneInterface;
        $this->dataObject        = $dataObject;
        $this->pricingHelperData = $pricingHelperData;
        parent::__construct($context);
    }

    /**
     * Insert the actual order to process the variables
     * @param Object $source
     * @return Order
     */
    public function applySourceOrder($source)
    {
        if (!$source instanceof Order) {
            return $this->order = $source->getOrder();
        }

        return $this->order = $source;
    }

    /**
     * Process object values for pdf output.
     * @param Object $object
     * @return \Magento\Framework\DataObject|null
     * @SuppressWarnings(CyclomaticComplexity)
     */
    public function getFormatted($object)
    {
        if (!is_object($object)) {
            return null;
        }

        $objectData = $object->getData();

        $newData = [];
        foreach ($objectData as $data => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_numeric($value) && !is_infinite($value)) {
                $currency = $this->pricingHelperData->currency(
                    $value,
                    true,
                    false
                );
                $newData[$data] = strip_tags($currency);

                continue;
            }

            if (in_array($data, AbstractPdf::DATE_FIELDS)) {
                $newData[$data] = $this->timezoneInterface->formatDate(
                    $this->timezoneInterface->date($this->dateTime->date($value)),
                    IntlDateFormatter::MEDIUM,
                    true
                );

                continue;
            } else {
                $newData[$data] = $value;
            }
        }

        return $this->dataObject->create($newData);
    }

    /**
     * @param Object $object
     * @param $type
     * @return \Magento\Framework\DataObject|null
     */
    public function getBarcodeFormatted($object, $type)
    {
        if (!is_object($object)) {
            return null;
        }

        $objectData = $object->getData();

        $newData = [];
        foreach ($objectData as $data => $value) {
            if (is_numeric($value) || is_string($value)) {
                $newData[$data] = strip_tags($value);
                $newData[$data] = '<barcode code="' .
                    strip_tags($value) .
                    '" type="' .
                    $type .
                    '" size="0.8" class="barcode" text="1" />';
                continue;
            }
        }

        return $this->dataObject->create($newData);
    }

    /**
     * @param Object $object
     * @return \Magento\Framework\DataObject|null
     */
    public function getZeroFormatted($object)
    {
        if (!is_object($object)) {
            return null;
        }

        $objectData = $object->getData();

        $newData = [];
        foreach ($objectData as $data => $value) {
            if (is_numeric($value)) {
                if ($value != 0) {
                    $newData[$data] = $value;
                    continue;
                }
            }
        }

        return $this->dataObject->create($newData);
    }

    /**
     * Used for "depend"
     *
     * @param $object
     *
     * @return \Magento\Framework\DataObject|null
     */
    public function getIfFormattedArray($object)
    {
        $newData = [];
        if (!is_array($object) && !is_object($object)) {
            return $this->dataObject->create($newData);
        }
        foreach ($object as $data => $value) {
            if (!empty($value)) {
                $newData[$data] = $value;
                continue;
            }
        }

        return $this->dataObject->create($newData);
    }

    /**
     * @param $template
     * @param $start
     * @param $end
     * @return array
     * @codingStandardsIgnoreLine
     * @todo refactor this part using regular expression and add validations
     * and add the validation or there will be a fatal error without the items
     */
    public function getTemplateArea($template, $start, $end)
    {
        if (strpos($template, $start) === false) {
            return [$template, '', ''];
        }

        if (strpos($template, $end) === false) {
            return [$template, '', ''];
        }

        $firstPart = explode($start, $template);

        $beginning = $firstPart[0];

        $secondPart = explode($end, $firstPart[1]);

        $items = $secondPart[0];

        $end = $secondPart[1];

        return [$beginning, $items, $end];
    }
}
