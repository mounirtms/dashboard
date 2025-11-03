<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Setup/Recurring.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Setup;

use Xtento\PdfCustomizer\Model\Files\TemplateReader;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Store\Model\StoreManagerInterface;
use Xtento\PdfCustomizer\Model\PdfTemplateFactory;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;

/**
 * Class Recurring
 * @package Xtento\CustomAttributes\Setup
 */
class Recurring implements InstallSchemaInterface
{
    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PdfTemplateFactory
     */
    private $templateFactory;

    /**
     * @var TemplateRepository
     */
    private $templateRepository;

    /**
     * @var TemplateReader
     */
    private $templateReader;

    /**
     * InstallData constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param PdfTemplateFactory $templateFactory
     * @param TemplateRepository $templateRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        PdfTemplateFactory $templateFactory,
        TemplateRepository $templateRepository,
        TemplateReader $templateReader
    ) {
        $this->storeManager = $storeManager;
        $this->templateFactory = $templateFactory;
        $this->templateRepository = $templateRepository;
        $this->templateReader = $templateReader;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
//        $templates = $this->templateReader->directoryParser();
//
//        if (empty($templates)) {
//            return $this;
//        }
//
//        foreach ($templates as $template) {
//            $tmpl = $this->templateFactory->create();
//            $tmpl->setData($template);
//            //@codingStandardsIgnoreLine
//            $this->templateRepository->save($tmpl);
//        }
    }
}
