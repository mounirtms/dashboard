<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-10-22T14:34:16+00:00
 * File:          Helper/Variable/Processors/Tax.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ItemFactory;
use Xtento\PdfCustomizer\Helper\Variable\Formatted;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject\Factory as DataObject;

/**
 * Class Tax
 * @package Xtento\PdfCustomizer\Helper\Variable\Processors
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Tax extends AbstractHelper
{
    /**
     * @var Formatted
     */
    private $formatted;

    /**
     * @var Processor
     */
    public $processor;

    /**
     * @var DataObject
     */
    private $dataObject;

    /**
     * Tax helper
     *
     * @var \Magento\Tax\Helper\Data
     */
    protected $taxHelper;

    /**
     * Tax calculation
     *
     * @var \Magento\Tax\Model\Calculation
     */
    protected $taxCalculation;

    /**
     * Tax factory
     *
     * @var \Magento\Tax\Model\Sales\Order\TaxFactory
     */
    protected $taxOrderFactory;

    /**
     * @var ItemFactory
     */
    protected $orderItemFactory;

    /**
     * Tax constructor.
     *
     * @param Context $context
     * @param Processor $processor
     * @param Formatted $formatted
     * @param DataObject $dataObject
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Tax\Model\Calculation $taxCalculation
     * @param \Magento\Tax\Model\Sales\Order\TaxFactory $taxOrderFactory
     * @param ItemFactory $orderItemFactory
     */
    public function __construct(
        Context $context,
        Processor $processor,
        Formatted $formatted,
        DataObject $dataObject,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Tax\Model\Calculation $taxCalculation,
        \Magento\Tax\Model\Sales\Order\TaxFactory $taxOrderFactory,
        ItemFactory $orderItemFactory
    ) {
        $this->formatted = $formatted;
        $this->processor = $processor;
        $this->dataObject = $dataObject;
        $this->taxHelper = $taxHelper;
        $this->taxCalculation = $taxCalculation;
        $this->taxOrderFactory = $taxOrderFactory;
        $this->orderItemFactory = $orderItemFactory;
        parent::__construct($context);
    }

    /**
     * @param $source
     * @param $taxRate
     * @param $template
     *
     * @return array|string
     */
    public function variableTaxProcessor($source, $taxRate, $template)
    {
        $transport['tax_rate'] = $this->dataObject->create($taxRate);
        $transport['tax_rate_formatted'] = $this->formatted->getFormatted($taxRate);

        $processor = $this->processor;
        $processor->setVariables($transport);
        $processor->setTemplate($template);

        return $processor->processTemplate($source->getStoreId());
    }

    /**
     * @param $source
     * @param $templateModel
     * @return string
     */
    public function process($source, $templateModel)
    {
        $templateHtml = $templateModel->getTemplateHtml();
        $templateTaxParts = $this->formatted->getTemplateAreas(
            $templateHtml,
            '##taxrates_start##',
            '##taxrates_end##'
        );

        if (empty($templateTaxParts)) {
            return $templateHtml;
        }

        $taxRates = $this->getTaxRates($source);

        foreach ($templateTaxParts as $templateTaxPart) {
            $taxHtml = '';
            foreach ($taxRates as $taxRate) {
                $templateParts = $this->dataObject->create(
                    [
                        'template_html_full' => null,
                        'template_html' => $templateTaxPart
                    ]
                );
                $processedTemplate = $this->variableTaxProcessor($source, $taxRate, $templateParts);
                $taxHtml .= $processedTemplate['body'];
            }
            $templateHtml = str_replace($templateTaxParts, $taxHtml, $templateHtml);
        }

        $templateHtml = str_replace(['##taxrates_start##', '##taxrates_end##'], '', $templateHtml);
        return $templateHtml;
    }

    /**
     * @param $source
     *
     * @return array
     */
    public function getTaxRates($source)
    {
        $taxRates = [];
        $tempTaxRates = $this->taxHelper->getCalculatedTaxes($source);
        if (empty($tempTaxRates)) {
            if ($source instanceof Order) {
                $order = $source;
            } else {
                $order = $source->getOrder();
            }
            $rates = $this->taxOrderFactory->create()->getCollection()->loadByOrder($order)->toArray();
            $tempTaxRates = $this->taxCalculation->reproduceProcess($rates['items']);
            // reproduceProcess returns different format than getCalculatedTaxes
            foreach ($tempTaxRates as $tempTaxRate) {
                if (isset($tempTaxRate['rates'])) {
                    foreach ($tempTaxRate['rates'] as $rate) {
                        $title = round($rate['percent'], 0) . '%';
                        if (floor($rate['percent']) != $rate['percent']) {
                            $title = round($rate['percent'], 2) . '%'; // Has decimals
                        }
                        $taxRates[] = [
                            'tax_amount' => $tempTaxRate['amount'],
                            'base_tax_amount' => $tempTaxRate['base_amount'],
                            'title' => $title,
                            'text' => $rate['title'],
                            'percent' => $rate['percent']
                        ];
                    }
                }
            }
        } else {
            foreach ($tempTaxRates as $tempTaxRate) {
                $title = round($tempTaxRate['percent'], 0) . '%';
                if (floor($tempTaxRate['percent']) != $tempTaxRate['percent']) {
                    $title = round($tempTaxRate['percent'], 2) . '%'; // Has decimals
                }
                $taxRates[] = [
                    'tax_amount' => $tempTaxRate['tax_amount'],
                    'base_tax_amount' => $tempTaxRate['base_tax_amount'],
                    'title' => $title,
                    'text' => $tempTaxRate['title'],
                    'percent' => $tempTaxRate['percent']
                ];
            }
        }
        return $taxRates;
    }
}