<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-05-16T21:17:38+00:00
 * File:          Helper/Variable/Processors/Output.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Xtento\PdfCustomizer\Helper\Variable\Formatted;
use Xtento\PdfCustomizer\Model\Source\TemplatePaperForm;
use Xtento\PdfCustomizer\Model\Source\TemplatePaperOrientation;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Xtento\PdfCustomizer\Model\Files\Synchronization;
use Xtento\PdfCustomizer\Helper\Variable\Custom\SalesCollect as TaxHelper;
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
class Output extends Pdf
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
     * Output constructor.
     *
     * @param Context $context
     * @param File $file
     * @param DirectoryList $directoryList
     * @param Processor $processor
     * @param Data $paymentHelper
     * @param InvoiceIdentity $identityContainer
     * @param Renderer $addressRenderer
     * @param Formatted $formatted
     * @param Items $items
     * @param Zend_Pdf $zendPdf
     * @param Zend_Pdf_Resource_Extractor $zendExtractor
     * @param Synchronization $synchronization
     * @param TemplatePaperForm $templatePaperForm
     * @param TemplatePaperOrientation $templatePaperOrientation
     * @param TaxHelper $taxHelper
     * @param Tax $taxProcessor
     * @param Tracking $trackingProcessor
     * @param Totals $totalsProcessor
     * @param ModuleDirReader $moduleDirReader
     * @param Filesystem $filesystem
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
        Items $items,
        Zend_Pdf $zendPdf,
        Zend_Pdf_Resource_Extractor $zendExtractor,
        Synchronization $synchronization,
        TemplatePaperForm $templatePaperForm,
        TemplatePaperOrientation $templatePaperOrientation,
        TaxHelper $taxHelper,
        Tax $taxProcessor,
        Tracking $trackingProcessor,
        Totals $totalsProcessor,
        ModuleDirReader $moduleDirReader,
        Filesystem $filesystem
    ) {
        $this->zendPdf = $zendPdf;
        $this->zendExtractor = $zendExtractor;
        $this->synchronization = $synchronization;
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
            $items,
            $taxHelper,
            $taxProcessor,
            $trackingProcessor,
            $totalsProcessor
        );
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

        if (!$templateModel->getTemplateCustomForm()) {
            $pdf = $this->standardSizePdf($templateModel);
        } else {
            $pdf = $this->customSizePdf($templateModel);
        }

        // solve the bug with table body background white
        $header = str_replace(['<tbody>', '</tbody>'], '', $parts['header']);
        $footer = str_replace(['<tbody>', '</tbody>'], '', $parts['footer']);
        $outputFooterOnEveryPage = stristr($templateModel->getTemplateHtml(), 'outputFooterOnEveryPage="true"') !== false;
        $cssToAdd = 'dl { margin-top: 0; margin-bottom: 0; }';

        //@codingStandardsIgnoreStart
        try {
            // CSS
            $pdf->WriteHTML($cssToAdd . $templateModel->getTemplateCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
            // Header
            $pdf->SetHTMLHeader(html_entity_decode($header));
            // Footer - on every page
            if ($outputFooterOnEveryPage) $pdf->SetHTMLFooter(html_entity_decode($footer));
            // Body
            $pdf->WriteHTML('<body>' . html_entity_decode($parts['body']) . '</body>');
            // Footer - only on last page
            if (!$outputFooterOnEveryPage) $pdf->SetHTMLFooter(html_entity_decode($footer));
        } catch (\Mpdf\Barcode\BarcodeException $e) {
            // Barcode is invalid and cannot be output
            $pdf->WriteText(10, 10, (string)__('Error while generating barcode, you must use another barcode type if you want to output this data: %1', $e->getMessage()));
        }
        //@codingStandardsIgnoreEnd

        $tmpFile = $this->directoryList->getPath('tmp') . DIRECTORY_SEPARATOR . $templateModel->getId() . $this->source->getEntityType() . $this->source->getIncrementId() . '.pdf';
        $this->addPdfFile($tmpFile);
        $pdf->Output($tmpFile, 'F');

        // Add background PDF to HTML generated PDF
        if ($templateModel->getAttachmentPdfFile() != '') {
            $attachmentFilename = $templateModel->getAttachmentPdfFile();
            $fullFilePath = $this->varDirectory->getAbsolutePath('pdfattachments/') . $attachmentFilename;
            if ($this->file->isExists($fullFilePath)) {
                //solution from https://stackoverflow.com/a/36761366
                if (!$templateModel->getTemplateCustomForm()) {
                    $pdfWithBackground = $this->standardSizePdf($templateModel);
                } else {
                    $pdfWithBackground = $this->customSizePdf($templateModel);
                }

                // let's get an id for the background template
                $pdfWithBackground->setSourceFile($fullFilePath);
                $backgroundId = $pdfWithBackground->importPage(1);

                // iterate over all pages of HTML generated PDF and import them
                $pageCount = $pdfWithBackground->setSourceFile($tmpFile);
                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                    $pdfWithBackground->AddPage();
                    $pdfWithBackground->useTemplate($backgroundId);
                    $pageId = $pdfWithBackground->importPage($pageNumber);
                    $pdfWithBackground->useTemplate($pageId);
                }

                $pdfWithBackground->Output($tmpFile, 'F');
            }
        }

        error_reporting($oldErrorReporting);
        return null;
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
    protected function standardSizePdf($templateModel, $finalOri = 'P')
    {
        $ori = $templateModel->getTemplatePaperOri();
        $orientation = $this->templatePaperOrientation->getAvailable();
        $finalOri = $orientation[$ori][0];

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
            'margin_top' => $templateModel->getTemplateCustomT(),
            'margin_bottom' => $templateModel->getTemplateCustomB(),
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

        if ($templateModel->getTemplateCustomT() == 0 || stristr($templateModel->getTemplateHtml(), 'setAutoTopMargin="stretch"') !== false) {
            $config = array_merge(
                $config,
                [
                    'setAutoTopMargin' => 'stretch',
                    'setAutoBottomMargin' => 'stretch',
                    'autoMarginPadding' => 0
                ]
            );
        }

        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setPdfType('sales');
        $transportObject->setConfig($config);
        $transportObject->setCustomFontFolder(false);
        $this->_eventManager->dispatch('xtento_pdfcustomizer_get_mpdf_config', ['transport' => $transportObject]);
        $config = $transportObject->getConfig();
        $customFontFolder = $transportObject->getCustomFontFolder();

        //@codingStandardsIgnoreLine
        $pdf = new Mpdf($config);
        $this->setFontDirectory($pdf);
        //$pdf->showImageErrors = true;

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
    protected function customSizePdf($templateModel)
    {
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
            'margin_top' => $templateModel->getTemplateCustomT(),
            'margin_bottom' => $templateModel->getTemplateCustomB(),
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

        if ($templateModel->getTemplateCustomT() == 0) { // Stylish templates
            $config = array_merge(
                $config,
                [
                    'setAutoTopMargin' => 'stretch',
                    'setAutoBottomMargin' => 'stretch',
                    'autoMarginPadding' => 0
                ]
            );
        }

        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setPdfType('sales');
        $transportObject->setConfig($config);
        $transportObject->setCustomFontFolder(false);
        $this->_eventManager->dispatch('xtento_pdfcustomizer_get_mpdf_config', ['transport' => $transportObject]);
        $config = $transportObject->getConfig();
        $customFontFolder = $transportObject->getCustomFontFolder();

        //@codingStandardsIgnoreLine
        $pdf = new Mpdf($config);
        $this->setFontDirectory($pdf, $customFontFolder);

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
            if (stristr($fileName, 'xtento_pdf') === false) { // Don't delete from backup folder
                $this->file->deleteFile($fileName);
            }
        }

        $pdfNew->pages = [];
        $this->pdfFiles = [];

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
