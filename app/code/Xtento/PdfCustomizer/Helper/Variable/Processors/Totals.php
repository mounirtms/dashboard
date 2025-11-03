<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-08-19T15:05:32+00:00
 * File:          Helper/Variable/Processors/Totals.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Magento\Sales\Model\Order;
use Xtento\PdfCustomizer\Helper\Variable\Formatted;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject\Factory as DataObject;
use Magento\Sales\Model\Order\Pdf\InvoiceFactory as InvoicePdfFactory;

/**
 * Class Totals
 * @package Xtento\PdfCustomizer\Helper\Variable\Processors
 */
class Totals extends AbstractHelper
{
    protected static $totalRenderers = false;

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
     * @var InvoicePdfFactory
     */
    protected $abstractPdfFactory;

    /**
     * @var Tax
     */
    protected $taxHelper;

    /**
     * @var \Magento\Tax\Model\Config
     */
    protected $taxConfig;

    /**
     * @var \Magento\Weee\Helper\Data
     */
    protected $weeeHelper;

    /**
     * Totals constructor.
     *
     * @param Context $context
     * @param Processor $processor
     * @param Formatted $formatted
     * @param DataObject $dataObject
     * @param InvoicePdfFactory $abstractPdfFactory
     * @param Tax $taxHelper
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Weee\Helper\Data $weeeHelper
     */
    public function __construct(
        Context $context,
        Processor $processor,
        Formatted $formatted,
        DataObject $dataObject,
        InvoicePdfFactory $abstractPdfFactory,
        Tax $taxHelper,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Weee\Helper\Data $weeeHelper
    ) {
        $this->formatted = $formatted;
        $this->processor = $processor;
        $this->dataObject = $dataObject;
        $this->abstractPdfFactory = $abstractPdfFactory;
        $this->taxHelper = $taxHelper;
        $this->taxConfig = $taxConfig;
        $this->weeeHelper = $weeeHelper;
        parent::__construct($context);
    }

    /**
     * @param $source
     * @param $total
     * @param $template
     *
     * @return array|string
     */
    public function variableTotalProcessor($source, $total, $template)
    {
        $transport['total'] = $this->dataObject->create($total);
        $transport['total_formatted'] = $this->formatted->getFormatted($total);

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
        if ($templateModel->getTemplateType() == TemplateType::TYPE_SHIPMENT) {
            return $templateHtml;
        }

        $templateTotalParts = $this->formatted->getTemplateAreas(
            $templateHtml,
            '##totals_start##',
            '##totals_end##'
        );

        if (empty($templateTotalParts)) {
            return $templateHtml;
        }

        // Get totals renderers
        if (self::$totalRenderers === false) {
            $pdfClass = $this->abstractPdfFactory->create();
            // Make function _getTotalsList() accessible
            $reflectionMethod = new \ReflectionMethod($pdfClass, '_getTotalsList');
            $reflectionMethod->setAccessible(true);
            self::$totalRenderers = $reflectionMethod->invoke($pdfClass);
            $reflectionMethod->setAccessible(false);
        }

        // Count totals
        $totalsCount = 0;
        foreach (self::$totalRenderers as $totalRenderer) {
            if ($source instanceof Order) {
                $order = $source->setOrderId($source->getId());
            } else {
                $order = $source->getOrder();
            }
            /** @var $totalRenderer \Magento\Sales\Model\Order\Pdf\Total\DefaultTotal */
            $totalRenderer->setOrder($order)->setSource($source);
            if ($totalRenderer->canDisplay()) {
                $totalsCount++;
            }
        }

        // Load translation
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $emulation = $objectManager->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation($source->getStoreId(), \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $areaList = $objectManager->get('\Magento\Framework\App\AreaList');
        $area = $areaList->getArea(\Magento\Framework\App\Area::AREA_FRONTEND);
        $area->load(\Magento\Framework\App\Area::PART_TRANSLATE);

        // Compile totals
        $totals = [];
        $totalsCounter = 0;
        foreach (self::$totalRenderers as $totalRenderer) {
            if ($source instanceof Order) {
                $source->setOrderId($source->getId())->setOrder($source); // To avoid issues with getTitleDescription in DefaultTotal
                $order = $source;
            } else {
                $order = $source->getOrder();
            }
            /** @var $totalRenderer \Magento\Sales\Model\Order\Pdf\Total\DefaultTotal */
            $totalRenderer->setOrder($order)->setSource($source);
            if ($totalRenderer->canDisplay()) {
                $totalsForDisplay = $totalRenderer->getTotalsForDisplay();
                if (empty($totalsForDisplay)) {
                    continue;
                }
                if ($totalRenderer->getSourceField() == 'tax_amount' && $this->taxConfig->displaySalesFullSummary($order->getStore())) {
                    // Custom tax renderer
                    $totalTaxAmount = 0;
                    $totalBaseTaxAmount = 0;
                    $taxRates = $this->taxHelper->getTaxRates($source);
                    foreach ($taxRates as $taxRate) {
                        $totalsCounter++;
                        $label = rtrim($totalsForDisplay[count($totalsForDisplay) - 1]['label'], ':');
                        $taxPercent = $taxRate['title'];
                        $totalTaxAmount += $taxRate['tax_amount'];
                        $totalBaseTaxAmount += $taxRate['base_tax_amount'];
                        $totals[] = [
                            'label' => (string)__($label),
                            'amount' => $taxRate['tax_amount'],
                            'base_amount' => $taxRate['base_tax_amount'],
                            'tax_percent' => $taxPercent,
                            'amount_prefix' => '',
                            'is_bold' => false,
                            'is_grand_total' => false,
                            'is_subtotal' => false,
                            'is_tax' => true,
                            'is_first' => $totalsCounter === 1,
                            'is_last' => $totalsCounter === $totalsCount
                        ];
                    }
                    if (floatval($totalTaxAmount) != floatval($source->getTaxAmount())) {
                        $missingAmount = abs(floatval($totalTaxAmount) - floatval($source->getTaxAmount()));
                        if ($missingAmount > 1e-6) {
                            $totalsCounter++;
                            $totals[] = [
                                'label' => (string)__('Tax'),
                                'amount' => $missingAmount,
                                'base_amount' => abs($totalBaseTaxAmount - $source->getBaseTaxAmount()),
                                'tax_percent' => __('Other'),
                                'amount_prefix' => '',
                                'is_bold' => false,
                                'is_grand_total' => false,
                                'is_subtotal' => false,
                                'is_tax' => true,
                                'is_first' => $totalsCounter === 1,
                                'is_last' => $totalsCounter === $totalsCount
                            ];
                        }
                    }
                } else if ($totalRenderer->getSourceField() == 'grand_total' && $this->taxConfig->displaySalesTaxWithGrandTotal($order->getStore())) {
                    $tempCounter = 0;
                    foreach ($totalsForDisplay as $totalForDisplay) {
                        $tempCounter++;
                        $totalsCounter++;
                        $maxToDisplay = count($totalsForDisplay) - 1;
                        if ($this->taxConfig->displaySalesFullSummary($order->getStore())) {
                            $maxToDisplay = count($totalsForDisplay);
                        }
                        if ($tempCounter > 1 && $tempCounter < $maxToDisplay) {
                            if ($tempCounter == 2 && $this->taxConfig->displaySalesFullSummary($order->getStore())) {
                                // Display custom tax rates
                                $taxRates = $this->taxHelper->getTaxRates($source);
                                foreach ($taxRates as $taxRate) {
                                    $totalsCounter++;
                                    $taxPercent = $taxRate['title'];
                                    $totals[] = [
                                        'label' => (string)__('Tax'), // $taxRate['title']
                                        'amount' => $taxRate['tax_amount'],
                                        'base_amount' => $taxRate['base_tax_amount'],
                                        'tax_percent' => $taxPercent,
                                        'amount_prefix' => '',
                                        'is_bold' => false,
                                        'is_grand_total' => false,
                                        'is_subtotal' => false,
                                        'is_tax' => true,
                                        'is_first' => $totalsCounter === 1,
                                        'is_last' => $totalsCounter === $totalsCount
                                    ];
                                }
                            }
                            continue;
                        }
                        $label = str_replace(' ()', '', rtrim($totalForDisplay['label'], ':'));
                        $amount = $source->getDataUsingMethod($totalRenderer->getSourceField());
                        if ($totalRenderer->getSourceField() === null || $totalRenderer->getSourceField() === '_' || $amount === null || $amount === false || is_array($amount)) {
                            $amount = $totalRenderer->getAmount();
                        }
                        if ($totalRenderer->getSourceField() == 'weee_amount') {
                            $amount = $this->weeeHelper->getTotalAmounts($source->getAllItems(), $source->getStore());
                        }
                        $baseAmount = $source->getDataUsingMethod('base_' . $totalRenderer->getSourceField());
                        if ($totalRenderer->getSourceField() == 'adjustment_negative' || $totalRenderer->getSourceField() == 'discount_amount') {
                            $amount = abs($amount) * -1;
                            $baseAmount = abs($baseAmount) * -1;
                        }
                        $totals[] = [
                            'label' => (string)__($label),
                            'amount' => $totalForDisplay['amount'] ? $totalForDisplay['amount'] : $amount,
                            'base_amount' => $baseAmount,
                            'tax_percent' => 0,
                            'amount_prefix' => $totalRenderer->getAmountPrefix(),
                            'is_bold' => count($totalsForDisplay) === $tempCounter ? 1 : false,
                            'is_grand_total' => count($totalsForDisplay) === $tempCounter ? 1 : false,
                            'is_subtotal' => ($totalRenderer->getSourceField() == 'subtotal') ? 1 : false,
                            'is_first' => $totalsCounter === 1,
                            'is_tax' => false,
                            'is_last' => $totalsCounter === $totalsCount
                        ];
                    }
                } else {
                    foreach ($totalsForDisplay as $totalForDisplay) {
                        $totalsCounter++;
                        $label = str_replace(' ()', '', rtrim($totalForDisplay['label'], ':'));
                        $amount = $source->getDataUsingMethod($totalRenderer->getSourceField());
                        if ($totalRenderer->getSourceField() === null || $totalRenderer->getSourceField() === '_' || $amount === null || $amount === false || is_array($amount)) {
                            $amount = $totalRenderer->getAmount();
                        }
                        if ($totalRenderer->getSourceField() == 'weee_amount') {
                            $amount = $this->weeeHelper->getTotalAmounts($source->getAllItems(), $source->getStore());
                        }
                        $baseAmount = $source->getDataUsingMethod('base_' . $totalRenderer->getSourceField());
                        if ($totalRenderer->getSourceField() == 'adjustment_negative' || $totalRenderer->getSourceField() == 'discount_amount') {
                            $amount = abs($amount) * -1;
                            $baseAmount = abs($baseAmount) * -1;
                        }
                        $totals[] = [
                            'label' => (string)__($label),
                            'amount' => $totalForDisplay['amount'] ? $totalForDisplay['amount'] : $amount,
                            'base_amount' => $baseAmount,
                            'tax_percent' => 0,
                            'amount_prefix' => $totalRenderer->getAmountPrefix(),
                            'is_bold' => ($totalRenderer->getSourceField() == 'grand_total') ? 1 : false,
                            'is_grand_total' => ($totalRenderer->getSourceField() == 'grand_total') ? 1 : false,
                            'is_subtotal' => ($totalRenderer->getSourceField() == 'subtotal') ? 1 : false,
                            'is_first' => $totalsCounter === 1,
                            'is_tax' => false,
                            'is_last' => $totalsCounter === $totalsCount
                        ];
                    }
                }
            }
        }

        $emulation->stopEnvironmentEmulation();

        foreach ($templateTotalParts as $templateTotalPart) {
            $totalsHtml = '';
            foreach ($totals as $total) {
                $templateParts = $this->dataObject->create(
                    [
                        'template_html_full' => null,
                        'template_html' => $templateTotalPart
                    ]
                );
                $processedTemplate = $this->variableTotalProcessor($source, $total, $templateParts);
                $totalsHtml .= $processedTemplate['body'];
            }
            $templateHtml = str_replace($templateTotalParts, $totalsHtml, $templateHtml);
        }

        $templateHtml = str_replace(['##totals_start##', '##totals_end##'], '', $templateHtml);
        return $templateHtml;
    }
}