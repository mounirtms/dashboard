<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-04-18T21:14:51+00:00
 * File:          Helper/Variable/Processors/Pdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Magento\Store\Model\ScopeInterface;
use Xtento\PdfCustomizer\Helper\Variable\Formatted;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Xtento\PdfCustomizer\Helper\AbstractPdf;
use Xtento\PdfCustomizer\Helper\Variable\Custom\SalesCollect as TaxHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Payment\Helper\Data;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;

/**
 * Class Pdf
 * Process the variable so they are configured for pdf output
 *
 * @package Xtento\PdfCustomizer\Helper
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Pdf extends AbstractPdf
{
    /**
     * @var File
     */
    public $file;

    /**
     * @var DirectoryList
     */
    public $directoryList;

    /**
     * @var Formatted
     */
    public $formatted;

    /**
     * @var Items
     */
    private $itemProcessor;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var Tax
     */
    private $taxProcessor;

    /**
     * @var Tracking
     */
    private $trackingProcessor;

    /**
     * @var Totals
     */
    private $totalsProcessor;

    /**
     * Pdf constructor.
     *
     * @param Context $context
     * @param File $file
     * @param DirectoryList $directoryList
     * @param Processor $processor
     * @param Data $paymentHelper
     * @param InvoiceIdentity $identityContainer
     * @param Renderer $addressRenderer
     * @param Formatted $formatted
     * @param Items $itemProcessor
     * @param TaxHelper $taxHelper
     * @param Tax $taxProcessor
     * @param Tracking $trackingProcessor
     * @param Totals $totalsProcessor
     */
    public function __construct(
        Context $context,
        File $file,
        DirectoryList $directoryList,
        Processor $processor,
        Data $paymentHelper,
        InvoiceIdentity $identityContainer,
        Renderer $addressRenderer,
        Formatted $formatted,
        Items $itemProcessor,
        TaxHelper $taxHelper,
        Tax $taxProcessor,
        Tracking $trackingProcessor,
        Totals $totalsProcessor
    ) {
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->formatted = $formatted;
        $this->itemProcessor = $itemProcessor;
        $this->taxHelper = $taxHelper;
        $this->taxProcessor = $taxProcessor;
        $this->trackingProcessor = $trackingProcessor;
        $this->totalsProcessor = $totalsProcessor;
        parent::__construct($context, $processor, $paymentHelper, $identityContainer, $addressRenderer);
    }

    /**
     * Filename of the pdf and the stream to sent to the download
     *
     * @return array
     */
    public function template2Pdf()
    {
        $source = $this->source;
        $templateModel = $this->template;

        $this->formatted->applySourceOrder($source);
        $this->formatted->applyTemplate($templateModel);

        if (stristr($templateModel->getTemplateHtml(), 'hideCurrencySymbol=true') === false) {
            $hideCurrencySymbol = false;
        } else {
            $hideCurrencySymbol = true;
        }
        $this->formatted->setConfiguration(['hide_currency_symbol' => $hideCurrencySymbol]);

        // Items
        $templateHtml = $this->itemProcessor->processItems($source, $templateModel);
        $templateModel->setData('template_html', $templateHtml);

        // Tax rates
        $templateHtml = $this->taxProcessor->process($source, $templateModel);
        $templateModel->setData('template_html', $templateHtml);

        // Tracking numbers
        $templateHtml = $this->trackingProcessor->process($source, $templateModel);
        $templateModel->setData('template_html', $templateHtml);

        // Totals
        $templateHtml = $this->totalsProcessor->process($source, $templateModel);
        $templateModel->setData('template_html', $templateHtml);

        /** transport use to get the variables $order object, $source object and the template model object */
        $parts = $this->buildVariablesAndProcessTemplate();

        /** instantiate the mPDF class and add the processed html to get the pdf */
        /** @var Output $applySettings */
        $applySettings = $this->applyPdfSettings($parts);

        $fileParts = [
            'model' => $templateModel,
            'filestream' => $applySettings,
            'filename' => filter_var($parts['filename'], FILTER_SANITIZE_URL)
        ];

        return $fileParts;
    }

    /**
     * Build variables for orders/invoices/...
     *
     * @param bool $getAllVariables
     *
     * @return array|string
     * @throws \Exception
     */
    public function buildVariablesAndProcessTemplate($getAllVariables = false)
    {
        $order = $this->taxHelper->entity($this->order)->processAndReadVariables();
        $source = $this->taxHelper->entity($this->source)->processAndReadVariables();

        $templateModel = $this->template;
        $templateType = $templateModel->getData('template_type');

        $templateTypeName = TemplateType::TYPES[$templateType]; // order, invoice, ...
        $templateHtml = $this->template->getTemplateHtml();

        if ($getAllVariables) {
            $templateHtml = ''; // We only want to show selected variables
        }

        // Performance improvement: Only load variables that are actually required
        $transport = [
            'increment_id' => $source->getIncrementId(),
        ];
        if ($getAllVariables || strstr($templateHtml, ' ' . $templateTypeName . '.') !== false || strstr($templateHtml, '$' . $templateTypeName) !== false) {
            $transport[$templateTypeName] = $source;
        }
        if ($getAllVariables || strstr($templateHtml, ' formatted_' . $templateTypeName . '.') !== false) {
            $transport['formatted_' . $templateTypeName] = $this->formatted->getFormatted($source);
        }
        if ($getAllVariables || strstr($templateHtml, ' order.') !== false || strstr($templateHtml, '$order') !== false) {
            $transport['order'] = $order;
            $transport['order']['store_view_name'] = $order->getStore()->getName();
            try {
                $transport['order']['payment_method_title'] = $order->getPayment()->getMethodInstance()->getTitle();
            } catch (\Exception $e) {
                $transport['order']['payment_method_title'] = 'Payment method could not be loaded';
            }
        }
        if ($getAllVariables || strstr($templateHtml, ' formatted_order.') !== false) {
            $transport['formatted_order'] = $this->formatted->getFormatted($order);
        }
        if (!isset($transport['invoice']) && ($getAllVariables || strstr($templateHtml, ' invoice.') !== false || strstr($templateHtml, '$invoice') !== false || strstr($templateHtml, ' formatted_invoice.') !== false)) {
            // For credit memos
            if ($source->getInvoiceId() > 0 && $source->getInvoice()) {
                $transport['invoice'] = $source->getInvoice();
                $transport['formatted_invoice'] = $this->formatted->getFormatted($source->getInvoice());
            }
        }
        if ($getAllVariables || strstr($templateHtml, ' customer.') !== false) {
            $transport['customer'] = $this->customer;
        }
        if ($getAllVariables || strstr($templateHtml, ' billing.') !== false) {
            $transport['billing'] = $this->formatted->addFieldsToAddressFields($order->getBillingAddress());
        }
        if ($getAllVariables || strstr($templateHtml, ' formattedBillingAddress') !== false) {
            $transport['formattedBillingAddress'] = $this->getFormattedBillingAddress($order);
        }
        if (strstr($templateHtml, ' billing_if.') !== false) {
            $transport['billing_if'] = $this->formatted->getZeroFormatted($order->getBillingAddress());
        }
        if ($getAllVariables || strstr($templateHtml, ' shipping.') !== false) {
            $transport['shipping'] = $this->formatted->addFieldsToAddressFields($order->getShippingAddress());
        }
        if ($getAllVariables || strstr($templateHtml, ' formattedShippingAddress') !== false) {
            $transport['formattedShippingAddress'] = $this->getFormattedShippingAddress($order);
        }
        if (strstr($templateHtml, ' shipping_if.') !== false) {
            $transport['shipping_if'] = $this->formatted->getZeroFormatted($order->getShippingAddress());
        }
        if ($getAllVariables || strstr($templateHtml, ' payment_html') !== false) {
            $transport['payment_html'] = $this->getPaymentHtml($order);
        }
        if ($getAllVariables || strstr($templateHtml, ' payment.') !== false) {
            $transport['payment'] = $order->getPayment();
        }
        if ($getAllVariables || strstr($templateHtml, ' payment.') !== false) {
            $transport['formatted_payment'] = $this->formatted->getFormatted($order->getPayment());
        }
        if (strstr($templateHtml, ' payment_if.') !== false) {
            $transport['payment_if'] = $this->formatted->getZeroFormatted($order->getPayment());
        }
        if ($getAllVariables || strstr($templateHtml, ' store') !== false) {
            $transport['store'] = $order->getStore();
        }
        if (strstr($templateHtml, ' ' . $templateTypeName . '_if.') !== false) {
            $transport[$templateTypeName . '_if'] = $this->formatted->getZeroFormatted($source);
        }
        if (strstr($templateHtml, ' order_if.') !== false) {
            $transport['order_if'] = $this->formatted->getZeroFormatted($order);
        }
        if (strstr($templateHtml, ' customer_if.') !== false) {
            $transport['customer_if'] = $this->formatted->getZeroFormatted($this->customer);
        }
        if ($getAllVariables || strstr($templateHtml, ' store_information') !== false) {
            $storeInfo = $this->scopeConfig->getValue('general/store_information', ScopeInterface::SCOPE_STORE, $order->getStoreId());
            $transport['store_information'] = $storeInfo;
            // Remove empty values for "depend" usage
            $transport['store_information_if'] = $this->formatted->getIfFormattedArray($storeInfo);
        }
        if ($getAllVariables || strstr($templateHtml, ' giftmessage.') !== false) {
            $transport['giftmessage'] = $this->formatted->getOrderGiftMessageArray($order);
        }

        // Barcode variables
        foreach (AbstractPdf::CODE_BAR as $code) {
            if (strstr($templateHtml, 'barcode_' . $code . '_' . $templateTypeName) !== false) {
                $transport['barcode_' . $code . '_' . $templateTypeName] = $this->formatted->getBarcodeFormatted($source, $code);
            }
            if (strstr($templateHtml, 'barcode_' . $code . '_order') !== false) {
                $transport['barcode_' . $code . '_order'] = $this->formatted->getBarcodeFormatted($order, $code);
            }
            if (strstr($templateHtml, 'barcode_' . $code . '_customer') !== false) {
                $transport['barcode_' . $code . '_customer'] = $this->formatted->getBarcodeFormatted($this->customer, $code);
            }
        }

        // Ability to customize variables using an event. Store them using $transportObject->setCustomVariables();
        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setCustomVariables([]);
        $this->_eventManager->dispatch(
            'xtento_pdfcustomizer_build_transport_after',
            [
                'type' => 'sales',
                'object' => $this->source,
                'variables' => $transport,
                'transport' => $transportObject
            ]
        );
        $transport = array_merge($transport, $transportObject->getCustomVariables());

        if ($getAllVariables) {
            return $transport;
        }

        /** @var Processor $processor */
        $processor = $this->processor;
        $processor->setVariables($transport);
        $processor->setTemplate($this->template);
        $parts = $processor->processTemplate($order->getStoreId());

        return $parts;
    }
}
