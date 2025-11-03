<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-09-09T12:28:40+00:00
 * File:          Model/PdfTemplate.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model;

use Magento\Framework\Exception\LocalizedException;
use Xtento\PdfCustomizer\Api\Data\TemplatesInterface;
use Magento\Framework\Model\AbstractModel;

class PdfTemplate extends AbstractModel implements TemplatesInterface
{
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * Init resource model for the templates
     * @return void
     */
    public function _construct()
    {
        $this->_init('Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate');
    }

    /**
     * PdfTemplate constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->filesystem = $filesystem;
    }

    public function getTemplateHtml()
    {
        // Change in version 2.7.9 from custom_ to formatted_ prefix to keep old templates compatible
        $originalTemplate = trim($this->getData('template_html'));
        // Ability to load templates from files
        $loadTemplateFromFile = strpos($originalTemplate, '<') === false || substr_count($originalTemplate, "\n") === 0;
        if ($loadTemplateFromFile && !empty($originalTemplate)) {
            $templatePath = $this->fixBasePath($originalTemplate);
            try {
                $fileExists = file_exists($templatePath);
            } catch (\Exception $e) {
                $fileExists = false;
            }
            if (!$fileExists) {
                throw new LocalizedException(__('Your PDF Template is empty, or the file you tried to specify does not exist or could not be loaded.'));
            } else {
                $originalTemplate = file_get_contents($templatePath);
                if (strpos($originalTemplate, '<') === false || strpos($originalTemplate, '##body_start##') === false) { // Security protection against loading arbitrary files
                    throw new LocalizedException(__('The file you are trying to load as a PDF Template is not a valid template file. Either it does not contain HTML or the ##body_start## and end tags are missing.'));
                }
            }
        }

        $adjustedTemplate = str_replace('var custom_', 'var formatted_', $originalTemplate);
        $adjustedTemplate = str_replace('var order.item.', 'var order_item.', $adjustedTemplate);
        $adjustedTemplate = str_replace('var formatted_item_if.', 'var item_if.', $adjustedTemplate);
        $adjustedTemplate = str_replace('var order.custom_item.', 'var formatted_order_item.', $adjustedTemplate);
        $adjustedTemplate = str_replace('var order_custom_item_product.', 'var formatted_order_item_product.', $adjustedTemplate);
        $adjustedTemplate = str_replace('var order_custom_item_product_if.', 'var order_item_product_if.', $adjustedTemplate);

        return $adjustedTemplate;
    }

    public function getTemplateCss()
    {
        $templateCss = trim($this->getData('template_css'));
        // Ability to load templates from files
        $loadCssFromFile = substr_count($templateCss, "\n") === 0;
        if ($loadCssFromFile && !empty($templateCss)) {
            $cssPath = $this->fixBasePath($templateCss);
            if (!preg_match('/\.css$/', $cssPath)) { // Security protection against loading arbitrary files
                throw new LocalizedException(__('The file you are trying to load as a CSS file is not a valid CSS file. The file extension has to be .css.'));
            }
            try {
                $fileExists = file_exists($cssPath);
            } catch (\Exception $e) {
                $fileExists = false;
            }
            if (!$fileExists) {
                throw new LocalizedException(__('Your Template CSS is empty, or the file you tried to specify does not exist or could not be loaded.'));
            } else {
                $templateCss = file_get_contents($cssPath);
            }
        }

        return $templateCss;
    }

    protected function fixBasePath($originalPath)
    {
        /*
        * Let's try to fix the template directory and replace the dot with the actual Magento root directory.
        * Why? Because if the cronjob is executed using the PHP binary a different working directory (when using a dot (.) in a directory path) could be used.
        * But Magento is able to return the right base path, so let's use it instead of the dot.
        */
        $originalPath = str_replace('/', DIRECTORY_SEPARATOR, $originalPath);
        if (substr($originalPath, 0, 2) == '.' . DIRECTORY_SEPARATOR) {
            return rtrim($this->filesystem->getDirectoryRead(
                \Magento\Framework\App\Filesystem\DirectoryList::ROOT
            )->getAbsolutePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . substr($originalPath, 2);
        }
        return $originalPath;
    }
}
