<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Helper/Variable/Processors/ProductPdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Xtento\PdfCustomizer\Helper\Variable\ProductFormatted;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Xtento\PdfCustomizer\Helper\AbstractPdf;
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
class ProductPdf extends AbstractPdf
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
    private $items;

    /**
     * ProductPdf constructor.
     * @param Context $context
     * @param File $file
     * @param DirectoryList $directoryList
     * @param Processor $processor
     * @param Data $paymentHelper
     * @param InvoiceIdentity $identityContainer
     * @param Renderer $addressRenderer
     * @param ProductFormatted $formatted
     * @param Items $items
     */
    public function __construct(
        Context $context,
        File $file,
        DirectoryList $directoryList,
        Processor $processor,
        Data $paymentHelper,
        InvoiceIdentity $identityContainer,
        Renderer $addressRenderer,
        ProductFormatted $formatted,
        Items $items
    ) {
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->formatted = $formatted;
        $this->items = $items;
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

        $this->formatted->applySourceOrder($source);

        /**transport use to get the variables $order object, $source object and the template model object*/
        $parts = $this->buildVariablesAndProcessTemplate();

        /** instantiate the mPDF class and add the processed html to get the pdf*/

        /** @var Output $applySettings */
        $applySettings = $this->applyPdfSettings($parts);

        $fileParts = [
            'filestream' => $applySettings,
            'filename' => filter_var($parts['filename'], FILTER_SANITIZE_URL)
        ];

        return $fileParts;
    }
}
