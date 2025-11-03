<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-04-18T20:54:33+00:00
 * File:          Helper/Variable/Formatted.php
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
use Xtento\PdfCustomizer\Model\PdfTemplate;

class Formatted extends AbstractHelper
{
    /**
     * @var Order
     */
    private $order;

    /** @var PdfTemplate */
    private $template;

    /**
     * @var array
     */
    private $templateConfig = [];

    /**
     * @var TimezoneInterface
     */
    private $timezoneInterface;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var DataObjectFactory
     */
    private $dataObject;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $countryFactory;

    /**
     * @var \Magento\GiftMessage\Model\MessageFactory
     */
    protected $messageFactory;

    /**
     * Formatted constructor.
     *
     * @param Context $context
     * @param Order $order
     * @param TimezoneInterface $timezoneInterface
     * @param DateTime $dateTime
     * @param DataObjectFactory $dataObject
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\GiftMessage\Model\MessageFactory $messageFactory
     */
    public function __construct(
        Context $context,
        Order $order,
        TimezoneInterface $timezoneInterface,
        DateTime $dateTime,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        DataObjectFactory $dataObject,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\GiftMessage\Model\MessageFactory $messageFactory
    ) {
        $this->order = $order;
        $this->dateTime = $dateTime;
        $this->timezoneInterface = $timezoneInterface;
        $this->localeResolver = $localeResolver;
        $this->dataObject = $dataObject;
        $this->countryFactory = $countryFactory;
        $this->messageFactory = $messageFactory;
        parent::__construct($context);
    }

    /**
     * Insert the actual order to process the variables
     *
     * @param Object $source
     *
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
     * @param $template
     */
    public function applyTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * @param $config
     */
    public function setConfiguration($config)
    {
        $this->templateConfig = $config;
    }

    /**
     * Process object values for pdf output.
     *
     * @param Object $object
     *
     * @return \Magento\Framework\DataObject|null
     * @SuppressWarnings(CyclomaticComplexity)
     */
    public function getFormatted($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return null;
        }
        if (!is_object($object)) {
            $object = $this->dataObject->create($object);
        }

        $objectData = $object->getData();

        $formattedData = [];
        foreach ($objectData as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $formattedData[$key] = $value;

            if ($key == 'percent') {
                $formattedData[$key] = round($value); // For tax rate percentage
                continue;
            }

            $numberFields = ['tax_amount_div_qty', 'discount_refunded', 'tax_refunded', 'tax_canceled', 'row_total', 'row_total_incl_tax', 'tax_before_discount', 'row_invoiced', 'row_total', 'amount_refunded', 'discount_invoiced', 'discount_amount', 'cost'];
            $isNumberField = in_array(str_replace('base_', '', $key), $numberFields);
            if ((is_numeric($value) && !is_infinite($value)) || $isNumberField) {
                if (preg_match('/^base_/', $key)) {
                    if (isset($this->templateConfig['hide_currency_symbol']) && !$this->templateConfig['hide_currency_symbol']) {
                        $formattedData[$key] = strip_tags($this->order->formatBasePrice($value));
                    } else {
                        $formattedData[$key] = strip_tags($this->order->getBaseCurrency()->formatPrecision($value, 2, ['symbol' => ''], false));
                    }
                } else {
                    if (isset($this->templateConfig['hide_currency_symbol']) && !$this->templateConfig['hide_currency_symbol']) {
                        $formattedData[$key] = strip_tags($this->order->formatPrice($value));
                    } else {
                        $formattedData[$key] = strip_tags($this->order->getOrderCurrency()->formatPrecision($value, 2, ['symbol' => ''], false));
                    }
                }

                if (preg_match('/percent/', $key)) {
                    $formattedData[$key] = number_format($value, 2).'%';
                }
                if ($key == 'tax_percent') {
                    $formattedData[$key] = $value . '%';
                    continue;
                }
            }

            if ($key == 'total_qty' || $key == 'qty' || (strpos($key, 'qty') !== false && strpos($key, 'div_qty') === false)) {
                if ($value === null) {
                    $value = 0;
                }
                if (floor($value) != $value) {
                    // Has decimals
                    $formattedData[$key] = round($value, 2);
                } else {
                    $formattedData[$key] = (int)round($value, 0);
                }
                continue;
            }

            if (in_array($key, AbstractPdf::DATE_FIELDS)) {
                $date = $value instanceof \DateTimeInterface ? $value : new \DateTime($value);
                $formattedData[$key] = $this->timezoneInterface->formatDateTime(
                    $date,
                    IntlDateFormatter::MEDIUM,
                    IntlDateFormatter::NONE,
                    $this->localeResolver->getDefaultLocale(),
                    $this->timezoneInterface->getConfigTimezone('store', $object->getStore())
                );
                $formattedData[$key . '_time'] = $this->timezoneInterface->formatDateTime(
                    $date,
                    IntlDateFormatter::MEDIUM,
                    IntlDateFormatter::SHORT,
                    $this->localeResolver->getDefaultLocale(),
                    $this->timezoneInterface->getConfigTimezone('store', $object->getStore())
                );
                continue;
            }
        }
        if (!isset($formattedData['qty'])) {
            $qty = $object->getQtyOrdered();
            if ($qty > 0 && floor($qty) != $qty) {
                // Has decimals
                $formattedData['qty'] = round($qty, 2);
            } else {
                $formattedData['qty'] = (int)round($object->getQtyOrdered() * 1, 0);
            }
        }

        return $this->dataObject->create($formattedData);
    }

    /**
     * @param Object $object
     * @param $type
     *
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
     * Used for "depend"
     *
     * @param $object
     *
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
            if ((!is_numeric($value) && !empty($value)) || (is_numeric($value) && abs($value) > 0)) {
                $newData[$data] = 1;
                continue;
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
     * @param $orderOrItem
     *
     * @return \Magento\Framework\DataObject
     */
    public function getOrderGiftMessageArray($orderOrItem)
    {
        if ($orderOrItem->getGiftMessageId()) {
            $giftMessageModel = $this->messageFactory->create()->load($orderOrItem->getGiftMessageId());
            if ($giftMessageModel->getId()) {
                return $this->dataObject->create($giftMessageModel->toArray());
            }
        }
        return $this->dataObject->create([]);
    }

    /**
     * @param $address
     *
     * @return mixed
     */
    public function addFieldsToAddressFields($address)
    {
        if (!$address || $address === null) {
            return $this->dataObject->create([]);
        }
        $pdfAddress = $this->dataObject->create($address->getData());
        if ($address->getCountryId() !== null) {
            $country = $this->countryFactory->create();
            $country->load($address->getCountryId());
            $pdfAddress->setCountryName($country->getName($this->localeResolver->getLocale()));
            $pdfAddress->setCountryIso3($country->getData('iso3_code'));
        }
        return $pdfAddress;
    }

    /**
     * @param $templateHtml
     * @param $start
     * @param $end
     *
     * @return array|bool
     */
    public function getTemplateAreas($templateHtml, $start, $end)
    {
        preg_match_all('/' . preg_quote($start) . '(.*?)' . preg_quote($end) . '/mis', $templateHtml, $itemMatches);
        if (!isset($itemMatches[1]) || !isset($itemMatches[1][0])) {
            return [];
        }
        return $itemMatches[1];
    }
}
