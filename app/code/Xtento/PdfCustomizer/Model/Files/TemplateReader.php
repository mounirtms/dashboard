<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-09-07T15:20:37+00:00
 * File:          Model/Files/TemplateReader.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Files;

use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Class TemplateReader
 * @package Xtento\PdfCustomizer\Model
 */
class TemplateReader
{

    const PDF_TEMPLATES_DIR = 'pdftemplates';
    const HTML = 'html';
    const CSS = 'css';
    const PREVIEW = 'preview';

    /**
     * @var ModuleDirReader
     */
    private $moduleDirReader;

    /**
     * @var File
     */
    private $file;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * TemplateReader constructor.
     *
     * @param File $file
     * @param DirectoryList $directoryList
     * @param ModuleDirReader $moduleDirReader
     */
    public function __construct(
        File $file,
        DirectoryList $directoryList,
        ModuleDirReader $moduleDirReader
    ) {
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->moduleDirReader = $moduleDirReader;
    }

    /**
     * @return string
     */
    private function templatesLocation()
    {
        $viewDir = $this->moduleDirReader->getModuleDir(
            Dir::MODULE_VIEW_DIR,
            'Xtento_PdfCustomizer'
        );
        return $viewDir . DIRECTORY_SEPARATOR . self::PDF_TEMPLATES_DIR;
    }

    /**
     * @param bool $getAllTemplates
     *
     * @return array
     */
    public function directoryParser($getAllTemplates = false)
    {
        $templates = $this->htmlTemplates();

        $inserts = [];
        foreach ($templates as $template) {
            if (!$getAllTemplates && stristr($template, '_default.') === false) {
                continue;
            }
            $templateName = explode('.', $template);
            $inserts[] = $this->createInsertArray($templateName[0]);
        }
        //$inserts = array_reverse($inserts);

        return $inserts;
    }

    /**
     * @return array
     */
    public function htmlTemplates()
    {
        $path = $this->templatesLocation() . DIRECTORY_SEPARATOR . self::HTML;
        $files = $this->file->readDirectory($path);
        $fileNames = [];

        foreach ($files as $file) {
            //@codingStandardsIgnoreLine
            $fileNames[] = basename($file);
        }

        return $fileNames;
    }

    /**
     * @param $templateName
     *
     * @return array
     */
    public function createInsertArray($templateName)
    {
        $htmlPath = $this->templatesLocation() . DIRECTORY_SEPARATOR . self::HTML . DIRECTORY_SEPARATOR;
        $htmlContents = $this->file->fileGetContents($htmlPath . $templateName . '.html');

        $cssPath = $this->templatesLocation() . DIRECTORY_SEPARATOR . self::CSS . DIRECTORY_SEPARATOR;
        $cssContents = $this->file->fileGetContents($cssPath . $templateName . '.css');

        $thumbnailPath = $this->templatesLocation() . DIRECTORY_SEPARATOR . self::PREVIEW . DIRECTORY_SEPARATOR;
        try {
            $thumbnailImage = base64_encode($this->file->fileGetContents($thumbnailPath . $templateName . '.jpg'));
        } catch (\Exception $e) {
            $thumbnailImage = base64_encode($this->file->fileGetContents($thumbnailPath . '_placeholder.jpg'));
        }

        $name = ucwords(str_replace('_', ' ', $templateName));
        $name = preg_replace('/(.*)(\d)(.*)/', '$1$3 (Variant $2)', $name);
        $name = str_replace(['Portrait', 'Landscape'], ['(Portrait)', '(Landscape)'], $name);
        $typeString = explode('_', $templateName);

        $type = array_flip(TemplateType::TYPES)[$typeString[1]];

        $orientation = 1;
        if ($typeString[2] === 'landscape') {
            $orientation = 2;
        }

        $top = 50;
        $bottom = 20;
        $right = 20;
        $left = 20;

        if (preg_match('/^(stylish|design)/', $typeString[0])) {
            $top = 0;
            $bottom = 15;
            $right = 0;
            $left = 0;

            if ($typeString[1] == 'product') {
                $bottom = 0;
            }
        }

        if ($typeString[1] !== 'product') {
            $filename = $typeString[1] . '_{{var increment_id}}.pdf';
        } else {
            $filename = $templateName . '.pdf';
        }

        $data = [
            'store_id' => 0,
            'is_active' => 1,
            'template_name' => $name,
            'template_description' => $name,
            'template_default' => 1,
            'template_type' => $type,
            'template_html' => $htmlContents,
            'template_css' => $cssContents,
            'template_file_name' => $filename,
            'template_paper_form' => 1,
            'template_custom_form' => 0,
            'template_custom_h' => 25,
            'template_custom_w' => 25,
            'template_custom_t' => $top,
            'template_custom_b' => $bottom,
            'template_custom_l' => $left,
            'template_custom_r' => $right,
            'template_paper_ori' => $orientation,
            'thumbnail' => $thumbnailImage,
            'customer_group_id' => '0',
            'creation_time' => time(),
            'update_time' => time(),
            'attachments' => ''
        ];

        return $data;
    }
}
