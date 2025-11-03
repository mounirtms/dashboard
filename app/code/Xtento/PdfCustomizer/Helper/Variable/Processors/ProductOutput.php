<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-10-23T08:43:33+00:00
 * File:          Helper/Variable/Processors/ProductOutput.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Magento\Store\Model\ScopeInterface;
use Xtento\PdfCustomizer\Helper\Variable\ProductFormatted;
use Xtento\PdfCustomizer\Model\Source\TemplatePaperForm;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Source\TemplatePaperOrientation;
use Xtento\PdfCustomizer\Helper\AbstractPdf;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Xtento\PdfCustomizer\Model\Files\Synchronization;
use Xtento\PdfCustomizer\Helper\Variable\Custom\Product as CustomProduct;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Payment\Helper\Data;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Zend_Pdf;
use Zend_Pdf_Resource_Extractor;
use Mpdf\Mpdf;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Filesystem;

/**
 * Class Output
 *
 * @package Xtento\PdfCustomizer\Helper\Variable\Processors
 */
class ProductOutput extends ProductPdf
{

    /**
     * @var array
     */
    private $pdfFiles = [];

    /**
     * @var Zend_Pdf
     */
    private $zendPdf;

    /**
     * @var Zend_Pdf_Resource_Extractor
     */
    private $zendExtractor;

    /**
     * @var Synchronization
     */
    private $synchronization;

    /**
     * @var CustomProduct
     */
    private $customProduct;

    /**
     * @var TemplatePaperForm
     */
    private $templatePaperForm;

    /**
     * @var TemplatePaperOrientation
     */
    private $templatePaperOrientation;

    /**
     * @var ModuleDirReader
     */
    private $moduleDirReader;

    /**
     * @var Filesystem\Directory\WriteInterface
     */
    private $varDirectory;

    /**
     * ProductOutput constructor.
     *
     * @param Context $context
     * @param File $file
     * @param DirectoryList $directoryList
     * @param Processor $processor
     * @param Data $paymentHelper
     * @param InvoiceIdentity $identityContainer
     * @param Renderer $addressRenderer
     * @param ProductFormatted $formatted
     * @param Items $items
     * @param Zend_Pdf $zendPdf
     * @param Zend_Pdf_Resource_Extractor $zendExtractor
     * @param Synchronization $synchronization
     * @param CustomProduct $customProduct
     * @param TemplatePaperForm $templatePaperForm
     * @param TemplatePaperOrientation $templatePaperOrientation
     * @param ModuleDirReader $moduleDirReader
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
        Items $items,
        Zend_Pdf $zendPdf,
        Zend_Pdf_Resource_Extractor $zendExtractor,
        Synchronization $synchronization,
        CustomProduct $customProduct,
        TemplatePaperForm $templatePaperForm,
        TemplatePaperOrientation $templatePaperOrientation,
        ModuleDirReader $moduleDirReader,
        Filesystem $filesystem
    ) {
        $this->synchronization = $synchronization;
        $this->zendPdf = $zendPdf;
        $this->zendExtractor = $zendExtractor;
        $this->customProduct = $customProduct;
        $this->templatePaperForm = $templatePaperForm;
        $this->templatePaperOrientation = $templatePaperOrientation;
        $this->moduleDirReader = $moduleDirReader;
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct(
            $context,
            $file,
            $directoryList,
            $processor,
            $paymentHelper,
            $identityContainer,
            $addressRenderer,
            $formatted,
            $items
        );
    }

    /**
     * @param $source
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @param $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\MailException
     */
    public function buildVariablesAndProcessTemplate()
    {
        $source = $this->source;

        $templateModel = $this->template;
        $templateType = $templateModel->getData('template_type');

        $templateTypeName = TemplateType::TYPES[$templateType];
        $templateHtml = $templateModel->getTemplateHtml();

        // Performance improvement: Only load variables that are actually required
        $transport = [];
        if (strstr($templateHtml, ' ' . $templateTypeName . '.') !== false) {
            $transport[$templateTypeName] = $source;
        }
        if (strstr($templateHtml, ' product.') !== false) {
            $extendedProduct = $this->customProduct->entity($source)->processAndReadVariables();
            $transport['product'] = $extendedProduct;
        }
        if (strstr($templateHtml, ' store.') !== false) {
            $transport['store'] = $source;
        }
        if (strstr($templateHtml, ' formatted_' . $templateTypeName . '.') !== false) {
            $transport['formatted_' . $templateTypeName] = $this->formatted->getFormatted($source);
        }
        if (strstr($templateHtml, ' formatted_product.') !== false) {
            $transport['formatted_product'] = $this->formatted->getFormatted($source);
        }
        if (strstr($templateHtml, ' ' . $templateTypeName . '_if.') !== false) {
            $transport[$templateTypeName . '_if.'] = $this->formatted->getZeroFormatted($source);
        }
        if (strstr($templateHtml, ' product_if.') !== false) {
            $transport['product_if.'] = $this->formatted->getZeroFormatted($source);
        }
        if (strstr($templateHtml, ' store_information') !== false) {
            $storeInfo = $this->scopeConfig->getValue('general/store_information', ScopeInterface::SCOPE_STORE, $source->getStoreId());
            $transport['store_information'] = $storeInfo;
            // Remove empty values for "depend" usage
            $transport['store_information_if'] = $this->formatted->getIfFormattedArray($storeInfo);
        }

        foreach (AbstractPdf::CODE_BAR as $code) {
            if (strstr($templateHtml, 'barcode_' . $code . '_' . $templateTypeName) !== false) {
                $transport['barcode_' . $code . '_' . $templateTypeName] = $this->formatted->getBarcodeFormatted($source, $code);
            }
        }

        // Ability to customize variables using an event. Store them using $transportObject->setCustomVariables();
        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setCustomVariables([]);
        $this->_eventManager->dispatch(
            'xtento_pdfcustomizer_build_transport_after',
            [
                'type' => 'product',
                'object' => $this->source,
                'variables' => $transport,
                'transport' => $transportObject
            ]
        );
        $transport = array_merge($transport, $transportObject->getCustomVariables());

        /** @var Processor $processor */
        $processor = $this->processor;
        $processor->setVariables($transport);
        $processor->setTemplate($this->template);
        $parts = $processor->processTemplate();

        return $parts;
    }

    /**
     * @param $parts
     *
     * @return null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function applyPdfSettings($parts)
    {
        $templateModel = $this->template;

        if (!$this->synchronization->isInSync()) {
            $this->synchronization->synchronizeData();
        }

        $oldErrorReporting = error_reporting();
        error_reporting(0);

        if (isset($parts['cover']) && !empty((string)$parts['cover']) && count($this->pdfFiles) === 0) {
            // Add cover
            $this->createCoverFile($templateModel, $parts['cover']);
        }

        if (!$templateModel->getTemplateCustomForm()) {
            $pdf = $this->standardSizePdf($templateModel);
        } else {
            $pdf = $this->customSizePdf($templateModel);
        }

        // solve the bug with table body background white
        $header = str_replace(['<tbody>', '</tbody>'], '', $parts['header']);
        $footer = str_replace(['<tbody>', '</tbody>'], '', $parts['footer']);
        $outputFooterOnEveryPage = stristr($templateModel->getTemplateHtml(), 'outputFooterOnEveryPage="true"') !== false;
        $body = str_replace('{PAGE_NUMBER}', count($this->pdfFiles), $parts['body']);

        //@codingStandardsIgnoreStart
        try {
            $pdf->WriteHTML($templateModel->getTemplateCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
            $pdf->SetHTMLHeader(html_entity_decode($header));
            // Background
            if ($templateModel->getAttachmentPdfFile() != '') {
                $attachmentFilename = $templateModel->getAttachmentPdfFile();
                $fullFilePath = $this->varDirectory->getAbsolutePath('pdfattachments/') . $attachmentFilename;
                if ($this->file->isExists($fullFilePath)) {
                    $background = $pdf->setSourceFile($fullFilePath);
                    $backgroundIndex = $pdf->importPage($background);
                    $pdf->useTemplate($backgroundIndex);
                }
            }
            if ($outputFooterOnEveryPage) $pdf->SetHTMLFooter(html_entity_decode($footer));
            $pdf->WriteHTML('<body>' . html_entity_decode($body) . '</body>');
            if (!$outputFooterOnEveryPage) $pdf->SetHTMLFooter(html_entity_decode($footer));
        } catch (\Mpdf\Barcode\BarcodeException $e) {
            // Barcode is invalid and cannot be output
            $pdf->WriteText(10, 10, (string)__('Error while generating barcode, you must use another barcode type if you want to output this data: %1', $e->getMessage()));
        }
        //@codingStandardsIgnoreEnd

        $tmpFile = $this->directoryList->getPath('tmp') . DIRECTORY_SEPARATOR . $this->source->getId() . '.pdf';
        $this->addPdfFile($tmpFile);
        $pdf->Output($tmpFile, 'F');

        error_reporting($oldErrorReporting);
        return null;
    }

    /**
     * @param $templateModel
     * @param $coverHtml
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function createCoverFile($templateModel, $coverHtml)
    {
        if (!$templateModel->getTemplateCustomForm()) {
            $pdf = $this->standardSizePdf($templateModel);
        } else {
            $pdf = $this->customSizePdf($templateModel);
        }

        //@codingStandardsIgnoreStart
        try {
            $pdf->WriteHTML(html_entity_decode($coverHtml));
        } catch (\Mpdf\Barcode\BarcodeException $e) {
            // Barcode is invalid and cannot be output
            $pdf->WriteText(10, 10, (string)__('Error while generating barcode, you must use another barcode type if you want to output this data: %1', $e->getMessage()));
        }
        //@codingStandardsIgnoreEnd

        $tmpFile = $this->directoryList->getPath('tmp') . DIRECTORY_SEPARATOR . $this->source->getId() . '_cover.pdf';
        $this->addPdfFile($tmpFile);
        $pdf->Output($tmpFile, 'F');

        return $this;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Zend_Pdf_Exception
     */
    public function pdfMerger()
    {
        $files = $this->pdfFiles;
        return $this->generateMergeZend($files);
    }

    /**
     * @param $templateModel
     * @param string $finalOri
     * @return Mpdf
     * @throws \Mpdf\MpdfException
     */
    private function standardSizePdf($templateModel, $finalOri = 'P')
    {
        $ori = $templateModel->getTemplatePaperOri();
        $orientation = $this->templatePaperOrientation->getAvailable();
        $finalOri = $orientation[$ori][0];


        $marginTop = $templateModel->getTemplateCustomT();
        $marginBottom = $templateModel->getTemplateCustomB();

        $paperForms = $this->templatePaperForm->getAvailable();

        $templatePaperForm = $templateModel->getTemplatePaperForm();

        if(!$templatePaperForm){
            $templatePaperForm = 1;
        }

        $form = $paperForms[$templatePaperForm];
        if ($ori == TemplatePaperOrientation::TEMAPLATE_PAPER_LANDSCAPE) {
            $form = $paperForms[$templateModel->getTemplatePaperForm()] . '-' . $finalOri;
        }

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $config = [
            'mode' => '',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'format' => $form,
            'default_font_size' => '',
            'default_font' => '',
            'margin_left' => $templateModel->getTemplateCustomL(),
            'margin_right' => $templateModel->getTemplateCustomR(),
            'margin_top' => $marginTop,
            'margin_bottom' => $marginBottom,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $this->directoryList->getPath('tmp'),
            'fontdata' => $fontData + [
                    'fontawesome' => [
                        'R' => 'fontawesome-webfont.ttf',
                    ],
                    'Lora' => [
                        'R' => 'Lora-Regular.ttf',
                        'B' => 'Lora-Bold.ttf',
                    ],
                    'Roboto' => [
                        'R' => 'Roboto-Regular.ttf',
                        'B' => 'Roboto-Bold.ttf',
                    ]
                ],
        ];

        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setPdfType('product');
        $transportObject->setConfig($config);
        $transportObject->setCustomFontFolder(false);
        $this->_eventManager->dispatch('xtento_pdfcustomizer_get_mpdf_config', ['transport' => $transportObject]);
        $config = $transportObject->getConfig();
        $customFontFolder = $transportObject->getCustomFontFolder();

        //@codingStandardsIgnoreLine
        $pdf = new Mpdf($config);
        $this->setFontDirectory($pdf);

        // From event above
        if ($customFontFolder !== false) {
            $pdf->AddFontDirectory($customFontFolder);
        }

        return $pdf;
    }

    /**
     * @param $templateModel
     * @return Mpdf
     * @throws \Mpdf\MpdfException
     */
    private function customSizePdf($templateModel)
    {
        $marginTop = $templateModel->getTemplateCustomT();
        $marginBottom = $templateModel->getTemplateCustomB();

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $config = [
            'mode' => '',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'format' => [
                $templateModel->getTemplateCustomW(),
                $templateModel->getTemplateCustomH()
            ],
            'default_font_size' => '',
            'default_font' => '',
            'margin_left' => $templateModel->getTemplateCustomL(),
            'margin_right' => $templateModel->getTemplateCustomR(),
            'margin_top' => $marginTop,
            'margin_bottom' => $marginBottom,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $this->directoryList->getPath('tmp'),
            'fontdata' => $fontData + [
                    'fontawesome' => [
                        'R' => 'fontawesome-webfont.ttf',
                    ],
                    'Lora' => [
                        'R' => 'Lora-Regular.ttf',
                        'B' => 'Lora-Bold.ttf',
                    ],
                    'Roboto' => [
                        'R' => 'Roboto-Regular.ttf',
                        'B' => 'Roboto-Bold.ttf',
                    ]
                ],
        ];

        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setPdfType('product');
        $transportObject->setConfig($config);
        $transportObject->setCustomFontFolder(false);
        $this->_eventManager->dispatch('xtento_pdfcustomizer_get_mpdf_config', ['transport' => $transportObject]);
        $config = $transportObject->getConfig();
        $customFontFolder = $transportObject->getCustomFontFolder();

        //@codingStandardsIgnoreLine
        $pdf = new Mpdf($config);
        $this->setFontDirectory($pdf);

        // From event above
        if ($customFontFolder !== false) {
            $pdf->AddFontDirectory($customFontFolder);
        }

        return $pdf;
    }

    protected function setFontDirectory($pdf)
    {
        $viewDir = $this->moduleDirReader->getModuleDir(
            Dir::MODULE_VIEW_DIR,
            'Xtento_PdfCustomizer'
        );

        return $pdf->AddFontDirectory($viewDir . DIRECTORY_SEPARATOR . 'pdftemplates/fonts');
    }

    /**
     * @param $files
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Zend_Pdf_Exception
     */
    private function generateMergeZend($files)
    {
        $pdfNew = $this->zendPdf;
        foreach ($files as $file) {
            //@codingStandardsIgnoreLine
            $pdf = Zend_Pdf::load($file);
            $extractor = $this->zendExtractor;
            foreach ($pdf->pages as $page) {
                $pdfExtract = $extractor->clonePage($page);
                $pdfNew->pages[] = $pdfExtract;
            }
        }

        $pdfToOutput = $pdfNew->render();

        foreach ($files as $fileName) {
            try {
                $this->file->deleteFile($fileName);
            } catch (\Exception $e) {}
        }

        return $pdfToOutput;
    }

    /**
     * @param $pdfFile
     */
    public function addPdfFile($pdfFile)
    {
        $this->pdfFiles[] = $pdfFile;
    }

    /**
     * Get last PDF added to PDF array
     */
    public function getLastPdf()
    {
        return array_values(array_slice($this->pdfFiles, -1))[0];
    }
}
